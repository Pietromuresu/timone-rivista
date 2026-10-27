<?php

namespace App\Jobs;

use App\Enums\ContentType;
use App\Enums\FormatCheckStatus;
use App\Enums\ThumbnailStatus;
use App\Events\ContentFileUploaded;
use App\Models\ContentFile;
use App\Support\PdfFormatChecker;
use App\Support\ContentFileAspectRatio;
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

/**
 * Analogo di GeneratePageFileThumbnail (Punto 1bis) per i file allegati a
 * un Content (Fase 4.1). Fino al Punto 4 del prompt (2026-09-02) generava
 * SEMPRE e solo la pagina 1 del PDF, senza controllo formato — un gap
 * reale rispetto a PageFile, non solo una scelta pragmatica: un contenuto
 * è ora l'UNICO oggetto a cui si può allegare un PDF (il caricamento
 * diretto su pagina è stato rimosso, vedi App\Livewire\Timone\
 * ContentCreate), quindi deve riprodurre esattamente lo stesso
 * comportamento multi-pagina/controllo formato di prima — non più "nessun
 * equivalente sensato", vedi ContentFile::relevantPage() per come si
 * risolve la pagina di riferimento ora che un contenuto può essere già
 * assegnato al momento della generazione della miniatura.
 */
class GenerateContentFileThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ContentFile $contentFile) {}

    public function handle(): void
    {
        $this->contentFile->update(['thumbnail_status' => ThumbnailStatus::Processing]);

        try {
            $thumbnailDir = 'contents/'.$this->contentFile->content_id.'/thumbnails';
            Storage::disk('local')->makeDirectory($thumbnailDir);

            if ($this->contentFile->isImage()) {
                // Un'immagine è già la propria anteprima: nessuna
                // conversione necessaria (a differenza di un PDF, che va
                // rasterizzato per ottenere un'anteprima) — basta copiarla
                // nella cartella miniature, cosi ContentFileController può
                // servire thumbnail_path con lo stesso meccanismo per
                // entrambi i tipi di file, senza un caso speciale a valle.
                $extension = pathinfo($this->contentFile->path, PATHINFO_EXTENSION);
                $thumbnailRelativePath = $thumbnailDir.'/'.pathinfo($this->contentFile->path, PATHINFO_FILENAME).'.'.$extension;
                Storage::disk('local')->copy($this->contentFile->path, $thumbnailRelativePath);
            } else {
                $absolutePdfPath = Storage::disk($this->contentFile->disk)->path($this->contentFile->path);
                $thumbnailRelativePath = $thumbnailDir.'/'.pathinfo($this->contentFile->path, PATHINFO_FILENAME).'.png';

                // pdf_page_number (default 1): quale pagina INTERNA del PDF
                // sorgente rendere in miniatura — un contenuto con un PDF
                // multi-pagina riceve un ContentFile per pagina sorgente
                // (vedi ContentCreate), ciascuno con il proprio
                // pdf_page_number, stesso principio già in uso per PageFile.
                (new Pdf($absolutePdfPath))
                    ->selectPage($this->contentFile->pdf_page_number)
                    ->format(OutputFormat::Png)
                    ->resolution(150)
                    ->thumbnailSize(400)
                    ->save(Storage::disk('local')->path($thumbnailRelativePath));
            }

            $this->contentFile->update([
                'thumbnail_path' => $thumbnailRelativePath,
                'thumbnail_status' => ThumbnailStatus::Ready,
            ]);
            // Revisione 2, Fase 2.4: proporzioni della pagina, misurate una
            // volta qui, per decidere come ritagliare l'anteprima nel suo
            // rettangolo (App\Support\PageGrid\ThumbnailFit).
            $this->contentFile->update(['aspect_ratio' => ContentFileAspectRatio::measure($this->contentFile)]);
        } catch (Throwable $e) {
            report($e);
            $this->contentFile->update(['thumbnail_status' => ThumbnailStatus::Failed]);
            // Nessun rilancio: stesso principio di GeneratePageFileThumbnail
            // — un file corrotto non diventa leggibile ritentando.
        }

        // Controllo formato (§2.3), indipendente dall'esito della miniatura
        // sopra — stesso principio di GeneratePageFileThumbnail. Solo per
        // un PDF: un'immagine non ha "pagine interne" da misurare via
        // Imagick/pdftoppm, e comunque un'immagine non è mai un'anteprima
        // pubblicitaria caricata a formato (il flusso multi-pagina/formato
        // riguarda sempre e solo PDF).
        if (! $this->contentFile->isImage()) {
            $this->checkFormat($absolutePdfPath ?? null);
        }

        broadcast(new ContentFileUploaded(
            issueId: $this->contentFile->content->issue_id,
            contentId: $this->contentFile->content_id,
            contentFileId: $this->contentFile->id,
            thumbnailStatus: $this->contentFile->thumbnail_status->value,
        ));
        $this->contentFile->content->issue->markPagesUpdated();
    }

    /**
     * Stesso identico principio di GeneratePageFileThumbnail::checkFormat()
     * — vedi lì per il ragionamento completo. Unica differenza: la pagina
     * di riferimento non è più un rapporto 1:1 implicito (un PageFile
     * apparteneva sempre a una sola pagina), va risolta esplicitamente
     * tramite ContentFile::relevantPage() — che torna null se il
     * contenuto non è (ancora) assegnato a nessuna pagina, nel qual caso
     * il controllo resta "non applicabile" finché non lo sarà (nessun
     * ri-controllo automatico al momento dell'assegnazione: il contenuto
     * comune, con questo flusso ancorato a una pagina fin dalla creazione,
     * ha già una pagina nota qui — scelta pragmatica per il caso raro di
     * un contenuto creato "libero" e assegnato solo in seguito, CLAUDE.md
     * regola 3, non richiesta esplicitamente dal prompt).
     */
    private function checkFormat(?string $absolutePdfPath): void
    {
        try {
            if ($absolutePdfPath === null) {
                $this->contentFile->update(['format_check_status' => FormatCheckStatus::Unverifiable]);

                return;
            }

            $page = $this->contentFile->relevantPage();
            $page?->load('contents.advertisement');
            $adContents = $page?->contents->where('type', ContentType::Pubblicita) ?? collect();

            if ($adContents->count() !== 1) {
                $this->contentFile->update(['format_check_status' => FormatCheckStatus::NotApplicable]);

                return;
            }

            $nominal = $adContents->first()->advertisement?->format?->dimensionsMm();

            if ($nominal === null) {
                $this->contentFile->update(['format_check_status' => FormatCheckStatus::NotApplicable]);

                return;
            }

            $measured = PdfPageMeasurer::pageSizeMm($absolutePdfPath, $this->contentFile->pdf_page_number);

            if ($measured === null) {
                $this->contentFile->update(['format_check_status' => FormatCheckStatus::Unverifiable]);

                return;
            }

            $matches = PdfFormatChecker::matches($nominal, $measured, (float) config('timone.pdf_format_tolerance_mm'));

            $this->contentFile->update([
                'format_check_status' => $matches ? FormatCheckStatus::Matching : FormatCheckStatus::Mismatch,
                'measured_width_mm' => $measured['width'],
                'measured_height_mm' => $measured['height'],
            ]);
        } catch (Throwable $e) {
            report($e);
            $this->contentFile->update(['format_check_status' => FormatCheckStatus::Unverifiable]);
        }
    }
}
