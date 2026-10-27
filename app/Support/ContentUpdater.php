<?php

namespace App\Support;

use App\Enums\ContentType;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Modifica di un contenuto esistente (Fase 4, 2026-10-27) — il pendant di
 * ContentCreator. Le modifiche valgono per tutte le pagine occupate dal
 * contenuto, perché titolo, tipo, occupazione e file stanno sul Content,
 * non sulla pagina.
 *
 * Cosa succede alle pagine:
 * - occupazione cambiata e contenuto già posizionato → ridistribuzione
 *   "tutto o niente" con ContentPlacer a partire dalla prima pagina
 *   occupata (in ordine di sfoglio): 2 pagine → 2 e 1/2 diventa 12, 12, 6
 *   sulle pagine consecutive; se non ci sta (numero finito, pagina
 *   successiva bloccata o piena) non cambia nulla e l'errore lo dice. Le
 *   regolazioni fatte a mano pagina per pagina vengono sostituite dalla
 *   nuova distribuzione di default — è proprio ciò che si chiede cambiando
 *   l'occupazione.
 * - occupazione invariata → le pagine restano come sono (anche le
 *   regolazioni manuali per pagina).
 * - PDF sostituito → i file vecchi vengono eliminati e le pagine
 *   ricevono la pagina del PDF nuovo che corrisponde alla loro posizione.
 *
 * Non parla con l'utente (niente broadcast/addError): errori come
 * ValidationException, che il componente mostra nel modulo — stesso
 * principio di ContentCreator e ContentPlacer.
 */
final class ContentUpdater
{
    /**
     * @param  array<string, mixed>  $data  campi già validati (ContentFormRules)
     *
     * @throws ValidationException
     */
    public static function update(
        Content $content,
        array $data,
        ?UploadedFile $file = null,
        ?int $userId = null,
    ): void {
        $content->loadMissing(['article', 'advertisement', 'files']);

        $occupancy = ContentCreator::occupancyFromFields($data);

        if ($occupancy === null) {
            throw ValidationException::withMessages(['occupancy' => 'Indica quanto spazio occupa: almeno una pagina intera o una frazione.']);
        }

        // Il PDF che resterà associato (nuovo o attuale) deve coincidere con
        // l'occupazione, come in creazione.
        $pdfPageCount = $file !== null ? ContentCreator::pdfPageCount($file) : self::currentPdfPageCount($content);
        ContentCreator::assertPdfFits($occupancy, $pdfPageCount, $file !== null ? 'file' : 'occupancy');

        $pages = self::assignedPagesInReadingOrder($content);
        $occupancyChanged = $occupancy->twelfths !== (int) $content->occupancy_twelfths;

        if (($occupancyChanged || $file !== null) && ($locked = $pages->first(fn (Page $page) => $page->isLocked())) !== null) {
            throw ValidationException::withMessages([
                'occupancy' => "La {$locked->label()} è bloccata: sbloccala prima di cambiare occupazione o PDF di questo contenuto (nessuna modifica salvata).",
            ]);
        }

        $before = self::snapshot($content);

        DB::transaction(function () use ($content, $data, $occupancy, $occupancyChanged, $pages) {
            self::updateFields($content, $data, $occupancy);

            if ($occupancyChanged && $pages->isNotEmpty()) {
                self::redistribute($content, $pages);
            }
        });

        // Fuori dalla transazione: i job delle anteprime partono subito e
        // devono trovare le righe già scritte.
        if ($file !== null) {
            self::replaceFiles($content, $file, $pdfPageCount, $userId);
        }

        $after = self::snapshot($content->fresh(['article', 'advertisement']));
        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

        if ($changed !== [] || $file !== null) {
            ActivityLogger::log(
                issue: $content->issue,
                entityType: 'Content',
                entityId: $content->id,
                action: 'content.updated',
                description: "Contenuto «{$content->fresh()->displayLabel()}» modificato".($file !== null ? ' (PDF sostituito)' : ''),
                old: array_intersect_key($before, array_flip($changed)),
                new: array_intersect_key($after, array_flip($changed)),
            );
        }
    }

    /**
     * Pagine occupate in ordine di sfoglio: copertine nel loro ordine,
     * poi le interne per posizione. Il punto di partenza di una
     * ridistribuzione è il primo elemento (vedi redistribute()).
     *
     * @return Collection<int, Page>
     */
    public static function assignedPagesInReadingOrder(Content $content): Collection
    {
        return $content->pages()->get()
            ->sortBy(fn (Page $page) => $page->isCover() ? [0, $page->cover_slot->order()] : [1, $page->position])
            ->values();
    }

