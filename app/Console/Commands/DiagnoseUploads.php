<?php

namespace App\Console\Commands;

use App\Livewire\Timone\ContentCreate;
use App\Support\Uploads\UploadEnvironmentInspector;
use Illuminate\Console\Command;

/**
 * `php artisan timone:diagnose-upload` (Fase 1, bug 9, 2026-10-26): stampa
 * i controlli di App\Support\Uploads\UploadEnvironmentInspector, da
 * eseguire SUL SERVER quando "non si riesce a caricare un file". Esce con
 * codice 1 se almeno un controllo è fallito, così può essere usato anche
 * in uno script di deploy. Da riga di comando APP_URL può solo essere
 * mostrato: il confronto con l'indirizzo reale lo fa il log di un upload
 * fallito (voce «upload_failed»), che vede la richiesta vera.
 */
class DiagnoseUploads extends Command
{
    protected $signature = 'timone:diagnose-upload {--no-pdf : Salta la prova di lettura di un PDF con Imagick}';

    protected $description = 'Controlla configurazione e permessi da cui dipende il caricamento dei file';

    public function handle(): int
    {
        $results = UploadEnvironmentInspector::inspect(
            componentMaxKilobytes: ContentCreate::FILE_MAX_KILOBYTES,
            probePdf: ! $this->option('no-pdf'),
        );

        $icons = ['ok' => '✅', 'warn' => '⚠️ ', 'fail' => '❌', 'info' => 'ℹ️ '];

        foreach ($results as $result) {
            $this->line("{$icons[$result['status']]} <options=bold>{$result['check']}</>: {$result['detail']}");

            if ($result['hint'] !== '') {
                $this->line("     → {$result['hint']}");
            }
        }

        $failed = collect($results)->where('status', 'fail')->count();
        $this->newLine();
        $failed > 0
            ? $this->error("{$failed} controllo/i fallito/i: vedi docs/10-debugging.md, «Upload in produzione».")
            : $this->info('Nessun controllo fallito. Se l\'upload fallisce ancora, cerca «upload_failed» in storage/logs/laravel.log.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
