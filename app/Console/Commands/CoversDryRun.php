<?php

namespace App\Console\Commands;

use App\Enums\CoverSlot;
use App\Support\Covers\CoverConversion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan timone:covers-dry-run` (Fase 2, 2026-10-26): mostra, numero
 * per numero, cosa farà la migration
 * 2026_10_26_200002_convert_cover_pages_to_slots, SENZA scrivere nulla.
 * Va lanciato prima di `php artisan migrate` (funziona anche sullo schema
 * vecchio). Stesso pianificatore della migration
 * (App\Support\Covers\CoverConversionPlanner): ciò che si legge qui è ciò
 * che verrà applicato.
 */
class CoversDryRun extends Command
{
    protected $signature = 'timone:covers-dry-run {--issue= : Solo il numero con questo id}';

    protected $description = 'Mostra cosa diventa cosa nella conversione alle copertine separate (nessuna scrittura)';

    public function handle(): int
    {
        $plans = collect(CoverConversion::planAll())
            ->when($this->option('issue'), fn ($c, $id) => $c->filter(fn ($row) => (int) $row['issue']->id === (int) $id));

        if ($plans->isEmpty()) {
            $this->info('Nessun numero da convertire.');

            return self::SUCCESS;
        }

        $magazines = DB::table('magazines')->pluck('name', 'id');

        foreach ($plans as ['issue' => $issue, 'plan' => $plan]) {
            $this->newLine();
            $this->line("<options=bold>#{$issue->id} «{$issue->title}»</> ({$magazines[$issue->magazine_id]})");

            if ($plan['already_converted']) {
                $this->line('   già convertito: nessuna modifica');

                continue;
            }

            $this->line("   pagine totali: {$issue->total_pages} → <options=bold>{$plan['new_total_pages']} interne</> (numerate da 1) + copertine");
            $this->line('   pagine copertina (peso): '.($issue->pagine_copertina ?? '—')." → {$plan['new_pagine_copertina']}");

            foreach (collect(CoverSlot::cases())->sortBy(fn (CoverSlot $s) => $s->order()) as $slot) {
                if (isset($plan['covers'][$slot->value])) {
                    $cover = $plan['covers'][$slot->value];
                    $contents = $cover['contents_count'] > 0 ? ", {$cover['contents_count']} contenuto/i restano assegnati" : ', vuota';
                    $this->line("   {$slot->label()}: ← pagina in posizione {$cover['old_position']}{$contents}");
                } elseif (in_array($slot->value, $plan['created_slots'], true)) {
                    $this->line("   {$slot->label()}: creata vuota");
                }
            }

            $this->line('   rinumerazione interne: '.self::describeRenumbering($plan['renumbered']));

            if ($plan['ungrouped'] !== []) {
                $this->line('   gruppi collegati sciolti (resterebbero con una sola pagina): pagine '.implode(', ', array_map(
                    fn (int $pageId) => $plan['renumbered'][$pageId]['new_position'],
                    array_keys($plan['ungrouped']),
                )));
            }

            foreach ($plan['warnings'] as $warning) {
                $this->warn("   ⚠️  {$warning}");
            }
        }

        $this->newLine();
        $this->info('Dry-run: nessuna modifica scritta. La conversione avviene con `php artisan migrate`.');

        return self::SUCCESS;
    }

    /**
     * "2–31 → 1–30" invece di 30 righe: raggruppa le pagine consecutive
     * che scalano dello stesso numero di posti.
     *
     * @param  array<int, array{old_position: int, new_position: int}>  $renumbered
     */
    public static function describeRenumbering(array $renumbered): string
    {
        if ($renumbered === []) {
            return 'nessuna pagina interna';
        }

        $runs = [];

        foreach (collect($renumbered)->sortBy('old_position') as $move) {
            $i = count($runs) - 1;

            if ($i >= 0 && $move['old_position'] === $runs[$i]['old_to'] + 1 && $move['new_position'] === $runs[$i]['new_to'] + 1) {
                $runs[$i]['old_to'] = $move['old_position'];
                $runs[$i]['new_to'] = $move['new_position'];
            } else {
                $runs[] = ['old_from' => $move['old_position'], 'old_to' => $move['old_position'], 'new_from' => $move['new_position'], 'new_to' => $move['new_position']];
            }
        }

        return collect($runs)->map(function (array $run) {
            $old = $run['old_from'] === $run['old_to'] ? $run['old_from'] : "{$run['old_from']}–{$run['old_to']}";
            $new = $run['new_from'] === $run['new_to'] ? $run['new_from'] : "{$run['new_from']}–{$run['new_to']}";

            return $old === $new ? "{$old} invariate" : "{$old} → {$new}";
        })->implode(', ');
    }
}
