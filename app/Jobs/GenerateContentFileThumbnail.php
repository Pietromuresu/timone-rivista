<?php

namespace App\Jobs;

use App\Enums\ThumbnailStatus;
use App\Events\ContentFileUploaded;
use App\Models\ContentFile;
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
 * un Content (Fase 4.1), non riusato direttamente: quel job è tipizzato su
 * PageFile e fa anche il controllo formato pubblicitario (§2.3), che ha
 * senso solo per un file legato a una pagina con una pubblicità assegnata
 * — nessun equivalente sensato per un file legato a un Content non ancora
 * su nessuna pagina. La generazione miniatura PDF vera e propria (stessa
 * libreria, Spatie\PdfToImage) è l'unica parte davvero comune, troppo
 * piccola da estrarre in una terza classe condivisa senza una complessità
 * sproporzionata al beneficio (CLAUDE.md, regola 3).
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

                (new Pdf($absolutePdfPath))
                    ->selectPage(1)
                    ->format(OutputFormat::Png)
                    ->resolution(150)
                    ->thumbnailSize(400)
                    ->save(Storage::disk('local')->path($thumbnailRelativePath));
            }

            $this->contentFile->update([
                'thumbnail_path' => $thumbnailRelativePath,
                'thumbnail_status' => ThumbnailStatus::Ready,
            ]);
        } catch (Throwable $e) {
            report($e);
            $this->contentFile->update(['thumbnail_status' => ThumbnailStatus::Failed]);
            // Nessun rilancio: stesso principio di GeneratePageFileThumbnail
            // — un file corrotto non diventa leggibile ritentando.
        }

        broadcast(new ContentFileUploaded(
            issueId: $this->contentFile->content->issue_id,
            contentId: $this->contentFile->content_id,
            contentFileId: $this->contentFile->id,
            thumbnailStatus: $this->contentFile->thumbnail_status->value,
        ));
    }
}
