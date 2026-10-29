<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un passo della cronologia annulla/ripeti (2026-10-28), tabella
 * `timone_azioni`. Scritto e letto solo da App\Support\Timeline.
 */
class TimelineAction extends Model
{
    public const DONE = 'eseguita';

    public const UNDONE = 'annullata';

    protected $table = 'timone_azioni';

    protected $fillable = ['issue_id', 'user_id', 'sequence', 'type', 'label', 'changes', 'status', 'blocked_reason', 'batch_key', 'undone_at'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'sequence' => 'integer',
            'undone_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
