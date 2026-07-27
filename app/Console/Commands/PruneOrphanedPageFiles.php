<?php

namespace App\Console\Commands;

use App\Models\PageFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Il cascadeOnDelete() su page_files (Page::delete(), inclusa la riduzione
 * pagine di Grid::resizePages(), e Issue::delete()) elimina solo le righe
 * dal database: è un vincolo a livello di foreign key SQL, non passa mai
 * dagli eventi Eloquent del modello, quindi non c'è un punto naturale dove
 * agganciare "cancella anche il file fisico". Comando on-demand invece di
 * un tentativo di intercettare la cascata — stesso approccio già usato per
 * il backup del database (`docker compose --profile tools run --rm
 * backup`): nessuno scheduler configurato in Docker in questa sessione (ne
 * servirebbe uno nuovo, fuori scope), quindi resta manuale per ora.
 */
class PruneOrphanedPageFiles extends Command
{
    protected $signature = 'pagefiles:prune-orphaned {--dry-run : Mostra solo cosa verrebbe eliminato, senza cancellare nulla}';

    protected $description = "Elimina i PDF/thumbnail rimasti orfani su disco dopo la cancellazione di una pagina o di un'issue";

    public function handle(): int
    {
        $disk = Storage::disk('local');

        $referenced = PageFile::query()
            ->select('path', 'thumbnail_path')
            ->get()
            ->flatMap(fn (PageFile $file) => array_filter([$file->path, $file->thumbnail_path]))
            ->flip();

        $orphaned = collect($disk->allFiles('pages'))
            ->reject(fn (string $path) => $referenced->has($path));

        if ($orphaned->isEmpty()) {
            $this->info('Nessun file orfano trovato.');

            return self::SUCCESS;
        }

        $this->info("{$orphaned->count()} file orfani trovati.");

        $dryRun = (bool) $this->option('dry-run');

        foreach ($orphaned as $path) {
            $this->line(($dryRun ? '  [dry-run] eliminerei: ' : '  eliminato: ').$path);

            if (! $dryRun) {
                $disk->delete($path);
            }
        }

        if (! $dryRun) {
            $this->info('Pulizia completata.');
        }

        return self::SUCCESS;
    }
}
