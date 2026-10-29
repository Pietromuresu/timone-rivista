<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Models\TimelineAction;
use App\Support\Timeline\TimelineActionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * `php artisan timone:history-prune [--dry-run]` (annulla/ripeti,
 * 2026-10-28), programmato ogni ora (routes/console.php):
 * - toglie i passi di cronologia più vecchi di 24 ore e, per sicurezza,
 *   quelli oltre i 50 per utente e numero (di norma li toglie già il servizio);
 * - elimina davvero i contenuti nel cestino da più di 24 ore (non più
 *   annullabili), con le loro righe figlie (cascate) e i file su disco.
 */
class PruneTimelineHistory extends Command
{
    protected $signature = 'timone:history-prune {--dry-run : Mostra cosa toglierebbe senza farlo}';

    protected $description = 'Pulisce la cronologia annulla/ripeti e i contenuti eliminati da più di 24 ore';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = now()->subHours(TimelineActionService::RETENTION_HOURS);

        $old = TimelineAction::where('created_at', '<', $limit);
        $oldCount = $old->count();

        $excess = TimelineAction::query()
            ->select('id', 'issue_id', 'user_id', 'sequence')
            ->orderByDesc('sequence')
            ->get()
            ->groupBy(fn ($step) => $step->issue_id.'-'.$step->user_id)
            ->flatMap(fn ($steps) => $steps->slice(TimelineActionService::MAX_STEPS)->pluck('id'));

        $trashed = Content::onlyTrashed()->where('deleted_at', '<', $limit)->with('files')->get();
        $files = $trashed->flatMap(fn (Content $content) => $content->files->flatMap(fn ($file) => array_filter([
            ['disk' => $file->disk, 'path' => $file->path],
            $file->thumbnail_path ? ['disk' => $file->disk, 'path' => $file->thumbnail_path] : null,
        ])));

        if (! $dryRun) {
            $old->delete();
            TimelineAction::whereIn('id', $excess)->delete();

            foreach ($trashed as $content) {
                $content->forceDelete();
            }

            foreach ($files->groupBy('disk') as $disk => $paths) {
                Storage::disk($disk)->delete($paths->pluck('path')->all());
            }
        }

        $this->info(($dryRun ? 'Dry-run: ' : '')."{$oldCount} passi oltre 24 ore, {$excess->count()} oltre il limite di ".TimelineActionService::MAX_STEPS.", {$trashed->count()} contenuti eliminati da più di 24 ore ({$files->count()} file).");

        return self::SUCCESS;
    }
}
