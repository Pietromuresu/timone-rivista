<?php

namespace App\Models;

use App\Enums\ThumbnailStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * File allegato a un Content (Fase 4.1), non a una Page — a differenza di
 * PageFile, che è per costruzione legato alla pagina fisica (vedi
 * HANDOFF.md, limite noto già segnalato nel Punto 5 sul cruscotto
 * pubblicitario: "impossibile distinguere quale contenuto di una pagina
 * mista ha effettivamente il PDF"). Una riga qui risolve esattamente quel
 * limite per i nuovi contenuti che allegano un file da questa fase in poi
 * (i PageFile già esistenti restano legati alla pagina, nessuna migrazione
 * a ritroso: sono due concetti che continuano a convivere).
 */
class ContentFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_id',
        'uploaded_by',
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
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

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
