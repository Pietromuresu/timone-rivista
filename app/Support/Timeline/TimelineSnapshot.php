<?php

namespace App\Support\Timeline;

use Illuminate\Support\Facades\DB;

/**
 * Fotografia delle tabelle del timone di un numero (annulla/ripeti,
 * 2026-10-28): pagine, assegnazioni, gruppi, contenuti (anche eliminati di
 * recente) con articoli, pubblicità e righe dei file. Righe grezze dal
 * query builder: si confrontano e si riapplicano così come sono, senza
 * passare dai model (niente eventi, cast o scope globali).
 *
 * L'ordine di TABLES è quello in cui si inseriscono/aggiornano (i genitori
 * prima dei figli); le eliminazioni vanno al contrario.
 */
final class TimelineSnapshot
{
    public const TABLES = ['page_groups', 'contents', 'articles', 'advertisements', 'content_files', 'pages', 'page_content'];

    /** @return array<string, array<int, array<string, mixed>>> tabella => id => riga */
    public static function take(int $issueId): array
    {
        $rows = fn ($query) => $query->get()->mapWithKeys(fn ($row) => [(int) $row->id => (array) $row])->all();

        $contentIds = DB::table('contents')->where('issue_id', $issueId)->pluck('id')->all();
        $pageIds = DB::table('pages')->where('issue_id', $issueId)->pluck('id')->all();

        return [
            'page_groups' => $rows(DB::table('page_groups')->where('issue_id', $issueId)),
            'contents' => $rows(DB::table('contents')->where('issue_id', $issueId)),
            'articles' => $rows(DB::table('articles')->whereIn('content_id', $contentIds)),
            'advertisements' => $rows(DB::table('advertisements')->whereIn('content_id', $contentIds)),
            'content_files' => $rows(DB::table('content_files')->whereIn('content_id', $contentIds)),
            'pages' => $rows(DB::table('pages')->where('issue_id', $issueId)),
            'page_content' => $rows(DB::table('page_content')->whereIn('page_id', $pageIds)),
        ];
    }
}
