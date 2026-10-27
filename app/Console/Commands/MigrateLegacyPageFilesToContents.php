<?php

namespace App\Console\Commands;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Page;
use App\Models\PageFile;
use App\Support\PageGroupLinker;
use App\Support\Twelfths;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Punto 8 del prompt "modifiche timone" (2026-09-02, NON eseguito
 * automaticamente — scritto e mostrato all'utente, in attesa di conferma
 * prima di lanciarlo davvero, come richiesto esplicitamente). Migra le
 * pagine con un PageFile diretto (caricato PRIMA dell'unificazione su
 * Content del Punto 4) verso il nuovo modello: un Content (+Article) con
 * uno o più ContentFile equivalenti, attaccati alla pagina via pivot con
 * `content_file_id` valorizzato. Il PageFile originale (riga E file
 * fisico) **non viene mai toccato né eliminato** — requisito esplicito
 * del prompt ("i dati/PDF/anteprime esistenti non vengono persi") — resta
 * come riferimento storico; `Page::previewFile()` preferirà comunque il
 * nuovo ContentFile una volta creato, quindi il PageFile diventa
 * invisibile in UI ma resta recuperabile.
 *
 * Il ContentFile creato PUNTA allo stesso file fisico del PageFile
 * originale (stesso disk/path/thumbnail_path, nessuna copia) — scelta
 * deliberata: non c'è motivo di duplicare byte su disco per un file che
 * non cambia, e la preview esistente (se già generata) viene riusata
 * così com'è, senza rigenerarla. Se un PageFile aveva
 * `thumbnail_status = Failed`, il ContentFile migrato eredita lo stesso
 * stato Failed (non viene ritentata la generazione qui — resta un
 * comando di sola migrazione dati, non di elaborazione file; un nuovo
 * tentativo richiederebbe ri-dispatchare GenerateContentFileThumbnail,
 * intenzionalmente lasciato fuori da questo comando per non introdurre
 * lavoro asincrono/side-effect in un'operazione pensata per essere
 * ispezionabile e ripetibile in --dry-run).
 *
 * Decisioni prese esplicitamente con l'utente prima di scrivere questo
 * comando (vedi HANDOFF.md, sessione 2026-09-02):
 * - raggruppamento dei PageFile che venivano dallo stesso upload multi-
 *   pagina originale: euristica "stesso original_name, pdf_page_number
 *   consecutivi, posizioni contigue" — nessun id di batch salvato nei
 *   dati esistenti, quindi non è una ricostruzione esatta (due upload
 *   indipendenti con lo stesso nome file apparirebbero come un unico
 *   gruppo); accettata così dall'utente;
 * - ogni pagina/gruppo migrato riceve un Content di tipo Articolo con un
 *   titolo generico ("Materiale pagina N" / "Materiale pagine N-M" per
 *   un gruppo), modificabile a mano in seguito da chi cura il numero.
 *
 * Solo le pagine il cui Page::previewFile() risolve OGGI al PageFile
 * legacy vengono migrate — una pagina che ha già un contenuto con un file
 * proprio (creato dopo il Punto 4: il PageFile legacy è quindi già
 * "coperto" e invisibile in UI) non riceve un secondo contenuto
 * ridondante.
 */
class MigrateLegacyPageFilesToContents extends Command
{
    protected $signature = 'timone:migrate-legacy-page-files {--dry-run : Mostra solo cosa verrebbe creato, senza scrivere nulla}';

    protected $description = 'Migra le pagine con un PDF caricato direttamente sulla pagina (prima del Punto 4) verso un Content equivalente';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $pages = Page::query()
            ->whereHas('files')
            ->with(['files' => fn ($q) => $q->latest(), 'contents.files', 'issue'])
            ->get()
            ->filter(fn (Page $page) => $page->previewFile() instanceof PageFile)
            ->values();

        if ($pages->isEmpty()) {
            $this->info('Nessuna pagina da migrare: tutte le pagine con un PageFile diretto hanno già un contenuto con anteprima propria, oppure non ce ne sono.');

            return self::SUCCESS;
        }

        $groups = $this->groupByHeuristic($pages);

        $this->info(($dryRun ? '[DRY RUN] ' : '').count($groups).' contenuti da creare, per '.$pages->count().' pagine totali.');

        foreach ($groups as $group) {
            $this->migrateGroup($group, $dryRun);
        }

