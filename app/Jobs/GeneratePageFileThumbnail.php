<?php

namespace App\Jobs;

use App\Enums\ContentType;
use App\Enums\FormatCheckStatus;
use App\Enums\ThumbnailStatus;
use App\Events\PageFileUploaded;
use App\Models\PageFile;
use App\Support\PdfFormatChecker;
use App\Support\PdfPageMeasurer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;
use Throwable;

class GeneratePageFileThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public PageFile $pageFile) {}

    public function handle(): void
    {
        $this->pageFile->update(['thumbnail_status' => ThumbnailStatus::Processing]);

        try {
            $absolutePdfPath = Storage::disk($this->pageFile->disk)->path($this->pageFile->path);

            $thumbnailDir = 'pages/'.$this->pageFile->page_id.'/thumbnails';
            $thumbnailRelativePath = $thumbnailDir.'/'.pathinfo($this->pageFile->path, PATHINFO_FILENAME).'.png';

            Storage::disk('local')->makeDirectory($thumbnailDir);

            // pdf_page_number (default 1): quale pagina INTERNA del PDF
            // sorgente rendere in miniatura — per un upload multipagina
            // (Fase 2, §2.1/§2.2) ogni pagina del timone coinvolta ha la
            // propria riga PageFile con un pdf_page_number diverso, tutte
            // puntano alla stessa copia completa del PDF (vedi
            // Grid::storeUploadedPdf()).
            (new Pdf($absolutePdfPath))
                ->selectPage($this->pageFile->pdf_page_number)
                ->format(OutputFormat::Png)
                ->resolution(150)
                ->thumbnailSize(400)
                ->save(Storage::disk('local')->path($thumbnailRelativePath));

            $this->pageFile->update([
                'thumbnail_path' => $thumbnailRelativePath,
                'thumbnail_status' => ThumbnailStatus::Ready,
            ]);
        } catch (Throwable $e) {
            report($e);
            $this->pageFile->update(['thumbnail_status' => ThumbnailStatus::Failed]);
            // Nessun rilancio: un PDF corrotto non diventa leggibile ritentando
            // (--tries=3 del worker). Il fallimento resta tracciato nel dominio
            // (thumbnail_status), non serve anche una entry in failed_jobs.
        }

        // Controllo formato (§2.3) indipendente dall'esito della miniatura
        // sopra: un PDF può essere leggibile per le dimensioni ma comunque
        // fallire la generazione della miniatura per altri motivi (o
        // viceversa) — nessuna delle due sorti dipende dall'altra.
        $this->checkFormat($absolutePdfPath ?? null);

        broadcast(new PageFileUploaded(
            issueId: $this->pageFile->page->issue_id,
            pageId: $this->pageFile->page_id,
            pageFileId: $this->pageFile->id,
            thumbnailStatus: $this->pageFile->thumbnail_status->value,
        ));
    }

    /**
     * Applicabile solo se la pagina ha esattamente una pubblicità assegnata
     * con un formato di misure note (App\Enums\AdFormat::dimensionsMm()) —
     * negli altri casi (nessuna pubblicità, più di una, formato senza
     * misure nel listino) non c'è un confronto sensato da fare, quindi
     * "non applicabile", non un problema da segnalare. Non deve MAI
     * lanciare un'eccezione non gestita (spec §2.3): ogni passaggio che
     * può fallire (path assente, PDF illeggibile) degrada a uno stato
     * controllato invece di propagare.
     */
    private function checkFormat(?string $absolutePdfPath): void
    {
        try {
            if ($absolutePdfPath === null) {
                $this->pageFile->update(['format_check_status' => FormatCheckStatus::Unverifiable]);

                return;
            }

            $page = $this->pageFile->page()->with('contents.advertisement')->first();
            $adContents = $page?->contents->where('type', ContentType::Pubblicita) ?? collect();

            if ($adContents->count() !== 1) {
                $this->pageFile->update(['format_check_status' => FormatCheckStatus::NotApplicable]);

                return;
            }

            $nominal = $adContents->first()->advertisement?->format?->dimensionsMm();

            if ($nominal === null) {
                $this->pageFile->update(['format_check_status' => FormatCheckStatus::NotApplicable]);

                return;
            }

            $measured = PdfPageMeasurer::pageSizeMm($absolutePdfPath, $this->pageFile->pdf_page_number);

            if ($measured === null) {
                $this->pageFile->update(['format_check_status' => FormatCheckStatus::Unverifiable]);

                return;
            }

            $matches = PdfFormatChecker::matches($nominal, $measured, (float) config('timone.pdf_format_tolerance_mm'));

            $this->pageFile->update([
                'format_check_status' => $matches ? FormatCheckStatus::Matching : FormatCheckStatus::Mismatch,
                'measured_width_mm' => $measured['width'],
                'measured_height_mm' => $measured['height'],
            ]);
        } catch (Throwable $e) {
            report($e);
            $this->pageFile->update(['format_check_status' => FormatCheckStatus::Unverifiable]);
        }
    }
}
