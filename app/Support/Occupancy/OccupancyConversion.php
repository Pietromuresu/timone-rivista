<?php

namespace App\Support\Occupancy;

use App\Enums\CoverSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applica (e annulla) la conversione percentuali → dodicesimi della Fase 3
 * (2026-10-27) con il piano di OccupancyConversionPlanner. Query builder e
 * non modelli Eloquent, come App\Support\Covers\CoverConversion: la
 * migration deve funzionare anche quando i modelli cambieranno.
 *
 * Nessun registro a parte: le colonne vecchie (`contents.page_occupancy`,
 * `page_content.occupied_percentage`, `advertisements.occupied_percentage_override`)
 * restano con i loro valori, il codice smette solo di leggerle. Il
 * rollback quindi ritrova i dati originali intatti e deve solo riempire le
 * righe nate dopo la conversione (revertAll()).
 */
final class OccupancyConversion
{
    /**
     * Piano per tutti i numeri (o uno solo), senza scrivere nulla. Funziona
     * prima e dopo la migration: legge solo colonne vecchie.
     *
     * @return list<array{issue: object, plan: array}>
     */
    public static function planAll(?int $issueId = null): array
    {
        return DB::table('issues')
            ->when($issueId, fn ($q) => $q->where('id', $issueId))
            ->orderBy('id')
            ->get()
            ->map(fn (object $issue) => [
                'issue' => $issue,
                'plan' => OccupancyConversionPlanner::plan(self::contentsOf($issue->id), self::pivotsOf($issue->id)),
            ])
            ->all();
    }

    public static function applyAll(): void
    {
        foreach (self::planAll() as ['plan' => $plan]) {
            DB::transaction(function () use ($plan) {
                foreach ($plan['contents'] as $content) {
                    DB::table('contents')->where('id', $content['id'])->update(['occupancy_twelfths' => $content['new']]);
                }

                foreach ($plan['pivots'] as $pivot) {
                    DB::table('page_content')->where('id', $pivot['id'])->update(['occupied_twelfths' => $pivot['new']]);
                }
            });
        }
    }

    /**
     * Prima del rollback dello schema: le righe create dopo la conversione
     * non hanno i valori vecchi (le colonne sono diventate nullable) — li
     * ricava dai dodicesimi, così le colonne possono tornare NOT NULL.
     */
    public static function revertAll(): void
    {
        DB::table('contents')->whereNull('page_occupancy')->orderBy('id')->each(function (object $content) {
            DB::table('contents')->where('id', $content->id)->update([
                'page_occupancy' => OccupancyConversionPlanner::legacyOccupancy((int) $content->occupancy_twelfths),
            ]);
        });

        DB::table('page_content')->whereNull('occupied_percentage')->orderBy('id')->each(function (object $pivot) {
            DB::table('page_content')->where('id', $pivot->id)->update([
                'occupied_percentage' => OccupancyConversionPlanner::legacyPercentage((int) $pivot->occupied_twelfths),
            ]);
        });
    }

    private static function contentsOf(int $issueId): array
    {
        return DB::table('contents')
            ->leftJoin('advertisements', 'advertisements.content_id', '=', 'contents.id')
            ->where('contents.issue_id', $issueId)
            ->orderBy('contents.id')
            ->get(['contents.id', 'contents.title', 'contents.type', 'contents.page_occupancy', 'advertisements.format', 'advertisements.occupied_percentage_override'])
            ->all();
    }

    private static function pivotsOf(int $issueId): array
    {
        $hasCoverSlot = Schema::hasColumn('pages', 'cover_slot');

        return DB::table('page_content')
            ->join('pages', 'pages.id', '=', 'page_content.page_id')
            ->where('pages.issue_id', $issueId)
            ->orderByRaw('pages.position is null, pages.position')
            ->orderBy('page_content.id')
            ->get(array_merge(
                ['page_content.id', 'page_content.page_id', 'page_content.content_id', 'page_content.occupied_percentage', 'pages.position'],
                $hasCoverSlot ? ['pages.cover_slot'] : [],
            ))
            ->each(function (object $row) {
                $slot = isset($row->cover_slot) ? CoverSlot::tryFrom($row->cover_slot) : null;
                $row->page_label = $slot !== null ? $slot->label() : "pagina {$row->position}";
            })
            ->all();
    }
}