    private static function updateFields(Content $content, array $data, PageOccupancy $occupancy): void
    {
        $content->update([
            'type' => $data['type'],
            'title' => $data['title'],
            'section_id' => $data['section_id'] ?? null,
            'occupancy_twelfths' => $occupancy->twelfths,
        ]);

        // Cambio di tipo: la riga dell'altro tipo sparisce (i suoi campi
        // non hanno senso sul nuovo tipo), quella del nuovo tipo nasce.
        if ($data['type'] === ContentType::Articolo->value) {
            $content->advertisement?->delete();
            Article::updateOrCreate(['content_id' => $content->id], [
                'author' => $data['author'] ?? null,
                'editorial_status' => $data['editorial_status'],
                'expected_length' => $data['expected_length'] ?? null,
            ]);
        } else {
            $content->article?->delete();
            Advertisement::updateOrCreate(['content_id' => $content->id], [
                'client' => $data['client'],
                'agency' => $data['agency'] ?? null,
                'format' => $data['format'],
                'preferred_position' => $data['preferred_position'] ?? null,
                'confirmation_status' => $data['confirmation_status'],
                'commercial_notes' => $data['commercial_notes'] ?? null,
            ]);
        }

        $content->unsetRelation('article')->unsetRelation('advertisement');

        // Il tipo della pagina (editoriale/pubblicità/mista) dipende dal tipo
        // dei contenuti: va riallineato su ogni pagina occupata.
        foreach ($content->pages()->get() as $page) {
            ContentPageAttacher::syncPageContentType($page);
        }
    }

    /**
     * Toglie il contenuto dalle pagine attuali e lo ri-posiziona dalla
     * prima, con la nuova distribuzione (ContentPlacer, tutto o niente).
     * Un gruppo collegato formato esattamente dalle sue pagine (quello
     * creato in automatico al primo posizionamento) viene sciolto: con un
     * numero di pagine diverso non corrisponderebbe più al contenuto.
     *
     * @param  Collection<int, Page>  $pages
     *
     * @throws ValidationException
     */
    private static function redistribute(Content $content, Collection $pages): void
    {
        $start = $pages->first();
        $groupIds = $pages->pluck('page_group_id')->filter()->unique();

        if ($groupIds->count() === 1) {
            $groupPageIds = Page::where('page_group_id', $groupIds->first())->pluck('id')->sort()->values()->all();

            if ($groupPageIds === $pages->pluck('id')->sort()->values()->all()) {
                PageGroupLinker::unlink($groupIds->first());
            }
        }

        $content->pages()->detach();

        foreach ($pages as $page) {
            ContentPageAttacher::syncPageContentType($page);
        }

        $result = ContentPlacer::place($content->fresh(['advertisement']), $start->fresh());

        if (is_string($result)) {
            throw ValidationException::withMessages([
                'occupancy' => 'Nuova occupazione non applicabile alle pagine: '.$result,
            ]);
        }
    }

    /**
     * Sostituisce il materiale: elimina file e righe vecchi, salva il nuovo
     * (una riga per pagina del PDF) e riassegna a ogni pagina occupata la
     * pagina del PDF corrispondente alla sua posizione nel contenuto.
     */
    private static function replaceFiles(Content $content, UploadedFile $file, ?int $pdfPageCount, ?int $userId): void
    {
        foreach ($content->files()->get() as $old) {
            Storage::disk($old->disk)->delete(array_filter([$old->path, $old->thumbnail_path]));
            $old->delete();
        }

        ContentCreator::storeFiles($content->issue, $content, $file, range(1, $pdfPageCount ?? 1), $pdfPageCount ?? 1, $userId);

        $newFiles = ContentFile::where('content_id', $content->id)->orderBy('pdf_page_number')->get()->values();

        foreach (self::assignedPagesInReadingOrder($content)->values() as $index => $page) {
            $content->pages()->updateExistingPivot($page->id, [
                // Una sola pagina di materiale: la ricava Page::previewFile()
                // dal file più recente, come per un contenuto appena creato.
                'content_file_id' => $newFiles->count() > 1 ? $newFiles->get($index)?->id : null,
            ]);
        }
    }

    /** Pagine del PDF attualmente associato (null se nessun PDF). */
    private static function currentPdfPageCount(Content $content): ?int
    {
        $pdfFiles = $content->files->where('mime_type', 'application/pdf');

        return $pdfFiles->isEmpty() ? null : $pdfFiles->count();
    }

    /** @return array<string, mixed> campi confrontati per la cronologia */
    private static function snapshot(Content $content): array
    {
        return [
            'type' => $content->type->value,
            'title' => $content->title,
            'section_id' => $content->section_id,
            'occupancy_twelfths' => $content->occupancy_twelfths,
            'author' => $content->article?->author,
            'editorial_status' => $content->article?->editorial_status?->value,
            'expected_length' => $content->article?->expected_length,
            'client' => $content->advertisement?->client,
            'agency' => $content->advertisement?->agency,
            'format' => $content->advertisement?->format?->value,
            'preferred_position' => $content->advertisement?->preferred_position,
            'confirmation_status' => $content->advertisement?->confirmation_status?->value,
            'commercial_notes' => $content->advertisement?->commercial_notes,
        ];
    }
}
