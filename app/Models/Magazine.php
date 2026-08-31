<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\Periodicity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Magazine extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'periodicity',
        'color',
        'ad_threshold_percentage',
        'notes',
        'battente_counted_in_total',
        'redattore_id',
    ];

    protected function casts(): array
    {
        return [
            'periodicity' => Periodicity::class,
            'ad_threshold_percentage' => 'decimal:2',
            'battente_counted_in_total' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Magazine $magazine) {
            if (empty($magazine->slug)) {
                $magazine->slug = Str::slug($magazine->name);
            }
        });
    }

    /**
     * Uscite periodiche della rivista.
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Rubriche/sezioni con cui raggruppare gli articoli di questa rivista.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Utenti abilitati a lavorare su questa rivista.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Redattore di riferimento (Fase 5.2) — destinatario delle mail di
     * alert/sollecito (Fase 5.3). FK dedicata, non una colonna sul pivot
     * `magazine_user`: quel pivot esprime accesso generico (chiunque
     * lavori sulla rivista, qualunque ruolo), il redattore di riferimento è
     * un concetto diverso — "chi è il responsabile", un solo utente per
     * rivista, che resta comunque uno degli utenti con accesso (vedi
     * Magazines\Edit::save(), che sincronizza anche il pivot).
     */
    public function redattore(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redattore_id');
    }

    /**
     * L'uscita attualmente in lavorazione (la più recente non chiusa).
     */
    public function currentIssue(): ?Issue
    {
        return $this->issues()
            ->where('status', '!=', IssueStatus::Chiuso->value)
            ->latest('issue_date')
            ->first();
    }
}
