<?php

namespace App\Models;

use App\Enums\FormatCheckStatus;
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
        'pdf_page_number',
        'format_check_status',
        'measured_width_mm',
        'measured_height_mm',
        'format_override_confirmed_by',
        'format_override_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'thumbnail_status' => ThumbnailStatus::class,
            'pdf_page_number' => 'integer',
            'format_check_status' => FormatCheckStatus::class,
            'measured_width_mm' => 'decimal:1',
            'measured_height_mm' => 'decimal:1',
            'format_override_confirmed_at' => 'datetime',
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

    public function formatOverrideConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'format_override_confirmed_by');
    }

    /**
     * Il badge "formato non conforme" (§2.3) va mostrato solo se il
     * controllo ha davvero rilevato una non conformità e l'utente non l'ha
     * ancora accettata esplicitamente (Grid::confirmFormatOverride()).
     */
    public function hasUnresolvedFormatMismatch(): bool
    {
        return $this->format_check_status === FormatCheckStatus::Mismatch
            && $this->format_override_confirmed_at === null;
    }
}
