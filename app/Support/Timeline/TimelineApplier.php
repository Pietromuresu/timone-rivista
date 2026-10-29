<?php

namespace App\Support\Timeline;

use App\Enums\CoverSlot;
use App\Models\ActivityLog;
use App\Models\Issue;
use App\Models\Page;
use App\Support\Twelfths;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Riapplica uno stato registrato (annulla/ripeti, 2026-10-28): «prima» per
 * annullare, «dopo» per ripetere. Un solo applicatore generico per tutte le
 * azioni, al posto di un'azione inversa scritta a mano per ogni tipo.
 *
 * Tre fasi, da chiamare dentro una transazione (TimelineActionService):
 * 1. conflict(): ogni campo registrato deve avere ancora il valore lasciato
 *    dalla mia azione (o, per ripetere, quello di prima). Altrimenti
 *    qualcuno l'ha cambiato dopo di me: nessuna modifica, messaggio con
 *    chi e quando (dal registro attività).
 * 2. apply(): eliminazioni (al contrario dell'ordine delle tabelle), poi
 *    inserimenti/aggiornamenti. Posizione e slot di copertina delle pagine
 *    in due passi (unique(issue_id, position/cover_slot)). Un contenuto
 *    «tolto» va nel cestino (soft delete) e le sue righe figlie restano,
 *    così ripetere lo riporta con file e dati.
 * 3. capacityProblem(): nessuna pagina toccata deve finire oltre capienza
 *    se prima non lo era (un collega può aver occupato lo spazio libero).
 */
final class TimelineApplier
{
    /** Tabelle in cui una riga «tolta» di un contenuto nel cestino si tiene. */
    private const CONTENT_CHILDREN = ['articles', 'advertisements', 'content_files'];

    /**
     * @param  list<array>  $changes
     * @return string|null motivo del conflitto, o null se si può applicare
     */
    public static function conflict(Issue $issue, array $changes, bool $undo, CarbonInterface $since, int $userId): ?string
    {
        foreach ($changes as $change) {
            $expected = $undo ? $change['a'] : $change['b'];
            $current = self::current($change['t'], $change['id']);

            if (self::matches($change['t'], $expected, $current)) {
                continue;
            }

            return 'Non posso '.($undo ? 'annullare' : 'ripetere').': '.self::describe($change, $current).self::who($issue, $since, $userId).'.';
        }

        return null;
    }

    /** @param  list<array>  $changes */
    public static function apply(array $changes, bool $undo): void
    {
        $targets = array_map(fn (array $change) => $change + ['target' => $undo ? $change['b'] : $change['a']], $changes);

        // Contenuti che resteranno (o finiranno) nel cestino: le loro righe figlie non si toccano.
        $trashed = collect($targets)->filter(fn ($c) => $c['t'] === 'contents' && ($c['target'] === null || ($c['target']['deleted_at'] ?? null) !== null))->pluck('id')->all();

        // 1. Eliminazioni, figli prima dei genitori.
        foreach (array_reverse(TimelineSnapshot::TABLES) as $table) {
            foreach ($targets as $change) {
                if ($change['t'] !== $table || $change['target'] !== null) {
                    continue;
                }

                if ($table === 'contents') {
                    DB::table('contents')->where('id', $change['id'])->update(['deleted_at' => now(), 'updated_at' => now()]);
                } elseif (in_array($table, self::CONTENT_CHILDREN, true) && in_array((int) (self::current($table, $change['id'])['content_id'] ?? 0), $trashed, true)) {
                    continue;
                } else {
                    DB::table($table)->where('id', $change['id'])->delete();
                }
            }
        }

        // 2. Pagine: posizione e slot di copertina liberati prima di riassegnarli.
        foreach ($targets as $change) {
            if ($change['t'] === 'pages' && $change['target'] !== null && self::current('pages', $change['id']) !== null) {
                $free = [];

                if (array_key_exists('position', $change['target']) && $change['target']['position'] !== null) {
                    $free['position'] = DB::raw('position + 100000');
                }

                if (array_key_exists('cover_slot', $change['target'])) {
                    $free['cover_slot'] = null;
                }

                if ($free !== []) {
                    DB::table('pages')->where('id', $change['id'])->update($free);
                }
            }
        }

        // 3. Inserimenti e aggiornamenti, genitori prima dei figli.
        foreach (TimelineSnapshot::TABLES as $table) {
            foreach ($targets as $change) {
                if ($change['t'] !== $table || $change['target'] === null) {
                    continue;
                }

                $values = array_diff_key($change['target'], array_flip(['id']));

                if (self::current($table, $change['id']) !== null) {
                    DB::table($table)->where('id', $change['id'])->update($values + (array_key_exists('updated_at', self::columns($table)) ? ['updated_at' => now()] : []));
                } else {
                    DB::table($table)->insert(['id' => $change['id']] + $values);
                }
            }
        }
    }

    /**
     * Pagine toccate che dopo l'applicazione sono oltre capienza o con la
     * griglia piena, mentre prima no.
     *
     * @param  array<int, bool>  $overfullBefore  page_id => era già oltre capienza
     */
    public static function capacityProblem(Issue $issue, array $overfullBefore): ?string
    {
        foreach (array_keys($overfullBefore) as $pageId) {
            $page = Page::with(['contents', 'issue'])->find($pageId);

            if ($page === null || $overfullBefore[$pageId]) {
                continue;
            }

            if (self::isOverfull($page)) {
                return "sulla {$page->label()} non c'è più spazio (occupata: ".Twelfths::pagesLabel($page->occupiedTwelfths()).'): qualcuno l\'ha riempita nel frattempo';
            }
        }

        return null;
    }

    /** @param  list<array>  $changes  @return array<int, bool> */
    public static function overfullBefore(array $changes): array
    {
        $pageIds = [];

        foreach ($changes as $change) {
            if ($change['t'] === 'pages') {
                $pageIds[] = $change['id'];
            }

            if ($change['t'] === 'page_content') {
                foreach ([$change['b'], $change['a'], self::current('page_content', $change['id'])] as $row) {
                    if (isset($row['page_id'])) {
                        $pageIds[] = (int) $row['page_id'];
                    }
                }
            }
        }

        return collect(array_unique($pageIds))
            ->mapWithKeys(fn (int $id) => [$id => ($page = Page::with(['contents', 'issue'])->find($id)) !== null && self::isOverfull($page)])
            ->all();
    }

    private static function isOverfull(Page $page): bool
    {
        return $page->occupiedTwelfths() > Twelfths::PER_PAGE || (bool) ($page->layout()['overfull'] ?? false);
    }

    /** @return array<string, mixed>|null riga attuale (anche se nel cestino) */
    private static function current(string $table, int $id): ?array
    {
        $row = DB::table($table)->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    private static function matches(string $table, ?array $expected, ?array $current): bool
    {
        // Atteso «non c'è»: per un contenuto vale anche «nel cestino», e per
        // le sue righe figlie «ci sono, ma il contenuto è nel cestino» (si
        // tengono apposta, vedi apply()).
        if ($expected === null) {
            return $current === null
                || ($table === 'contents' && ($current['deleted_at'] ?? null) !== null)
                || (in_array($table, self::CONTENT_CHILDREN, true) && DB::table('contents')->where('id', $current['content_id'] ?? 0)->whereNotNull('deleted_at')->exists());
        }

        if ($current === null) {
            return false;
        }

        if (in_array($table, TimelineDiff::EXISTENCE_ONLY, true)) {
            return true;
        }

        foreach ($expected as $field => $value) {
            if (in_array($field, TimelineDiff::IGNORED, true) || $field === 'id') {
                continue;
            }

            if (TimelineDiff::normalize($value) !== TimelineDiff::normalize($current[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** «la pagina 12 è stata modificata», «il contenuto «Acme» è stato modificato»… */
    private static function describe(array $change, ?array $current): string
    {
        $row = $current ?? $change['a'] ?? $change['b'] ?? [];

        return match ($change['t']) {
            'pages' => self::pageLabel($row).' è stata modificata',
            'page_content' => self::pageLabel(self::current('pages', (int) ($row['page_id'] ?? 0)) ?? []).' è stata modificata',
            'contents' => 'il contenuto «'.($row['title'] ?? '?').'» è stato modificato',
            'articles', 'advertisements', 'content_files' => 'il contenuto «'.(DB::table('contents')->where('id', $row['content_id'] ?? 0)->value('title') ?? '?').'» è stato modificato',
            'page_groups' => 'un gruppo di pagine è stato modificato',
            default => 'il timone è stato modificato',
        };
    }

    private static function pageLabel(array $row): string
    {
        if (($row['cover_slot'] ?? null) !== null) {
            return 'la '.CoverSlot::from($row['cover_slot'])->label();
        }

        return isset($row['position']) ? 'la pagina '.$row['position'] : 'una pagina';
    }

    /** « da Mario alle 16:42», dal registro attività dopo la mia azione (se c'è). */
    private static function who(Issue $issue, CarbonInterface $since, int $userId): string
    {
        $log = ActivityLog::with('user')->where('issue_id', $issue->id)->where('created_at', '>=', $since)
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $userId))
            ->latest('created_at')->first();

        return $log === null ? ' dopo la tua azione' : ' da '.($log->user?->name ?? 'un altro utente').' alle '.$log->created_at->format('H:i');
    }

    /** @return array<string, true> */
    private static function columns(string $table): array
    {
        static $cache = [];

        return $cache[$table] ??= array_fill_keys(DB::getSchemaBuilder()->getColumnListing($table), true);
    }
}
