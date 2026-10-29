<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commento su una pagina (2026-10-29). Scritto da
 * App\Support\Comments\PageCommentWriter, letto da PageCommentReader.
 * `page_id` NULL = pagina eliminata: il nome resta in `page_label`
 * (vedi la migration 2026_10_29_100000).
 */
class PageComment extends Model
{
    use SoftDeletes;

    protected $fillable = ['issue_id', 'page_id', 'user_id', 'body', 'page_label', 'page_deleted_at', 'edited_at'];

    protected function casts(): array
    {
        return [
            'page_deleted_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Utenti che l'hanno letto (tabella page_comment_reads). */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'page_comment_reads')->withPivot('read_at');
    }

    public function isPageDeleted(): bool
    {
        return $this->page_id === null;
    }

    /** «pagina 12», «II di copertina» o «pagina 47, eliminata». */
    public function pageName(): string
    {
        return $this->page !== null ? $this->page->label() : $this->page_label.', eliminata';
    }

    public function authorName(): string
    {
        return $this->author?->name ?? 'Utente eliminato';
    }
}
