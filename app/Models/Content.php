<?php

namespace App\Models;

use App\Enums\ContentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Content extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'section_id',
        'type',
        'title',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
        ];
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
