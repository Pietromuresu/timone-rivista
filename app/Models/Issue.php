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
     * Segna "qualcosa di visibile sul timone è cambiato" (2026-09-01,
     * fallback a polling di Grid::checkForUpdates()) — chiamato ovunque il
     * codice già trasmette un evento broadcast (PageMoved, ContentAssigned,
     * PageStatusUpdated, PageGroupsChanged, PageFileUploaded, ecc., vedi
     * HANDOFF.md), mai un observer sul model Page: molte di queste mutazioni
     * passano da una pivot (`page_content`) o da un modello diverso
     * (`PageFile`/`Content`), che un observer su `Page` da solo non
     * intercetterebbe comunque. `touch()` con una colonna esplicita
     * aggiorna SOLO `pages_updated_at`, un singolo UPDATE leggero, senza
     * toccare `updated_at`/altri campi del modello.
     */
    public function markPagesUpdated(): void
    {
        $this->touch('pages_updated_at');
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
     * Numero di pagine mostrato all'utente (Fase 3.1, estesa per separare
     * la copertina dal conteggio) — SOLO per visualizzazione, mai usato
     * per posizioni/ordinamento: total_pages resta l'unica fonte di
     * verità per lo schema (unique(issue_id, position), PageReorderer,
     * ecc.), copertina e battente occupano comunque una posizione fisica
     * sequenziale come qualunque altra pagina. Scelta pragmatica
     * documentata in HANDOFF.md — un vero "fuori sequenza" romperebbe
     * l'invariante di posizione in tutto il progetto per un guadagno solo
     * cosmetico. Le quattro pagine di copertina (I-IV) sono SEMPRE escluse
     * (mai contano come pagina numerata, a differenza del battente che
     * resta configurabile per rivista).
     */
    public function displayedTotalPages(): int
    {
        $coverCount = $this->pages()->whereIn('role', [
            PageRole::Copertina1->value,
            PageRole::Copertina2->value,
            PageRole::Copertina3->value,
            PageRole::Copertina4->value,
        ])->count();

        $battenteCount = $this->magazine->battente_counted_in_total
            ? 0
            : $this->pages()->where('role', PageRole::Battente->value)->count();

        return $this->total_pages - $coverCount - $battenteCount;
    }
}
