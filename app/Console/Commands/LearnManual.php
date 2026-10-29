<?php

namespace App\Console\Commands;

use App\Support\Manual\ManualLearningPublisher;
use Illuminate\Console\Command;

/**
 * `php artisan manuale:apprendi` (2026-10-29): il giro notturno
 * dell'assistente. Ricalcola da zero correzioni e domande imparate dai voti
 * e le pubblica solo se il set di riferimento resta verde
 * (App\Support\Manual\ManualLearningPublisher). Programmato ogni notte in
 * routes/console.php; si può lanciare a mano, non fa danni rilanciarlo.
 *
 * Esce con errore se il cancello blocca la pubblicazione: chi lo lancia a
 * mano lo vede subito, e il log dello scheduler lo segnala.
 */
class LearnManual extends Command
{
    protected $signature = 'manuale:apprendi';

    protected $description = 'Ricalcola dai voti le correzioni dell\'assistente del manuale e le pubblica se il set di riferimento passa';

    public function handle(): int
    {
        $summary = ManualLearningPublisher::fromConfig()->run(now());

        $this->info(sprintf(
            'Voti considerati: %d. Correzioni: %d (di cui %d risposte probabilmente sbagliate). Domande imparate: %d.',
            $summary['voti'], $summary['correzioni'], $summary['sospette'], $summary['domande_imparate'],
        ));

        if ($summary['pubblicato']) {
            $this->info('Set di riferimento verde: pesi pubblicati.');

            return self::SUCCESS;
        }

        $this->error('Set di riferimento NON verde: pesi nuovi non pubblicati, restano quelli di prima.');
        $this->table(['Domanda', 'Pagina attesa', 'Primi tre'], array_map(
            fn (array $f) => [$f['domanda'], $f['attesa'], implode(', ', $f['primi'])],
            $summary['fallimenti'],
        ));

        return self::FAILURE;
    }
}
