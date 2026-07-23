<?php

namespace App\Models;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Advertisement extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_id',
        'client',
        'agency',
        'format',
        'occupied_percentage_override',
        'confirmation_status',
        'commercial_notes',
    ];

    protected function casts(): array
    {
        return [
            'format' => AdFormat::class,
            'occupied_percentage_override' => 'decimal:2',
            'confirmation_status' => AdConfirmationStatus::class,
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * Percentuale di pagina occupata: quella manuale se presente,
     * altrimenti il default associato al formato pubblicitario.
     */
    public function occupiedPercentage(): float
    {
        return $this->occupied_percentage_override !== null
            ? (float) $this->occupied_percentage_override
            : $this->format->defaultPercentage();
    }
}
