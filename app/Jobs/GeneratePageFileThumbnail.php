<?php

namespace App\Jobs;

use App\Enums\ThumbnailStatus;
use App\Events\PageFileUploaded;
use App\Models\PageFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;

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

            (new Pdf($absolutePdfPath))
                ->selectPage(1)
                ->format(OutputFormat::Png)
                ->resolution(150)
                ->thumbnailSize(400)
                ->save(Storage::disk('local')->path($thumbnailRelativePath));

            $this->pageFile->update([
                'thumbnail_path' => $thumbnailRelativePath,
                'thumbnail_status' => ThumbnailStatus::Ready,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->pageFile->update(['thumbnail_status' => ThumbnailStatus::Failed]);
            // Nessun rilancio: un PDF corrotto non diventa leggibile ritentando
            // (--tries=3 del worker). Il fallimento resta tracciato nel dominio
            // (thumbnail_status), non serve anche una entry in failed_jobs.
        }

        broadcast(new PageFileUploaded(
            issueId: $this->pageFile->page->issue_id,
            pageId: $this->pageFile->page_id,
            pageFileId: $this->pageFile->id,
            thumbnailStatus: $this->pageFile->thumbnail_status->value,
        ));
    }
}
