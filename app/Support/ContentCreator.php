<?php

namespace App\Support;

use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\ThumbnailStatus;
use App\Jobs\GenerateContentFileThumbnail;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Unico punto di creazione di un contenuto (bug 1, 2026-10-12). Prima i due
 * percorsi di ContentCreate ("📎 Carica materiale" ancorato a una pagina e
 * "+ Nuovo contenuto" libero) divergevano proprio sul PDF: solo quello
 * ancorato misurava il numero di pagine, quello libero salvava un solo
 * ContentFile (pagina 1) e perdeva per sempre l'informazione — al drop il
 * contenuto finiva su una sola pagina. Qui il PDF viene SEMPRE misurato e
 * salvato come un ContentFile per pagina sorgente (ognuno con la propria
 * miniatura), indipendentemente da dove e quando verrà posizionato:
 * posizionamento (App\Support\ContentPlacer) e creazione restano due
 * responsabilità separate.
 *
 * Statica come le altre classi App\Support del progetto: dipende solo da
 * PdfPageMeasurer (anch'essa statica, I/O su Imagick) e dal database —
 * un'interfaccia iniettabile per un'unica implementazione sarebbe
 * complessità senza un consumatore alternativo (CLAUDE.md, regola 3).
 *
 * I vincoli di dominio sono verificati QUI (secondo livello del vincolo
 * "occupazione obbligatoria", bug 5) e non solo nella validazione
 * Livewire: un ValidationException lanciato da un'azione Livewire viene
 * mostrato nel form come un normale errore di validazione, quindi
 * l'utente vede lo stesso messaggio a prescindere da quale livello lo
 * intercetta.
 */
final class ContentCreator
{
    /**
     * Pagine di un PDF caricato, o null se non è un PDF. Lancia se il PDF
     * non è leggibile: trattarlo come "1 pagina" (vecchio `?? 1`) era
     * esattamente il fallback silenzioso che produceva distribuzioni
     * sbagliate.
     *
     * @throws ValidationException
     */
    public static function pdfPageCount(UploadedFile $file): ?int
    {
        if (! self::isPdf($file)) {
            return null;
        }

        $count = PdfPageMeasurer::pageCount($file->getRealPath());

        if ($count === null || $count < 1) {
            throw ValidationException::withMessages([
                'file' => 'Impossibile leggere il numero di pagine di questo PDF (file danneggiato o protetto?): non è stato salvato nulla, riprova con un altro file.',
            ]);
        }

        return $count;
    }

    public static function isPdf(UploadedFile $file): bool
    {
        return $file->getMimeType() === 'application/pdf';
    }

    /**
     * Occupazione effettiva del contenuto: scelta dall'utente per un
     * articolo, derivata dal formato per la pubblicità (vedi
     * PageOccupancy::forAdFormat()). Verifica anche la coerenza con il PDF.
     *
     * @param  array<string, mixed>  $data  campi già validati dal form
     *
     * @throws ValidationException
     */
    public static function resolveOccupancy(array $data, ?int $pdfPageCount): PageOccupancy
    {
        if ($data['type'] === ContentType::Pubblicita->value) {
            $format = AdFormat::tryFrom((string) ($data['format'] ?? ''));

            if ($format === null) {
                throw ValidationException::withMessages(['format' => 'Il formato della pubblicità è obbligatorio: determina quanta pagina occupa.']);
            }

            $occupancy = PageOccupancy::forAdFormat($format);

            if ($pdfPageCount !== null && $pdfPageCount > $occupancy->pagesSpanned()) {
                throw ValidationException::withMessages([
                    'file' => "Il PDF ha {$pdfPageCount} pagine, ma il formato «{$format->label()}» ne occupa ".$occupancy->pagesSpanned().': controlla il file o il formato.',
                ]);
            }

            return $occupancy;
        }

        $occupancy = PageOccupancy::tryFrom($data['page_occupancy'] ?? null);

        if ($occupancy === null) {
            throw ValidationException::withMessages(['page_occupancy' => "L'occupazione di pagina è obbligatoria."]);
        }

        if ($pdfPageCount !== null && $pdfPageCount > 1 && $occupancy->pagesSpanned() !== $pdfPageCount) {
            throw ValidationException::withMessages([
                'page_occupancy' => "Il PDF allegato ha {$pdfPageCount} pagine: l'occupazione deve essere di {$pdfPageCount} pagine.",
            ]);
        }

        if ($pdfPageCount === 1 && $occupancy->pagesSpanned() > 1) {
            throw ValidationException::withMessages([
                'page_occupancy' => "Il PDF allegato ha una sola pagina: scegli un'occupazione di al massimo 1 pagina.",
            ]);
        }

        return $occupancy;
    }

    /**
     * Crea Content + Article/Advertisement e, se c'è un file, un
     * ContentFile per ogni pagina sorgente del PDF (o uno solo per
     * un'immagine), accodando una miniatura per ciascuno.
     *
     * @param  array<string, mixed>  $data  campi già validati dal form
     * @param  int|null  $pdfPageCount  se già misurato dal chiamante (evita una seconda lettura del PDF)
     *
     * @throws ValidationException
     */
    public static function create(Issue $issue, array $data, ?UploadedFile $file, ?int $userId, ?int $pdfPageCount = null): Content
    {
        if ($file !== null && $pdfPageCount === null) {
            $pdfPageCount = self::pdfPageCount($file);
        }

        $occupancy = self::resolveOccupancy($data, $pdfPageCount);

        $content = DB::transaction(function () use ($issue, $data, $occupancy) {
            $content = Content::create([
                'issue_id' => $issue->id,
                'section_id' => $data['section_id'] ?? null,
                'type' => $data['type'],
                'title' => $data['title'],
                'page_occupancy' => $occupancy->value,
            ]);

            if ($data['type'] === ContentType::Articolo->value) {
                Article::create([
                    'content_id' => $content->id,
                    'author' => $data['author'] ?? null,
                    'editorial_status' => $data['editorial_status'],
                    'expected_length' => $data['expected_length'] ?? null,
                ]);
            } else {
                Advertisement::create([
                    'content_id' => $content->id,
                    'client' => $data['client'],
                    'agency' => $data['agency'] ?? null,
                    'format' => $data['format'],
                    'preferred_position' => $data['preferred_position'] ?? null,
                    'occupied_percentage_override' => $data['occupied_percentage_override'] ?? null,
                    'confirmation_status' => $data['confirmation_status'],
                    'commercial_notes' => $data['commercial_notes'] ?? null,
                ]);
            }

            return $content;
        });

        ActivityLogger::log(
            issue: $issue,
            entityType: 'Content',
            entityId: $content->id,
            action: 'content.created',
            description: "Contenuto «{$content->title}» creato (".($data['type'] === 'articolo' ? 'articolo' : 'pubblicità').', '.$occupancy->label().')',
            new: ['type' => $data['type'], 'title' => $content->title, 'page_occupancy' => $occupancy->value],
        );

        if ($file !== null) {
            foreach (range(1, $pdfPageCount ?? 1) as $pdfPageNumber) {
                self::storeFile($issue, $content, $file, $pdfPageNumber, $pdfPageCount ?? 1, $userId);
            }
        }

        return $content;
    }

    /**
     * Ogni pagina sorgente riceve una copia indipendente del file fisico —
     * stesso principio già documentato per PageFile/ContentFile (più
     * semplice di un riferimento condiviso tra righe).
     */
    private static function storeFile(Issue $issue, Content $content, UploadedFile $file, int $pdfPageNumber, int $totalPdfPages, ?int $userId): ContentFile
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $storedPath = $file->storeAs("contents/{$content->id}", Str::uuid().'.'.$extension, 'local');

        $contentFile = ContentFile::create([
            'content_id' => $content->id,
            'uploaded_by' => $userId,
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'thumbnail_status' => ThumbnailStatus::Pending,
            'pdf_page_number' => $pdfPageNumber,
        ]);

        ActivityLogger::log(
            issue: $issue,
            entityType: 'ContentFile',
            entityId: $contentFile->id,
            action: 'content.file_uploaded',
            description: $totalPdfPages > 1
                ? "PDF «{$contentFile->original_name}» (pagina {$pdfPageNumber} di {$totalPdfPages}) allegato al contenuto «{$content->title}»"
                : "File «{$contentFile->original_name}» allegato al contenuto «{$content->title}»",
            new: ['content_id' => $content->id, 'original_name' => $contentFile->original_name, 'pdf_page_number' => $pdfPageNumber],
        );

        GenerateContentFileThumbnail::dispatch($contentFile);

        return $contentFile;
    }
}
