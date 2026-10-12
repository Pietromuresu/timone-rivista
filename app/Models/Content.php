<?php

namespace App\Models;

use App\Enums\ContentType;
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
        'page_occupancy',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'archived_at' => 'datetime',
            'page_occupancy' => 'decimal:2',
        ];
    }

    /**
     * Bug 5 (2026-10-12): null solo per i contenuti creati prima che
     * l'occupazione diventasse obbligatoria e non ancora valorizzati (vedi
     * `php artisan timone:missing-occupancy`) — chi la legge deve trattare
     * null come "dato mancante" e rifiutare l'operazione, mai come 1 pagina.
     */
    public function occupancy(): ?PageOccupancy
    {
        return PageOccupancy::tryFrom($this->page_occupancy);
    }

    /**
     * Percentuale occupata su ciascuna pagina attraversata dal contenuto:
     * per la pubblicità resta quella di listino/manuale
     * (Advertisement::occupiedPercentage(), che può non essere una frazione
     * esatta: 52%, 32.6%...), per un articolo la deriva l'occupazione.
     * Null se il dato manca.
     */
    public function occupiedPercentagePerPage(): ?float
    {
        if ($this->type === ContentType::Pubblicita) {
            return $this->advertisement?->occupiedPercentage();
        }

        return $this->occupancy()?->percentagePerPage();
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
            ->withPivot('occupied_percentage', 'content_file_id')
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
