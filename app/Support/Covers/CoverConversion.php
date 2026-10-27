<?php

namespace App\Support\Covers;

use App\Enums\CoverSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applica (e annulla) la conversione dei numeri esistenti al modello
 * "copertine separate" (Fase 2, 2026-10-26), usando il piano di
 * CoverConversionPlanner. Lavora con il query builder e non con i modelli
 * Eloquent: una migration deve continuare a funzionare anche quando i
 * modelli cambieranno.
 *
 * Ogni modifica è scritta in `cover_conversion_logs` (stessa transazione
 * del numero), da cui revertAll() ripristina posizioni, ruoli, gruppi e
 * totali. Il rollback è pensato per l'immediato dopo-deploy: se nel
 * frattempo sono state aggiunte pagine interne, le posizioni ripristinate
 * possono collidere (unique(issue_id, position)) e il rollback si ferma su
 * quel numero con un errore del database, senza modifiche parziali.
 */
final class CoverConversion
{
    /**
     * Piano per tutti i numeri, senza scrivere nulla (dry-run). Funziona
     * anche PRIMA di eseguire le migration della Fase 2 (colonna
     * cover_slot assente).
     *
     * @return list<array{issue: object, plan: array}>
     */
    public static function planAll(): array
    {
        return DB::table('issues')->orderBy('id')->get()
            ->map(fn (object $issue) => ['issue' => $issue, 'plan' => CoverConversionPlanner::plan(self::pagesOf($issue->id))])
            ->all();
    }

    public static function applyAll(): void
    {
        foreach (self::planAll() as ['issue' => $issue, 'plan' => $plan]) {
            if ($plan['already_converted']) {
                continue;
            }

            DB::transaction(fn () => self::apply($issue, $plan));
        }
    }

    public static function revertAll(): void
    {
        $issueIds = DB::table('cover_conversion_logs')->distinct()->pluck('issue_id');

        foreach ($issueIds as $issueId) {
            DB::transaction(fn () => self::revert((int) $issueId));
        }

        // Numeri creati DOPO la conversione (copertine nate già come slot,
        // quindi senza righe di log): senza questo passo resterebbero
        // pagine con position NULL e il rollback dello schema (position di
        // nuovo NOT NULL) fallirebbe — scoperto provando il rollback.
        $unlogged = DB::table('pages')->whereNotNull('cover_slot')->distinct()->pluck('issue_id');

        foreach ($unlogged as $issueId) {
            DB::transaction(fn () => self::toLegacyModel((int) $issueId));
        }
    }

    /**
     * Riporta un numero nato col modello nuovo al modello precedente: le
     * copertine rientrano nella sequenza fisica (I, bandella, II, interne,
     * III, IV) con il vecchio ruolo, total_pages le conta di nuovo.
     */
    private static function toLegacyModel(int $issueId): void
    {
        $legacyRoles = [
            CoverSlot::I->value => 'copertina1',
            CoverSlot::Bandella->value => 'battente',
            CoverSlot::II->value => 'copertina2',
            CoverSlot::III->value => 'copertina3',
            CoverSlot::IV->value => 'copertina4',
        ];
        $covers = DB::table('pages')->where('issue_id', $issueId)->whereNotNull('cover_slot')->get()->keyBy('cover_slot');
        $internal = DB::table('pages')->where('issue_id', $issueId)->whereNull('cover_slot')->orderBy('position')->pluck('id');

        $front = collect([CoverSlot::I, CoverSlot::Bandella, CoverSlot::II])->map(fn ($slot) => $covers->get($slot->value))->filter();
        $back = collect([CoverSlot::III, CoverSlot::IV])->map(fn ($slot) => $covers->get($slot->value))->filter();
        $sequence = $front->pluck('id')->concat($internal)->concat($back->pluck('id'))->values();

        DB::table('pages')->whereIn('id', $sequence)->update(['position' => null]);

        foreach ($sequence as $index => $pageId) {
            DB::table('pages')->where('id', $pageId)->update(['position' => $index + 1]);
        }

        foreach ($covers as $slot => $cover) {
            DB::table('pages')->where('id', $cover->id)->update(['cover_slot' => null, 'role' => $legacyRoles[$slot]]);
        }

        DB::table('issues')->where('id', $issueId)->update(['total_pages' => $sequence->count()]);
    }

    /**
     * @return list<array{id: int, position: int|null, role: string|null, page_group_id: int|null, contents_count: int, cover_slot: string|null}>
     */
    private static function pagesOf(int $issueId): array
    {
        $hasSlot = Schema::hasColumn('pages', 'cover_slot');
        $hasRole = Schema::hasColumn('pages', 'role');
        $contentCounts = DB::table('page_content')
            ->join('pages', 'pages.id', '=', 'page_content.page_id')
            ->where('pages.issue_id', $issueId)
            ->groupBy('page_content.page_id')
            ->pluck(DB::raw('count(*)'), 'page_content.page_id');

        return DB::table('pages')->where('issue_id', $issueId)->orderBy('position')->get()
            ->map(fn (object $page) => [
                'id' => (int) $page->id,
                'position' => $page->position !== null ? (int) $page->position : null,
                'role' => $hasRole ? $page->role : null,
                'page_group_id' => $page->page_group_id !== null ? (int) $page->page_group_id : null,
                'contents_count' => (int) ($contentCounts[$page->id] ?? 0),
                'cover_slot' => $hasSlot ? $page->cover_slot : null,
            ])
            ->all();
    }

