<?php

namespace App\Models;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dati commerciali di una pubblicità. Dalla Fase 3 (2026-10-27) lo spazio
 * occupato NON sta più qui (occupiedPercentage()/occupied_percentage_override,
 * colonna rimasta nel database ma non più letta): è l'occupazione in
 * dodicesimi del Content, proposta dal formato (AdFormat::twelfths()) e
 * modificabile come per un articolo.
 */
class Advertisement extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_id',
        'client',
        'agency',
        'format',
        'preferred_position',
        'confirmation_status',
        'commercial_notes',
    ];

    protected function casts(): array
    {
        return [
            'format' => AdFormat::class,
            'preferred_position' => 'integer',
            'confirmation_status' => AdConfirmationStatus::class,
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
