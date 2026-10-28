<?php

namespace App\Models;

use App\Enums\ContentType;
use App\Enums\FileFit;
use App\Enums\ThumbnailStatus;
use App\Support\PageOccupancy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Content extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'section_id',
        'type',
        'title',
        'occupancy_twelfths',
        'is_placeholder',
        // Revisione 2, Fase 2.4: scelta manuale "a pagina intera"/"nel formato" (App\Enums\FileFit), null = automatico.
        'file_fit',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'archived_at' => 'datetime',
            'occupancy_twelfths' => 'integer',
            'is_placeholder' => 'boolean',
            'file_fit' => FileFit::class,
        ];
    }

    /**
     * Occupazione totale in dodicesimi (Fase 3, 2026-10-27). Null solo se
     * il dato manca (non dovrebbe: la colonna è NOT NULL dalla conversione)
     * — chi la legge rifiuta l'operazione, mai un default silenzioso.
     * Vale per articoli e pubblicità allo stesso modo: per la pubblicità il
     * formato la PROPONE alla creazione, ma resta modificabile.
     */
    public function occupancy(): ?PageOccupancy
    {
        return PageOccupancy::tryFromTwelfths($this->occupancy_twelfths);
    }

    /**
     * Anteprima pronta del file più recente del contenuto (chip dei
     * contenuti da assegnare), o null. Il PDF/immagine di stampa È la
     * miniatura: non esiste un'immagine separata (scelta confermata
     * dall'utente nella Fase 4, 2026-10-27).
     * Richiede `files` eager-loaded per non fare una query per contenuto.
     */
    public function previewImageUrl(): ?string
    {
        $file = $this->files->sortByDesc('created_at')->first();

        return $file?->thumbnail_status === ThumbnailStatus::Ready ? route('content-files.thumbnail', $file) : null;
    }

    /**
     * Pubblicità segnaposto (Fase 5, 2026-10-27): senza inserzionista né
     * file — dalle "pubblicità previste" di un numero o dal modulo col
     * cliente vuoto. Conta nel carico pubblicitario come ogni pubblicità e
     * nel contatore "da assegnare". Legge il valore memorizzato: la regola
     * (e chi la scrive) è App\Support\AdPlaceholder (revisione 2, Fase 3.1).
     */
    public function isPlaceholder(): bool
    {
        return (bool) $this->is_placeholder;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function article(): HasOne
    {
        return $this->hasOne(Article::class);
    }

    public function advertisement(): HasOne
    {
        return $this->hasOne(Advertisement::class);
    }

    /**
     * File allegati a questo contenuto (Fase 4.1) — non alla pagina, a
     * differenza di Page::files()/PageFile: risolve il limite già segnalato
     * in HANDOFF.md (Punto 5) di non poter distinguere quale contenuto di
     * una pagina mista possiede davvero un PDF/immagine caricato.
     */
    public function files(): HasMany
    {
        return $this->hasMany(ContentFile::class)->latest();
    }

    /**
     * Pagine a cui questo contenuto è assegnato (di norma una sola, ma
     * il modello ne permette più di una per contenuti "a cavallo").
     */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_content')
            ->using(PageContent::class)
            // content_file_id (Punto 4, 2026-09-02): quale file di questo
            // contenuto è rilevante per ciascuna pagina — vedi
            // PageContent::contentFile().
            ->withPivot('occupied_twelfths', 'content_file_id', 'position', 'grid_x', 'grid_y', 'grid_w', 'grid_h')
            ->withTimestamps();
    }

    public function isAssigned(): bool
    {
        return $this->pages()->exists();
    }

    /**
     * Etichetta da mostrare sulla card pagina: titolo per gli articoli,
     * cliente per le pubblicità.
     */
    public function displayLabel(): string
    {
        return match ($this->type) {
            ContentType::Articolo => $this->title,
            ContentType::Pubblicita => $this->advertisement?->client ?? $this->title,
        };
    }
}
