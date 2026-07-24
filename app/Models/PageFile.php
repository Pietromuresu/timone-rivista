<?php

namespace App\Models;

use App\Enums\ThumbnailStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'uploaded_by',
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'size',
        'thumbnail_status',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'thumbnail_status' => ThumbnailStatus::class,
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
