<?php

namespace App\Console\Commands;

use App\Enums\ThumbnailStatus;
use App\Models\ContentFile;
use App\Support\ContentFileAspectRatio;
use App\Support\ContentFileExtras;
use Illuminate\Console\Command;

/**
 * `php artisan timone:file-ratios [--dry-run]` (revisione 2, Fase 2.4,
 * 2026-10-27): misura le proporzioni delle pagine dei file caricati prima
 * della Fase 2.4 (content_files.aspect_ratio vuoto), dalle loro miniature.
 * Dal 2026-10-30 completa anche quello che serve al ritaglio
 * (ContentFileExtras: TrimBox e miniatura grande da 1200 px), che la card
 * altrimenti accoda da sola la prima volta che un file serve. Con
 * --dry-run stampa cosa scriverebbe, senza scrivere.
 */
class BackfillFileRatios extends Command
{
    protected $signature = 'timone:file-ratios {--dry-run : Mostra le misure senza salvarle}';

    protected $description = 'Misura proporzioni e TrimBox dei file già caricati e crea la miniatura grande, per il ritaglio delle anteprime';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $measured = 0;
        $missing = 0;

        ContentFile::query()
            ->where(fn ($query) => $query->whereNull('aspect_ratio')->orWhereNull('large_thumbnail_path')->orWhere(fn ($pdf) => $pdf->whereNull('trim_box')->where('mime_type', 'not like', 'image/%')))
            ->where('thumbnail_status', ThumbnailStatus::Ready)
            ->with('content')
            ->chunkById(200, function ($files) use ($dryRun, &$measured, &$missing) {
                foreach ($files as $file) {
                    $ratio = $file->aspect_ratio ?? ContentFileAspectRatio::measure($file);

                    if ($ratio === null) {
                        $missing++;
                        $this->warn("  [{$file->id}] {$file->original_name}: miniatura non leggibile, saltato");

                        continue;
                    }

                    $measured++;
                    $this->line("  [{$file->id}] «{$file->content?->displayLabel()}» {$file->original_name} p.{$file->pdf_page_number}: larghezza/altezza = {$ratio}");

                    if (! $dryRun) {
                        $file->update(['aspect_ratio' => $ratio]);
                        ContentFileExtras::complete($file->fresh());
                    }
                }
            });

        $this->newLine();
        $this->info(($dryRun ? 'Dry-run: da misurare ' : 'Misurati ')."{$measured} file, {$missing} senza miniatura leggibile.".($dryRun ? ' Nessuna modifica scritta.' : ''));

        return self::SUCCESS;
    }
}
