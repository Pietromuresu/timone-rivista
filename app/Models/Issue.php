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
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'status' => IssueStatus::class,
            'total_pages' => 'integer',
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
}
