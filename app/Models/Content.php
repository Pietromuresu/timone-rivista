<?php

namespace App\Models;

use App\Enums\ContentType;
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
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'archived_at' => 'datetime',
        ];
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
            ->withPivot('occupied_percentage')
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
