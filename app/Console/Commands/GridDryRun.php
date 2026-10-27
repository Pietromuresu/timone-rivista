<?php

namespace App\Console\Commands;

use App\Support\PageGrid\GridConversion;
use App\Support\Twelfths;
use Illuminate\Console\Command;

/**
 * `php artisan timone:grid-dry-run` (revisione 2, Fase 2.3, 2026-10-27):
 * per ogni pagina con contenuti mostra dove ciascuno viene messo sulla
 * griglia 6×6 dalla migration 2026_10_27_600000_add_grid_cells_to_page_content_table,
 * SENZA scrivere nulla — stesso calcolo della migration
 * (App\Support\PageGrid\GridConversion). Funziona anche dopo la
 * migration: ricalcola dalla disposizione della Fase 7a.
 */
class GridDryRun extends Command
{
    protected $signature = 'timone:grid-dry-run {--issue= : Solo il numero con questo id}';

    protected $description = 'Mostra dove ogni contenuto viene messo sulla griglia 6×6 della pagina (nessuna scrittura)';

    public function handle(): int
    {
        $plans = GridConversion::planAll($this->option('issue') ? (int) $this->option('issue') : null);
        $issueId = null;
        $counts = ['identica a prima' => 0, 'riposizionata (primo posto libero)' => 0, 'a L (riempie lo spazio libero)' => 0, 'NON ENTRA (pagina oltre capienza)' => 0];

        foreach ($plans as $plan) {
            $page = $plan['page'];

            if ($page->issue_id !== $issueId) {
                $issueId = $page->issue_id;
                $this->newLine();
                $this->line("<options=bold>#{$page->issue->id} «{$page->issue->title}»</>");
            }

            $this->line('  '.ucfirst($page->label()).':');

            foreach ($plan['rows'] as $row) {
                $counts[$row['how']]++;
                $before = $row['before'] !== null
                    ? sprintf('prima x %s%% y %s%% %s×%s%%', ...array_map(fn ($v) => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ','), [$row['before']['x'], $row['before']['y'], $row['before']['w'], $row['before']['h']]))
                    : 'prima: —';
                $after = $row['after']?->label() ?? implode(' + ', array_map(fn ($r) => $r->label(), $row['pieces']));
                $line = "    - «{$row['content']->displayLabel()}» ".Twelfths::fractionLabel($row['twelfths'])." ({$row['twelfths']}/12): {$before} → <options=bold>{$after}</> [{$row['how']}]";
                $row['kind'] === 'overflow' ? $this->warn($line) : $this->line($line);
            }
        }

        $this->newLine();
        foreach ($counts as $how => $count) {
            $this->line("{$how}: {$count}");
        }
        $this->info('Dry-run: nessuna modifica scritta. La conversione avviene con `php artisan migrate`.');

        return self::SUCCESS;
    }
}
