<?php

namespace App\Console\Commands;

use App\Support\Covers\FlapConversion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * `php artisan timone:battenti-dry-run` (2026-10-30): conteggi della
 * conversione bandella → battente di I, SENZA scrivere nulla. Prima di
 * `migrate` mostra «adesso» e «dopo la migrazione» (calcolato dalle stesse
 * regole di FlapConversion::apply()); dopo, rilanciato, mostra lo stato
 * reale, da confrontare con la previsione.
 */
class FlapsDryRun extends Command
{
    protected $signature = 'timone:battenti-dry-run';

    protected $description = 'Conteggi prima/dopo della conversione dalla bandella ai battenti (nessuna scrittura)';

    public function handle(): int
    {
        $now = FlapConversion::counts();
        $migrated = Schema::hasColumn('magazines', 'default_cover_flaps');

        $after = $now;
        $after['numeri con battente I'] += $now['numeri con bandella'];
        $after['pagine di battente con contenuti'] += $now['bandelle con contenuti'];

        foreach (['numeri con bandella', 'bandelle con contenuti', 'bandelle con file di pagina', 'bandelle con stato o note', 'bandelle bloccate', 'bandelle con commenti'] as $key) {
            $after[$key] = 0;
        }

        if (! $migrated) {
            $after['riviste con default battente I'] = $now['riviste con 6 pagine di copertina'];
            $after['riviste con default entrambi i battenti'] = $now['riviste con 8 o più pagine di copertina'];
        }

        $this->line($migrated
            ? 'Migrazione dei battenti già applicata: «adesso» è lo stato reale.'
            : 'Migrazione dei battenti non ancora applicata: «dopo» è la previsione.');

        $this->table(['', 'adesso', 'dopo la migrazione'], collect($now)->map(fn (int $value, string $key) => [$key, $value, $after[$key]])->values()->all());

        $this->line('Ogni bandella diventa la pagina 1 del battente di I (contenuti, file, stato, blocco e commenti restano), la pagina 2 nasce vuota; il peso non cambia.');

        return self::SUCCESS;
    }
}
