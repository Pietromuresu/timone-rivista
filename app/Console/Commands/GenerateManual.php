<?php

namespace App\Console\Commands;

use App\Support\Manual\ManualCompiler;
use App\Support\Manual\ManualRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * `php artisan manuale:genera [--check]` (2026-10-29): riscrive
 * MANUALE_UTENTE.md concatenando le pagine di resources/manuale/. Con
 * --check non scrive: esce con errore se il file non è aggiornato (lo
 * stesso controllo fa un test, così chi cambia una pagina e dimentica di
 * rigenerare se ne accorge nella suite).
 */
class GenerateManual extends Command
{
    protected $signature = 'manuale:genera {--check : Controlla soltanto che MANUALE_UTENTE.md sia aggiornato}';

    protected $description = 'Genera MANUALE_UTENTE.md dalle pagine in resources/manuale/';

    public function handle(): int
    {
        $path = (string) config('app.manual_path');
        $compiled = ManualCompiler::compile(ManualRepository::fromConfig());

        if ($this->option('check')) {
            if (! File::exists($path) || File::get($path) !== $compiled) {
                $this->error('MANUALE_UTENTE.md non è aggiornato: esegui «php artisan manuale:genera».');

                return self::FAILURE;
            }

            $this->info('MANUALE_UTENTE.md è aggiornato.');

            return self::SUCCESS;
        }

        File::put($path, $compiled);
        $this->info('Scritto '.$path.' ('.ManualRepository::fromConfig()->all()->count().' pagine).');

        return self::SUCCESS;
    }
}
