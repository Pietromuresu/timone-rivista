<?php

namespace App\Support;

use App\Enums\CoverSlot;
use App\Models\Content;
use App\Models\Page;
use App\Models\PageGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Posiziona un contenuto a partire da una pagina (drop dal pannello
 * "Contenuti da assegnare", bug 1, 2026-10-12): occupa tante pagine
 * consecutive quante ne dichiara la sua occupazione (PageOccupancy), con
 * la distribuzione di default in dodicesimi (Fase 3, 2026-10-27: pagine
 * intere e la frazione sull'ultima, 2,5 pagine → 12, 12, 6), e per
 * un PDF multipagina aggancia a ciascuna pagina il ContentFile della
 * pagina sorgente corrispondente (pivot content_file_id), così ogni card
 * mostra la propria miniatura.
 *
 * Tutto o niente: se manca l'occupazione, se il numero finisce prima, se
 * una pagina è bloccata o non ha spazio, non scrive nulla e restituisce un
 * messaggio. Mai un ripiego silenzioso su "1 pagina" — era proprio quel
 * ripiego (implicito: il drop conosceva solo il concetto di pagina singola)
 * a nascondere il bug 1.
 *
 * Non parla con l'utente (niente addError/broadcast/ActivityLog): quello
 * resta al componente Livewire chiamante, stesso principio di
 * ContentPageAttacher e PageGroupLinker.
 */
final class ContentPlacer
{
    /**
     * Le pagine occupate (in ordine), i dodicesimi scritti su ciascuna
     * (stesso ordine) e l'eventuale gruppo creato — oppure il messaggio
     * d'errore.
     *
     * @return array{pages: Collection<int, Page>, twelfths: list<int>, group: PageGroup|null}|string
     */
    public static function place(Content $content, Page $startPage): array|string
    {
        $occupancy = $content->occupancy();

        if ($occupancy === null) {
            return "«{$content->displayLabel()}» non ha un'occupazione di pagina: impostala prima di posizionarlo (nessuna pagina è stata modificata).";
        }

        $span = $occupancy->pagesSpanned();
        $distribution = $occupancy->distribution();

        $targetPages = $startPage->isCover()
            ? self::coverTargets($startPage, $span)
            : self::internalTargets($startPage, $span);

        if (is_string($targetPages)) {
            return str_replace('{content}', $content->displayLabel(), $targetPages);
        }

        foreach ($targetPages->values() as $index => $page) {
            if ($page->isLocked()) {
                return 'La '.$page->label()." è bloccata: sbloccala o scegli un'altra pagina di partenza (nessuna pagina è stata modificata).";
            }

            $occupied = $page->contents()->pluck('occupied_twelfths')->all();

            if (! PageCapacity::fits($occupied, $distribution[$index])) {
                return PageCapacity::insufficientSpaceMessage($page->label(), $distribution[$index], PageCapacity::free($occupied), $content->displayLabel())
                    .' Nessuna pagina è stata modificata.';
            }
        }

        // files() è ordinata dalla più recente e keyBy() tiene l'ULTIMA
        // occorrenza di ogni chiave: invertendo prima, a parità di
        // pdf_page_number resta il file più recente.
        $files = $content->files()->get();
        // Per POSIZIONE nell'ordine delle pagine PDF, non per numero di
        // pagina: dal bug 8 (2026-10-26) un contenuto può avere solo un
        // intervallo del PDF (es. pagine 2-3), e la prima pagina del timone
        // deve ricevere la prima pagina SCELTA, non la "pagina 1" che non
        // esiste. Per i contenuti che partono da 1 il risultato è identico.
        $filesInPdfOrder = $files->reverse()->keyBy('pdf_page_number')->sortKeys()->values();
        $latestFileId = $files->first()?->id;

        $group = DB::transaction(function () use ($content, $targetPages, $filesInPdfOrder, $latestFileId, $distribution, $span) {
            foreach ($targetPages->values() as $index => $page) {
                // Multipagina: la pagina sorgente del PDF che corrisponde a
                // questa posizione. Pagina singola: il file più recente del
                // contenuto (stesso valore che il vecchio percorso ancorato
                // registrava sul pivot).
                $contentFileId = $span > 1
                    ? $filesInPdfOrder->get($index)?->id
                    : $latestFileId;
                ContentPageAttacher::attach($content, $page, $contentFileId, $distribution[$index]);
            }

            // Stesso collegamento automatico del percorso "📎 Carica
            // materiale" (Punto 4.5): le pagine di un contenuto multipagina
            // si spostano insieme. Un fallimento (pagina già in un altro
            // gruppo) non annulla il posizionamento, come lì.
            // Fase 2: le copertine non entrano mai in un gruppo collegato
            // (il gruppo si sposta tra le pagine interne, una copertina no).
            if ($span < 2 || $targetPages->contains(fn (Page $page) => $page->isCover())) {
                return null;
            }

            $result = PageGroupLinker::link($content->issue, $targetPages);

            return $result instanceof PageGroup ? $result : null;
        });

        return ['pages' => $targetPages, 'twelfths' => $distribution, 'group' => $group];
    }

    /**
     * Pagine interne consecutive dalla pagina di partenza.
     *
     * @return Collection<int, Page>|string
     */
    private static function internalTargets(Page $startPage, int $span): Collection|string
    {
        $targetPages = $startPage->issue->pages()
            ->where('position', '>=', $startPage->position)
            ->where('position', '<', $startPage->position + $span)
            ->get();

        if ($targetPages->count() < $span) {
            return "«{content}» occupa {$span} pagine consecutive, ma dalla pagina {$startPage->position} il numero ne ha solo {$targetPages->count()}: scegli una pagina più in alto.";
        }

        return $targetPages;
    }

    /**
     * Fase 2 (2026-10-26) — contenuto piazzato su una copertina. Una pagina:
     * solo la copertina. Più pagine (bug 8, "la prima sulla copertina, le
     * altre sulle pagine successive"), solo dove la copertina forma
     * un'apertura con le pagine interne:
     * - II di copertina: la II e poi le pagine 1, 2, … (l'apertura II + 1);
     * - III di copertina: le ultime pagine interne e poi la III
     *   (l'apertura ultima pagina + III), in ordine di sfoglio.
     * I, IV e bandella accolgono una sola pagina: non hanno pagine interne
     * accanto.
     *
     * @return Collection<int, Page>|string
     */
    private static function coverTargets(Page $cover, int $span): Collection|string
    {
        if ($span === 1) {
            return collect([$cover]);
        }

        $internal = $cover->issue->pages();

        $targetPages = match ($cover->cover_slot) {
            CoverSlot::II => collect([$cover])->concat($internal->where('position', '<', $span)->get()),
            CoverSlot::III => $internal->where('position', '>', $cover->issue->total_pages - ($span - 1))->get()->concat([$cover]),
            default => null,
        };

        if ($targetPages === null) {
            return "«{content}» occupa {$span} pagine: la {$cover->label()} ne accoglie una sola. Su più pagine si può partire dalla II di copertina (continua sulle pagine 1, 2…) o dalla III (le ultime pagine interne e poi la III).";
        }

        if ($targetPages->count() < $span) {
            return "«{content}» occupa {$span} pagine, ma il numero non ha abbastanza pagine interne accanto alla {$cover->label()}.";
        }

        return $targetPages->values();
    }
}
