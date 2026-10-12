<?php

namespace App\Support;

use App\Models\Content;
use App\Models\Page;
use App\Models\PageGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Posiziona un contenuto a partire da una pagina (drop dal pannello
 * "Contenuti da assegnare", bug 1, 2026-10-12): occupa tante pagine
 * consecutive quante ne dichiara la sua occupazione (PageOccupancy), e per
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
     * Le pagine occupate (in ordine), la percentuale per pagina e
     * l'eventuale gruppo creato — oppure il messaggio d'errore.
     *
     * @return array{pages: Collection<int, Page>, percentage: string, group: PageGroup|null}|string
     */
    public static function place(Content $content, Page $startPage): array|string
    {
        $occupancy = $content->occupancy();
        $percentage = $content->occupiedPercentagePerPage();

        if ($occupancy === null || $percentage === null) {
            return "«{$content->displayLabel()}» non ha un'occupazione di pagina: impostala prima di posizionarlo (nessuna pagina è stata modificata).";
        }

        $span = $occupancy->pagesSpanned();

        $targetPages = Page::query()
            ->where('issue_id', $startPage->issue_id)
            ->where('position', '>=', $startPage->position)
            ->where('position', '<', $startPage->position + $span)
            ->orderBy('position')
            ->get();

        if ($targetPages->count() < $span) {
            return "«{$content->displayLabel()}» occupa {$span} pagine consecutive, ma dalla pagina {$startPage->position} il numero ne ha solo {$targetPages->count()}: scegli una pagina più in alto.";
        }

        foreach ($targetPages as $page) {
            if ($page->isLocked()) {
                return "La pagina {$page->position} è bloccata: sbloccala o scegli un'altra pagina di partenza (nessuna pagina è stata modificata).";
            }

            $occupied = $page->contents()->pluck('occupied_percentage')->all();

            if (! PagePercentageAllocator::fits($occupied, $percentage)) {
                return "Spazio insufficiente sulla pagina {$page->position} per «{$content->displayLabel()}» ({$percentage}% richiesto): nessuna pagina è stata modificata.";
            }
        }

        // files() è ordinata dalla più recente e keyBy() tiene l'ULTIMA
        // occorrenza di ogni chiave: invertendo prima, a parità di
        // pdf_page_number resta il file più recente.
        $files = $content->files()->get();
        $filesByPdfPage = $files->reverse()->keyBy('pdf_page_number');
        $latestFileId = $files->first()?->id;

        $group = DB::transaction(function () use ($content, $targetPages, $filesByPdfPage, $latestFileId, $percentage, $span) {
            foreach ($targetPages->values() as $index => $page) {
                // Multipagina: la pagina sorgente del PDF che corrisponde a
                // questa posizione. Pagina singola: il file più recente del
                // contenuto (stesso valore che il vecchio percorso ancorato
                // registrava sul pivot).
                $contentFileId = $span > 1
                    ? $filesByPdfPage->get($index + 1)?->id
                    : $latestFileId;
                ContentPageAttacher::attach($content, $page, $contentFileId, $percentage);
            }

            // Stesso collegamento automatico del percorso "📎 Carica
            // materiale" (Punto 4.5): le pagine di un contenuto multipagina
            // si spostano insieme. Un fallimento (pagina già in un altro
            // gruppo) non annulla il posizionamento, come lì.
            if ($span < 2) {
                return null;
            }

            $result = PageGroupLinker::link($content->issue, $targetPages);

            return $result instanceof PageGroup ? $result : null;
        });

        return ['pages' => $targetPages, 'percentage' => (string) $percentage, 'group' => $group];
    }
}
