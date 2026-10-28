<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Cancella da disco i file di ciò che è appena stato eliminato (revisione 2,
 * Fase 4.2, 2026-10-28). Accodato con afterCommit() DENTRO la transazione
 * dell'eliminazione: se la transazione fallisce il job non parte (nessun
 * file perso per record ancora vivi), se riesce parte dopo il commit
 * (nessun record senza file). Un file già assente non è un errore: il
 * risultato voluto è comunque "non c'è più".
 */
class DeleteStoredFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param  list<array{disk: string, path: string}>  $paths */
    public function __construct(public array $paths, public string $reason)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $deleted = 0;

        foreach (collect($this->paths)->groupBy('disk') as $disk => $files) {
            Storage::disk($disk)->delete($files->pluck('path')->all());
            $deleted += $files->count();
        }

        Log::info('stored_files_deleted', ['reason' => $this->reason, 'files' => $deleted]);
    }
}
