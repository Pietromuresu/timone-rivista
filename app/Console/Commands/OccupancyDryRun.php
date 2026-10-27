<?php

namespace App\Console\Commands;

use App\Support\Occupancy\OccupancyConversion;
use App\Support\Twelfths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan timone:occupancy-dry-run` (Fase 3, 2026-10-27): mostra,
 * numero per numero, cosa farà la migration
 * 2026_10_27_100000_convert_occupancy_to_twelfths, SENZA scrivere nulla —
 * stesso pianificatore della migration
 * (App\Support\Occupancy\OccupancyConversionPlanner). Funziona prima e
 * dopo la migration (legge solo le colonne vecchie, che restano).
 */
class OccupancyDryRun extends Command
{
    protected $signature = 'timone:occupancy-dry-run {--issue= : Solo il numero con questo id}';

    protected $description = 'Mostra la conversione delle occupazioni da percentuali a dodicesimi di pagina (nessuna scrittura)';

    public function handle(): int
    {
        $plans = OccupancyConversion::planAll($this->option('issue') ? (int) $this->option('issue') : null);
        $magazines = DB::table('magazines')->pluck('name', 'id');
        $titles = [];

        foreach ($plans as ['issue' => $issue, 'plan' => $plan]) {
            $this->newLine();
            $this->line("<options=bold>#{$issue->id} «{$issue->title}»</> ({$magazines[$issue->magazine_id]})");

            if ($plan['contents'] === []) {
                $this->line('   nessun contenuto');

                continue;
            }

            $this->line('   Contenuti (occupazione totale):');

            foreach ($plan['contents'] as $content) {
                $titles[$content['id']] = $content['title'];
                $this->line("   - [{$content['id']}] {$content['type']} «{$content['title']}»: {$content['old']} → <options=bold>".Twelfths::pagesLabel($content['new'])."</> ({$content['new']}/12)");
            }

            if ($plan['pivots'] !== []) {
                $this->line('   Assegnazioni (spazio su ciascuna pagina):');

                foreach ($plan['pivots'] as $pivot) {
                    $title = $titles[$pivot['content_id']] ?? "contenuto {$pivot['content_id']}";
                    $exact = abs($pivot['old'] - $pivot['new'] * 100 / Twelfths::PER_PAGE) < 0.01 ? '' : ' (arrotondato)';
                    $this->line("   - {$pivot['page_label']}, «{$title}»: {$pivot['old']}% → <options=bold>".Twelfths::pagesLabel($pivot['new'])."</> ({$pivot['new']}/12){$exact}");
                }
            }

            foreach ($plan['overfull_pages'] as $page) {
                $this->warn("   ⚠️  {$page['page_label']}: dopo l'arrotondamento occupa {$page['total']}/12, oltre la capienza — da sistemare a mano.");
            }
        }

        $this->newLine();
        $this->info('Dry-run: nessuna modifica scritta. La conversione avviene con `php artisan migrate`.');

        return self::SUCCESS;
    }
}
