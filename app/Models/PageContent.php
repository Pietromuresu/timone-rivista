<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PageContent extends Pivot
{
    protected $table = 'page_content';

    public $incrementing = true;

    protected $fillable = [
        'page_id',
        'content_id',
        'occupied_percentage',
    ];

    protected function casts(): array
    {
        return [
            'occupied_percentage' => 'decimal:2',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
