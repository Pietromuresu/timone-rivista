<?php

namespace App\Console\Commands;

use App\Enums\ThumbnailStatus;
use App\Models\ContentFile;
use App\Support\ContentFileAspectRatio;
use Illuminate\Console\Command;

/**
 * `php artisan timone:file-ratios [--dry-run]` (revisione 2, Fase 2.4,
 * 2026-10-27): misura le proporzioni delle pagine dei file caricati prima
 * della Fase 2.4 (content_files.aspect_ratio vuoto), dalle loro miniature.
 * Senza la misura l'anteprima resta intera, senza ritaglio: il comando
 * serve solo ad attivare il ritaglio sui file vecchi. Con --dry-run
 * stampa cosa scriverebbe, senza scrivere.
 */
class BackfillFileRatios extends Command
{
    protected $signature = 'timone:file-ratios {--dry-run : Mostra le misure senza salvarle}';

    protected $description = 'Misura le proporzioni dei file già caricati per il ritaglio delle anteprime (Fase 2.4)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $measured = 0;
        $missing = 0;

        ContentFile::query()
            ->whereNull('aspect_ratio')
            ->where('thumbnail_status', ThumbnailStatus::Ready)
            ->with('content')
            ->chunkById(200, function ($files) use ($dryRun, &$measured, &$missing) {
                foreach ($files as $file) {
                    $ratio = ContentFileAspectRatio::measure($file);

                    if ($ratio === null) {
                        $missing++;
                        $this->warn("  [{$file->id}] {$file->original_name}: miniatura non leggibile, saltato");

                        continue;
                    }

                    $measured++;
                    $this->line("  [{$file->id}] «{$file->content?->displayLabel()}» {$file->original_name} p.{$file->pdf_page_number}: larghezza/altezza = {$ratio}");

                    if (! $dryRun) {
                        $file->update(['aspect_ratio' => $ratio]);
                    }
                }
            });

        $this->newLine();
        $this->info(($dryRun ? 'Dry-run: da misurare ' : 'Misurati ')."{$measured} file, {$missing} senza miniatura leggibile.".($dryRun ? ' Nessuna modifica scritta.' : ''));

        return self::SUCCESS;
    }
}
