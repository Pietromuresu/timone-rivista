<?php

namespace App\Models;

use App\Enums\PageContentType;
use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'position',
        'content_type',
        'status',
        'notes',
        'locked_at',
        'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'content_type' => PageContentType::class,
            'status' => PageStatus::class,
            'locked_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * §6.6 dello spec: "una pagina bloccata non può essere spostata,
     * eliminata, sovrascritta, o modificata". Copre solo l'azione diretta
     * sulla pagina bloccata stessa — non impedisce che la sua posizione
     * cambi come effetto collaterale dello spostamento di un'altra pagina
     * che le "slitta attraverso" (richiederebbe riscrivere l'algoritmo di
     * riordino per trattare le pagine bloccate come ostacoli fissi, non
     * solo una guardia sull'id target — fuori scopo, documentato in
     * HANDOFF.md).
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Contenuti assegnati a questa pagina, con la percentuale di spazio occupato.
     */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'page_content')
            ->using(PageContent::class)
            ->withPivot('occupied_percentage')
            ->withTimestamps();
    }

    /**
     * File PDF caricati per questa pagina, dal più recente.
     */
    public function files(): HasMany
    {
        return $this->hasMany(PageFile::class)->latest();
    }

    /**
     * Ultimo PDF caricato: quello mostrato come anteprima principale sulla card.
     */
    public function latestFile(): ?PageFile
    {
        return $this->files()->first();
    }

    public function reorderLogs(): HasMany
    {
        return $this->hasMany(PageReorderLog::class);
    }
}
