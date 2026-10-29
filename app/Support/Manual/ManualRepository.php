<?php

namespace App\Support\Manual;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Le pagine del manuale utente (2026-10-29): un file per argomento in
 * `resources/manuale/*.md` (cartella in config('timone.manual_dir')).
 *
 * Perché lì: le pagine fanno parte dell'applicazione (le legge il server a
 * ogni richiesta e, dalla Fase 2, il build dell'indice di ricerca), non
 * della documentazione per sviluppatori in docs/. Il percorso è in
 * config/timone.php e non in config/app.php (file protetto, CLAUDE.md
 * regola 4): `app.manual_path` resta e punta a MANUALE_UTENTE.md, che ora è
 * il file unico generato da `php artisan manuale:genera`.
 *
 * Le categorie hanno un ordine fisso (CATEGORIES); dentro una categoria
 * conta il campo `ordine` del front matter.
 */
final class ManualRepository
{
    public const CATEGORIES = [
        'Inizia qui',
        'Lavorare sul timone',
        'Contenuti e pubblicità',
        'Copertine e bandella',
        'Numero e rivista',
        'Esportare e stampare',
        'Riferimento',
    ];

    /** @var Collection<string, ManualPage>|null */
    private ?Collection $pages = null;

    public function __construct(private readonly string $directory) {}

    public static function fromConfig(): self
    {
        return new self((string) config('timone.manual_dir'));
    }

    public function exists(): bool
    {
        return File::isDirectory($this->directory) && File::glob($this->directory.'/*.md') !== [];
    }

    /**
     * Tutte le pagine, in ordine di categoria e poi di `ordine`.
     *
     * @return Collection<string, ManualPage> per slug
     *
     * @throws InvalidArgumentException pagina non valida, slug ripetuto o categoria sconosciuta
     */
    public function all(): Collection
    {
        if ($this->pages !== null) {
            return $this->pages;
        }

        $pages = collect(File::glob($this->directory.'/*.md'))
            ->map(fn (string $path) => ManualPage::parse(File::get($path), basename($path)));

        foreach ($pages as $page) {
            if (! in_array($page->categoria, self::CATEGORIES, true)) {
                throw new InvalidArgumentException("{$page->slug}: categoria «{$page->categoria}» sconosciuta.");
            }
        }

        if (($duplicates = $pages->countBy(fn (ManualPage $page) => $page->slug)->filter(fn ($n) => $n > 1)->keys()->all()) !== []) {
            throw new InvalidArgumentException('Slug ripetuti nel manuale: '.implode(', ', $duplicates).'.');
        }

        return $this->pages = $pages
            ->sortBy([
                fn (ManualPage $a, ManualPage $b) => array_search($a->categoria, self::CATEGORIES, true) <=> array_search($b->categoria, self::CATEGORIES, true),
                fn (ManualPage $a, ManualPage $b) => $a->ordine <=> $b->ordine,
            ])
            ->keyBy(fn (ManualPage $page) => $page->slug);
    }

    public function find(string $slug): ?ManualPage
    {
        return $this->all()->get($slug);
    }

    /** @return Collection<string, Collection<int, ManualPage>> per categoria, nell'ordine di CATEGORIES */
    public function byCategory(): Collection
    {
        return $this->all()->values()->groupBy(fn (ManualPage $page) => $page->categoria);
    }
}