        if (! $dryRun) {
            $this->info('Migrazione completata.');
        } else {
            $this->info('Nessuna scrittura effettuata (--dry-run). Rilancia senza l\'opzione per applicare davvero.');
        }

        return self::SUCCESS;
    }

    /**
     * Raggruppa le pagine per issue, poi in sequenze contigue che
     * condividono lo stesso file sorgente (stesso original_name,
     * pdf_page_number che avanza di 1 in 1, posizioni consecutive) — vedi
     * il docblock della classe per l'euristica e i suoi limiti noti.
     *
     * @param  Collection<int, Page>  $pages
     * @return list<Collection<int, Page>>
     */
    private function groupByHeuristic(Collection $pages): array
    {
        $groups = [];

        foreach ($pages->groupBy('issue_id') as $issuePages) {
            $sorted = $issuePages->sortBy('position')->values();
            $current = collect();
            $expectedName = null;
            $expectedPageNumber = null;
            $expectedPosition = null;

            foreach ($sorted as $page) {
                $file = $page->previewFile();

                $continuesBatch = $current->isNotEmpty()
                    && $file->original_name === $expectedName
                    && $file->pdf_page_number === $expectedPageNumber
                    && $page->position === $expectedPosition;

                if (! $continuesBatch && $current->isNotEmpty()) {
                    $groups[] = $current;
                    $current = collect();
                }

                $current->push($page);
                $expectedName = $file->original_name;
                $expectedPageNumber = $file->pdf_page_number + 1;
                $expectedPosition = $page->position + 1;
            }

            if ($current->isNotEmpty()) {
                $groups[] = $current;
            }
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Page>  $group
     */
    private function migrateGroup(Collection $group, bool $dryRun): void
    {
        $issue = $group->first()->issue;
        $positions = $group->pluck('position')->all();
        $title = count($positions) > 1
            ? 'Materiale pagine '.min($positions).'-'.max($positions)
            : 'Materiale pagina '.$positions[0];

        $this->line(
            ($dryRun ? '  [dry-run] creerei' : '  creo')
            ." «{$title}» — numero #{$issue->id}, pagine ".implode(', ', $positions)
            .(count($positions) >= 2 ? ' (collegate automaticamente)' : '')
        );

        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($group, $issue, $title) {
            $content = Content::create([
                'issue_id' => $issue->id,
                'type' => ContentType::Articolo,
                'title' => $title,
                // Bug 5 (2026-10-12, occupazione obbligatoria): qui il dato
                // è certo, non inventato — ogni pagina del gruppo era
                // occupata per intero dal suo PDF legacy (Fase 3: in
                // dodicesimi, 12 per pagina).
                'occupancy_twelfths' => $group->count() * Twelfths::PER_PAGE,
            ]);

            Article::create([
                'content_id' => $content->id,
                'editorial_status' => EditorialStatus::DaScrivere->value,
            ]);

            foreach ($group as $page) {
                $legacyFile = $page->previewFile();

                $contentFile = ContentFile::create([
                    'content_id' => $content->id,
                    'uploaded_by' => $legacyFile->uploaded_by,
                    'disk' => $legacyFile->disk,
                    // Stesso file fisico del PageFile originale, nessuna
                    // copia — vedi il docblock della classe.
                    'path' => $legacyFile->path,
                    'thumbnail_path' => $legacyFile->thumbnail_path,
                    'original_name' => $legacyFile->original_name,
                    'mime_type' => 'application/pdf',
                    'size' => $legacyFile->size,
                    'thumbnail_status' => $legacyFile->thumbnail_status,
                    'pdf_page_number' => $legacyFile->pdf_page_number,
                    'format_check_status' => $legacyFile->format_check_status,
                    'measured_width_mm' => $legacyFile->measured_width_mm,
                    'measured_height_mm' => $legacyFile->measured_height_mm,
                    'format_override_confirmed_by' => $legacyFile->format_override_confirmed_by,
                    'format_override_confirmed_at' => $legacyFile->format_override_confirmed_at,
                ]);

                $page->contents()->attach($content->id, [
                    'occupied_twelfths' => Twelfths::PER_PAGE,
                    'position' => 1,
                    'content_file_id' => $contentFile->id,
                ]);
            }

            // Punto 5 applicato retroattivamente ai dati storici (coerenza
            // esplicitamente richiesta dal prompt): riusa la stessa
            // funzione di collegamento di sempre, non una copia.
            if ($group->count() >= 2) {
                PageGroupLinker::link($issue, $group);
            }
        });
    }
}
