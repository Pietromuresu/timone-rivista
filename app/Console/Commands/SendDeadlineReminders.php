<?php

namespace App\Console\Commands;

use App\Mail\IssueDeadlineReminder;
use App\Models\Issue;
use App\Support\DeadlineReminderChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Schedulato (vedi routes/console.php) invece che un cron esterno, per
 * restare nello spirito "Docker self-hosted, minimo sysadmin" già scelto
 * per il resto del progetto — vedi anche il servizio `scheduler` aggiunto a
 * docker-compose.yml in questa stessa fase (Fase 5.3), senza il quale
 * questo comando non avrebbe mai modo di essere invocato in produzione.
 */
class SendDeadlineReminders extends Command
{
    protected $signature = 'timone:send-deadline-reminders';

    protected $description = 'Invia la mail di sollecito al redattore di riferimento per le issue con scadenza stampa in avvicinamento';

    public function handle(): int
    {
        $thresholdDays = (int) config('timone.deadline_reminder_days_before');

        $issues = Issue::query()
            ->whereNotNull('print_deadline_at')
            ->whereNull('deadline_reminder_sent_at')
            ->with('magazine.redattore')
            ->get();

        $sent = 0;

        foreach ($issues as $issue) {
            if (! DeadlineReminderChecker::isDue($issue, now(), $thresholdDays)) {
                continue;
            }

            Mail::to($issue->magazine->redattore->email)->queue(new IssueDeadlineReminder($issue));

            // Aggiornato subito (non atteso l'invio reale, che è in coda):
            // l'accodamento è già l'atto che deve essere deduplicato, non
            // la consegna finale — altrimenti un secondo giro di questo
            // comando prima che il worker processi la coda accoderebbe la
            // stessa mail due volte.
            $issue->update(['deadline_reminder_sent_at' => now()]);

            $sent++;
        }

        $this->info("Solleciti accodati: {$sent}");

        return self::SUCCESS;
    }
}
