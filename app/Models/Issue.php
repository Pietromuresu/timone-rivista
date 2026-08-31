<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\PageRole;
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
        'print_deadline_at',
        'deadline_reminder_sent_at',
        'status',
        'total_pages',
        'notes',
        'reorder_version',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'print_deadline_at' => 'date',
            'deadline_reminder_sent_at' => 'datetime',
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
        // Fase 4.3: un contenuto archiviato non è "assegnabile" finché non
        // viene ripristinato — resta comunque non-cancellato in database
        // (a differenza di Grid::deleteUnassignedContent(), che è invece
        // definitiva), semplicemente escluso da questo pannello.
        return $this->contents()->whereDoesntHave('pages')->whereNull('archived_at');
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

    /**
     * Numero di pagine mostrato all'utente (Fase 3.1) — SOLO per
     * visualizzazione, mai usato per posizioni/ordinamento: total_pages
     * resta l'unica fonte di verità per lo schema (unique(issue_id,
     * position), PageReorderer, ecc.), il battente occupa comunque una
     * posizione fisica sequenziale come qualunque altra pagina. Scelta
     * pragmatica documentata in HANDOFF.md — un vero "fuori sequenza" per
     * il battente romperebbe l'invariante di posizione in tutto il
     * progetto per un guadagno solo cosmetico.
     */
    public function displayedTotalPages(): int
    {
        if ($this->magazine->battente_counted_in_total) {
            return $this->total_pages;
        }

        $battenteCount = $this->pages()->where('role', PageRole::Battente->value)->count();

        return $this->total_pages - $battenteCount;
    }
}
