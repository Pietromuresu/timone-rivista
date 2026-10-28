<?php

namespace App\Models;

use App\Enums\MagazineExportStatus;
use App\Enums\MagazineExportVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Export PDF sfogliabile di un numero, una riga per variante (revisione 2,
 * Fase 5). Scritta da App\Support\MagazineExport\MagazineExportRequester
 * (richiesta) e dal job App\Jobs\GenerateMagazinePdf (avanzamento, file).
 */
class MagazineExport extends Model
{
    protected $fillable = [
        'issue_id', 'variant', 'status', 'disk', 'path', 'size', 'page_count',
        'missing_files', 'error', 'requested_by', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'variant' => MagazineExportVariant::class,
            'status' => MagazineExportStatus::class,
            'size' => 'integer',
            'page_count' => 'integer',
            'missing_files' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
