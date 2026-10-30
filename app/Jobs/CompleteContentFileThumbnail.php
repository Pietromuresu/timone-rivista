<?php

namespace App\Jobs;

use App\Models\ContentFile;
use App\Support\ContentFileExtras;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Completa in coda un file caricato prima del ritaglio (2026-10-30):
 * misure della pagina e miniatura grande (ContentFileExtras). Lo accoda la
 * card la prima volta che il file serve ritagliato, così la griglia non
 * aspetta la rasterizzazione; al giro di polling successivo il ritaglio è
 * quello esatto.
 */
class CompleteContentFileThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ContentFile $contentFile) {}

    public function handle(): void
    {
        ContentFileExtras::complete($this->contentFile);
        $this->contentFile->content?->issue?->markPagesUpdated();
    }
}
