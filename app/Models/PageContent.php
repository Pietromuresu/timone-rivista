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
        'content_file_id',
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

    /**
     * Quale file specifico del contenuto (Punto 4 del prompt, 2026-09-02)
     * è rilevante per QUESTA pagina — valorizzato solo per una pagina
     * derivata da un PDF multi-pagina caricato su un contenuto, dove ogni
     * pagina timone coinvolta mostra una pagina interna diversa dello
     * stesso PDF. Null per un contenuto a file singolo: il chiamante
     * ricade su Content::files()->first() (vedi page-card.blade.php/
     * page-row.blade.php).
     */
    public function contentFile(): BelongsTo
    {
        return $this->belongsTo(ContentFile::class);
    }
}
