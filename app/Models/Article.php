<?php

namespace App\Models;

use App\Enums\EditorialStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_id',
        'author',
        'editorial_status',
        'expected_length',
    ];

    protected function casts(): array
    {
        return [
            'editorial_status' => EditorialStatus::class,
            'expected_length' => 'integer',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
