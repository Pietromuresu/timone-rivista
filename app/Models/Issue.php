<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Support\Weight\MagazineWeightCalculator;
use App\Support\Weight\MagazineWeightInput;
use App\Support\Weight\MagazineWeightResult;
use App\Support\Weight\WeightLimits;
use App\Support\Weight\WeightParameters;
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
        // Parametri del peso (WeightParameters::FIELDS + ISSUE_ONLY_FIELDS),
        // vedi weightInput() per il perché sono una copia e non un riferimento.
        'formato_base_mm',
        'formato_altezza_mm',
        'grammatura_interno',
        'grammatura_copertina',
        'pagine_copertina',
        'maggiorazione_percentuale',
        'peso_bancale_kg',
        'tolleranza_grammatura_perc',
        'tolleranza_formato_mm',
        'tolleranza_maggiorazione_perc',
        'n_copie',
        'n_bancali',
        'peso_reale_g',
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
            // Stessa scelta di Magazine::casts(): cast solo sugli interi.
            'formato_base_mm' => 'integer',
            'formato_altezza_mm' => 'integer',
            'pagine_copertina' => 'integer',
            'n_copie' => 'integer',
            'n_bancali' => 'integer',
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
     * Pagine INTERNE del timone (1…total_pages), ordinate per posizione.
     *
     * Fase 2 (2026-10-26): le copertine sono escluse qui, alla fonte —
     * riordino, ridimensionamento, cruscotto, conteggi, selezione "tutte"
     * passano da questa relazione e non devono mai vederle. Per le
     * copertine: covers(); per entrambe (es. ricerca per id): allPages().
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->whereNull('cover_slot')->orderBy('position');
    }

    /**
     * Copertine del numero (I–IV sempre, bandella se presente), in ordine
     * di sfoglio. Vedi App\Enums\CoverSlot.
     */
    public function covers(): HasMany
    {
        return $this->hasMany(Page::class)->whereNotNull('cover_slot');
    }

    /** Pagine interne e copertine insieme. */
    public function allPages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Facciate di copertina stampate (4, o 6 con la bandella): la fonte del
     * parametro `pagine_copertina` del peso, sincronizzato da
     * App\Support\IssueCovers ogni volta che le copertine cambiano.
     */
    public function coverPageCount(): int
    {
        return $this->covers()->get(['cover_slot'])->sum(fn (Page $cover) => $cover->cover_slot->printedPages());
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

        // Fase 2: anche le copertine, slot per slot (es. la IV sempre pubblicità).
        $sourceCoverTypes = $source->covers()->get()->mapWithKeys(fn (Page $cover) => [$cover->cover_slot->value => $cover->content_type]);

        foreach ($this->covers()->get() as $cover) {
            if ($sourceCoverTypes->has($cover->cover_slot->value)) {
                $cover->update(['content_type' => $sourceCoverTypes[$cover->cover_slot->value]]);
            }
        }
    }

    /**
     * Numero di pagine interne del numero, quello mostrato all'utente.
     * Fino alla Fase 2 le copertine stavano dentro total_pages e qui si
     * sottraevano (vedi HANDOFF.md, sessione 2026-09-01, scelta "A"); dal
     * 2026-10-26 sono slot separati e total_pages è già il dato giusto.
     */
    public function displayedTotalPages(): int
    {
        // Fase 2 (2026-10-26): total_pages conta ormai SOLO le pagine
        // interne (le copertine sono fuori sequenza), quindi niente più
        // sottrazioni. Il metodo resta per i chiamanti esistenti.
        return (int) $this->total_pages;
    }

    /**
     * Input del calcolo del peso teorico di una copia di QUESTO numero.
     *
     * I parametri (formato, carta, maggiorazione, tolleranze, tiratura) sono
     * letti SOLO dalle colonne dell'issue, mai dalla rivista: vengono copiati
     * dai default della rivista alla creazione del numero (Issues\Create) e
     * da lì sono indipendenti. Motivo: se domani cambia la carta della
     * testata, i numeri già usciti non devono cambiare peso — modificare i
     * default della rivista vale solo per i numeri creati dopo (coperto da
     * tests/Feature/Livewire/IssueWeightTest.php).
     *
     * Le pagine interne non sono un parametro salvato: sono total_pages, così
     * il peso segue il timone da solo. Le pagine di copertina non sono più il
     * parametro modificabile a mano: derivano dalle copertine che esistono
     * (coverPageCount(), 4 o 6 con la bandella).
     */
    public function weightInput(): MagazineWeightInput
    {
        // Fase 2 (2026-10-26): pagine interne = total_pages (le copertine
        // non ne fanno più parte), pagine di copertina = quelle che
        // esistono davvero (4, 6 con la bandella) — nessuna sottrazione.
        $params = $this->only([...WeightParameters::FIELDS, ...WeightParameters::ISSUE_ONLY_FIELDS]);
        $params['pagine_copertina'] = $this->coverPageCount();

        return MagazineWeightInput::fromArray($params, (int) $this->total_pages);
    }

    /**
     * Passa sempre i limiti della config: una grammatura o un formato
     * sporchi già salvati (0,8 invece di 80, 21 invece di 210) devono dare
     * un risultato incompleto, non un peso di pochi grammi (vedi WeightLimits).
     */
    public function weight(): MagazineWeightResult
    {
        return MagazineWeightCalculator::calculate($this->weightInput(), config('peso_rivista.scaglioni'), WeightLimits::fromConfig());
    }
}
