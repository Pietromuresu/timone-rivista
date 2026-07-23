<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\Periodicity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected function casts(): array
    {
        return [
            'periodicity' => Periodicity::class,
            'ad_threshold_percentage' => 'decimal:2',
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
