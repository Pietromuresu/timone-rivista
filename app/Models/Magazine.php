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
        // Default del calcolo peso (App\Support\Weight\WeightParameters::FIELDS):
        // copiati su ogni NUOVO numero, mai riletti dai numeri esistenti.
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
    ];

    protected function casts(): array
    {
        return [
            'periodicity' => Periodicity::class,
            'ad_threshold_percentage' => 'decimal:2',
            'battente_counted_in_total' => 'boolean',
            // Parametri peso: solo gli interi hanno un cast. I decimal restano
            // grezzi (stringa/float a seconda del driver) di proposito: il cast
            // `decimal:2` produce la deprecation di brick/math già documentata
            // in HANDOFF.md, e la conversione a float avviene comunque in un
            // punto solo (App\Support\Weight\MagazineWeightInput::fromArray).
            'formato_base_mm' => 'integer',
            'formato_altezza_mm' => 'integer',
            'pagine_copertina' => 'integer',
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
