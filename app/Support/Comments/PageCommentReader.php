<?php

namespace App\Support\Comments;

use App\Enums\CoverSlot;
use App\Models\Issue;
use App\Models\Page;
use App\Models\PageComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Letture e stato letto/non letto dei commenti (2026-10-29).
 *
 * Non letto = scritto da un altro (o da un utente eliminato), non
 * eliminato, senza riga in page_comment_reads per me. I miei commenti non
 * sono mai «non letti».
 */
final class PageCommentReader
{
    /**
     * Contatori per le icone delle pagine e per la testata, in una query.
     * `deleted` = commenti di pagine eliminate (contano nei non letti della
     * testata, non hanno un'icona).
     *
     * @return array{pages: array<int, array{total: int, unread: int}>, unread: int}
     */
    public static function counts(Issue $issue, User $user): array
    {
        $rows = DB::table('page_comments as c')
            ->leftJoin('page_comment_reads as r', fn ($join) => $join->on('r.page_comment_id', '=', 'c.id')->where('r.user_id', $user->id))
            ->where('c.issue_id', $issue->id)
            ->whereNull('c.deleted_at')
            ->groupBy('c.page_id')
            ->selectRaw('c.page_id, count(*) as total, sum(case when r.user_id is null and (c.user_id is null or c.user_id <> ?) then 1 else 0 end) as unread', [$user->id])
            ->get();

        $pages = [];

        foreach ($rows as $row) {
            if ($row->page_id !== null) {
                $pages[(int) $row->page_id] = ['total' => (int) $row->total, 'unread' => (int) $row->unread];
            }
        }

        return ['pages' => $pages, 'unread' => (int) $rows->sum('unread')];
    }

    /** @return Collection<int, PageComment> in ordine cronologico */
    public static function forPage(Page $page): Collection
    {
        return $page->comments()->with('author')->orderBy('created_at')->orderBy('id')->get();
    }

    /** @return Collection<int, PageComment> */
    public static function unreadFor(Issue $issue, User $user): Collection
    {
        return self::unreadQuery($issue, $user)->with(['author', 'page'])->orderBy('created_at')->orderBy('id')->get();
    }

    /**
     * Tutti i commenti del numero, anche delle pagine eliminate (unico
     * posto dove restano consultabili). Per pagina: interne in ordine,
     * copertine, poi pagine eliminate.
     *
     * @return Collection<int, PageComment>
     */
    public static function allFor(Issue $issue): Collection
    {
        return PageComment::where('issue_id', $issue->id)->with(['author', 'page'])->orderBy('created_at')->orderBy('id')->get()
            ->sortBy(fn (PageComment $comment) => match (true) {
                $comment->page === null => [2, 0],
                $comment->page->isCover() => [1, array_search($comment->page->cover_slot, CoverSlot::cases(), true)],
                default => [0, $comment->page->position],
            })->values();
    }

    /** Aprendo i commenti di una pagina li ho letti tutti. */
    public static function markPageRead(Page $page, User $user): void
    {
        self::markRead($page->comments()->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '<>', $user->id))->pluck('id')->all(), $user);
    }

    /** @param  list<int>  $commentIds */
    public static function markRead(array $commentIds, User $user): void
    {
        if ($commentIds === []) {
            return;
        }

        DB::table('page_comment_reads')->insertOrIgnore(array_map(
            fn (int $id) => ['page_comment_id' => $id, 'user_id' => $user->id, 'read_at' => now()],
            $commentIds,
        ));
    }

    /** @return Builder<PageComment> */
    private static function unreadQuery(Issue $issue, User $user): Builder
    {
        return PageComment::where('issue_id', $issue->id)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '<>', $user->id))
            ->whereDoesntHave('readers', fn ($q) => $q->whereKey($user->id));
    }
}