    private static function apply(object $issue, array $plan): void
    {
        $now = now();
        $log = fn (array $row) => DB::table('cover_conversion_logs')->insert($row + ['issue_id' => $issue->id, 'created_at' => $now]);

        $log([
            'action' => 'issue_totals',
            'old_total_pages' => $issue->total_pages,
            'old_pagine_copertina' => $issue->pagine_copertina ?? null,
        ]);

        foreach ($plan['covers'] as $slot => $cover) {
            DB::table('pages')->where('id', $cover['page_id'])->update([
                'cover_slot' => $slot,
                'position' => null,
                'page_group_id' => null,
                'updated_at' => $now,
            ]);
            $log([
                'page_id' => $cover['page_id'],
                'action' => 'to_cover',
                'old_position' => $cover['old_position'],
                'old_role' => $cover['old_role'],
                'cover_slot' => $slot,
                'old_page_group_id' => $cover['old_page_group_id'],
            ]);
        }

        // In ordine crescente di vecchia posizione: ogni nuova posizione è
        // ≤ della vecchia e il posto che va a occupare è già stato liberato
        // (da una copertina, ora NULL, o da una pagina precedente già
        // scalata), quindi il vincolo unique non scatta mai a metà.
        foreach (collect($plan['renumbered'])->sortBy('old_position') as $pageId => $move) {
            if ($move['old_position'] === $move['new_position']) {
                continue;
            }

            DB::table('pages')->where('id', $pageId)->update(['position' => $move['new_position'], 'updated_at' => $now]);
            $log(['page_id' => $pageId, 'action' => 'renumbered', 'old_position' => $move['old_position'], 'new_position' => $move['new_position']]);
        }

        foreach ($plan['ungrouped'] as $pageId => $groupId) {
            DB::table('pages')->where('id', $pageId)->update(['page_group_id' => null, 'updated_at' => $now]);
            $log(['page_id' => $pageId, 'action' => 'ungrouped', 'old_page_group_id' => $groupId]);
        }

        foreach ($plan['created_slots'] as $slot) {
            $pageId = DB::table('pages')->insertGetId([
                'issue_id' => $issue->id,
                'position' => null,
                'cover_slot' => $slot,
                'content_type' => 'bianca',
                'status' => 'da_assegnare',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $log(['page_id' => $pageId, 'action' => 'created_cover', 'cover_slot' => $slot]);
        }

        $update = ['total_pages' => $plan['new_total_pages'], 'updated_at' => $now];

        if (Schema::hasColumn('issues', 'pagine_copertina')) {
            $update['pagine_copertina'] = $plan['new_pagine_copertina'];
        }

        DB::table('issues')->where('id', $issue->id)->update($update);
    }

    private static function revert(int $issueId): void
    {
        $logs = DB::table('cover_conversion_logs')->where('issue_id', $issueId)->orderByDesc('id')->get();

        // Le copertine create dalla conversione spariscono (i contenuti
        // eventualmente assegnati tornano "da assegnare": page_content è in
        // cascata sulla pagina).
        $created = $logs->where('action', 'created_cover')->pluck('page_id');
        DB::table('pages')->whereIn('id', $created)->delete();

        // Prima tutte le posizioni coinvolte a NULL, poi quelle vecchie: in
        // due passi per non urtare unique(issue_id, position).
        $moved = $logs->whereIn('action', ['to_cover', 'renumbered']);
        DB::table('pages')->whereIn('id', $moved->pluck('page_id'))->update(['position' => null]);

        foreach ($moved as $row) {
            $update = ['position' => $row->old_position];

            if ($row->action === 'to_cover') {
                $update += ['cover_slot' => null, 'role' => $row->old_role, 'page_group_id' => $row->old_page_group_id];
            }

            DB::table('pages')->where('id', $row->page_id)->update($update);
        }

        foreach ($logs->where('action', 'ungrouped') as $row) {
            DB::table('pages')->where('id', $row->page_id)->update(['page_group_id' => $row->old_page_group_id]);
        }

        $totals = $logs->firstWhere('action', 'issue_totals');

        if ($totals !== null) {
            $update = ['total_pages' => $totals->old_total_pages];

            if (Schema::hasColumn('issues', 'pagine_copertina')) {
                $update['pagine_copertina'] = $totals->old_pagine_copertina;
            }

            DB::table('issues')->where('id', $issueId)->update($update);
        }

        DB::table('cover_conversion_logs')->where('issue_id', $issueId)->delete();
    }
}
