<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageReorderLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'issue_id',
        'page_id',
        'user_id',
        'from_position',
        'to_position',
    ];

    protected function casts(): array
    {
        return [
            'from_position' => 'integer',
            'to_position' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
