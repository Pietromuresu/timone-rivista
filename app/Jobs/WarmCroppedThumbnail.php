<?php

namespace App\Jobs;

use App\Models\ContentFile;
use App\Support\CroppedThumbnails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Prepara in coda il ritaglio di un pezzo per l'export del timone
 * (2026-10-30, CroppedThumbnails): l'export non deve rifarli tutti mentre
 * l'utente aspetta il PDF.
 */
class WarmCroppedThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param  array{0: float, 1: float, 2: float, 3: float}  $window */
    public function __construct(public ContentFile $contentFile, public array $window) {}

    public function handle(): void
    {
        CroppedThumbnails::ensure($this->contentFile, $this->window);
    }
}
