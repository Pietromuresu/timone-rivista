<?php

namespace App\Models;

use App\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'magazine_id',
        'title',
        'issue_date',
        'status',
        'total_pages',
        'notes',
        'reorder_version',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'status' => IssueStatus::class,
            'total_pages' => 'integer',
            'reorder_version' => 'integer',
        ];
    }

    public function magazine(): BelongsTo
    {
        return $this->belongsTo(Magazine::class);
    }

    /**
     * Pagine del timone, ordinate per posizione.
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->orderBy('position');
    }

    /**
     * Contenuti (articoli/pubblicità) creati per questa uscita.
     */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    /**
     * Contenuti non ancora assegnati a nessuna pagina.
     */
    public function unassignedContents(): HasMany
    {
        return $this->contents()->whereDoesntHave('pages');
    }

    public function reorderLogs(): HasMany
    {
        return $this->hasMany(PageReorderLog::class);
    }

    /**
     * Duplica solo la "struttura" (il tipo di ciascuna pagina — editoriale,
     * pubblicità, mista, bianca) di un'issue precedente sulle pagine di
     * questa issue, posizione per posizione, fino al minimo tra i due
     * total_pages. Contenuti, stati e note **non** vengono copiati:
     * questa issue è nuova, con contenuti assegnati da zero — solo il
     * layout del timone (che pagine tenere per pubblicità/editoriale) si
     * riusa, come da richiesta esplicita dello spec ("duplicazione
     * struttura da numero precedente").
     */
    public function duplicateStructureFrom(self $source): void
    {
        $sourceTypes = $source->pages()->pluck('content_type', 'position');

        foreach ($this->pages()->get() as $page) {
            if ($sourceTypes->has($page->position)) {
                $page->update(['content_type' => $sourceTypes[$page->position]]);
            }
        }
    }
}
