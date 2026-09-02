<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Catena" di pagine collegate permanentemente (Fase 2.3): trascinarne una
 * sposta tutte le altre insieme, senza bisogno di rifare la selezione
 * multipla ogni volta — vedi App\Support\PageGroupResolver e
 * Grid::movePage()/moveSelectedBlock(). Un gruppo esiste solo finché ha
 * almeno 2 pagine: Grid::unlinkGroup() (bottone 🔗 sul badge) lo scioglie
 * sempre per intero; App\Support\PageGroupLinker::removeFromGroup() (Punto
 * 4.6 del prompt, 2026-09-02) permette invece di rimuovere solo alcune
 * pagine lasciando le altre collegate, sciogliendo comunque il gruppo se
 * ne restano meno di 2 — le due modalità di scollegamento coesistono
 * deliberatamente, vedi App\Support\PageGroupLinker per i dettagli.
 */
class PageGroup extends Model
{
    protected $fillable = ['issue_id', 'color'];

    /**
     * Palette fissa, assegnata a rotazione in base a quanti gruppi esistono
     * già per l'issue al momento della creazione (Grid::linkSelectedPages())
     * — solo per distinguere visivamente gruppi diversi sulla stessa issue,
     * non serve unicità globale.
     */
    public const PALETTE = ['rose', 'amber', 'lime', 'cyan', 'violet', 'fuchsia'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Classi Tailwind letterali (mai concatenate a runtime): tailwind.config.js
     * scansiona app/**\/*.php proprio per poter generare il CSS di classi
     * restituite da metodi PHP come questo — stesso pattern già usato da
     * PageStatus::colorClasses()/PageContentType::colorClasses(), vedi la
     * nota nel config trovata durante il bugfix del 2026-07-31.
     */
    public function badgeClasses(): string
    {
        return match ($this->color) {
            'rose' => 'bg-rose-100 text-rose-800 border-rose-400 dark:bg-rose-900/40 dark:text-rose-300 dark:border-rose-600',
            'amber' => 'bg-amber-100 text-amber-800 border-amber-400 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-600',
            'lime' => 'bg-lime-100 text-lime-800 border-lime-400 dark:bg-lime-900/40 dark:text-lime-300 dark:border-lime-600',
            'cyan' => 'bg-cyan-100 text-cyan-800 border-cyan-400 dark:bg-cyan-900/40 dark:text-cyan-300 dark:border-cyan-600',
            'violet' => 'bg-violet-100 text-violet-800 border-violet-400 dark:bg-violet-900/40 dark:text-violet-300 dark:border-violet-600',
            'fuchsia' => 'bg-fuchsia-100 text-fuchsia-800 border-fuchsia-400 dark:bg-fuchsia-900/40 dark:text-fuchsia-300 dark:border-fuchsia-600',
            default => 'bg-gray-100 text-gray-800 border-gray-400 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-600',
        };
    }

    /**
     * Solo il colore dell'anello (per il contorno della card in griglia/
     * doppia, dove il badge testuale occuperebbe troppo spazio) — stessa
     * palette di badgeClasses(), separata perché serve una sola classe
     * `ring-*`, non l'intero set bg/text/border.
     */
    public function ringClasses(): string
    {
        return match ($this->color) {
            'rose' => 'ring-rose-400 dark:ring-rose-600',
            'amber' => 'ring-amber-400 dark:ring-amber-600',
            'lime' => 'ring-lime-400 dark:ring-lime-600',
            'cyan' => 'ring-cyan-400 dark:ring-cyan-600',
            'violet' => 'ring-violet-400 dark:ring-violet-600',
            'fuchsia' => 'ring-fuchsia-400 dark:ring-fuchsia-600',
            default => 'ring-gray-400 dark:ring-gray-600',
        };
    }
}
