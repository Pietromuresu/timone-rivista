<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan manuale:pulisci` (2026-10-29): i voti dell'assistente e le
 * domande senza risposta si tengono 12 mesi, poi si cancellano. Un voto di
 * un anno fa vale comunque meno di 1/16 (decadimento con mezza vita di 90
 * giorni): toglierlo non cambia quasi nulla e non si tengono dati che non
 * servono. Anche i giri del job più vecchi di 12 mesi.
 */
class PruneManualFeedback extends Command
{
    public const MONTHS = 12;

    protected $signature = 'manuale:pulisci';

    protected $description = 'Cancella i voti dell\'assistente del manuale e le domande senza risposta più vecchi di 12 mesi';

    public function handle(): int
    {
        $limit = now()->subMonths(self::MONTHS);

        $votes = DB::table('manual_feedback')->where('created_at', '<', $limit)->delete();
        $unanswered = DB::table('manual_unanswered')->where('ultima_volta', '<', $limit)->delete();
        $runs = DB::table('manual_learning_runs')->where('created_at', '<', $limit)->delete();

        $this->info("Cancellati: {$votes} voti, {$unanswered} domande senza risposta, {$runs} giri del job.");

        return self::SUCCESS;
    }
}
