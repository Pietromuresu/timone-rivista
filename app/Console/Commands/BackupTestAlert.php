<?php

namespace App\Console\Commands;

use App\Mail\BackupAlert;
use App\Support\Backup\BackupSettings;
use App\Support\Backup\BackupStatusDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Mail di prova agli indirizzi di BACKUP_ALERT_EMAIL (2026-10-30). Esiste
 * perché senza un SMTP vero gli avvisi del backup finiscono nel log e non li
 * vede nessuno: è un passo della checklist di attivazione
 * (docs/OPERATIONS.md). La mail passa dalla coda come gli avvisi veri, così
 * la prova copre anche il worker.
 */
class BackupTestAlert extends Command
{
    protected $signature = 'timone:backup-test-alert
        {--to= : Indirizzi a cui mandarla (separati da virgola) al posto di BACKUP_ALERT_EMAIL}';

    protected $description = 'Manda una mail di prova agli indirizzi degli avvisi del backup (BACKUP_ALERT_EMAIL)';

    public function handle(): int
    {
        $directory = BackupStatusDirectory::fromConfig();
        $override = (string) $this->option('to');

        if ($override !== '') {
            [$recipients, $invalid] = BackupSettings::parseRecipients($override);
        } else {
            $settings = $directory->settings();
            if ($settings === null) {
                $this->error('Non trovo le impostazioni del backup in '.$directory->path().'/settings.json.');
                $this->line('Il servizio backup-agent non è attivo o non è mai partito: vedi docs/OPERATIONS.md, «Attivare il backup».');
                $this->line('Per provare comunque l\'invio: php artisan timone:backup-test-alert --to=indirizzo@esempio.it');

                return self::FAILURE;
            }
            $recipients = $settings->alertRecipients;
            $invalid = $settings->invalidRecipients;
        }

        if ($invalid !== []) {
            $this->error('Indirizzi non validi in BACKUP_ALERT_EMAIL: '.implode(', ', $invalid));

            return self::FAILURE;
        }
        if ($recipients === []) {
            $this->error('BACKUP_ALERT_EMAIL è vuota in backup.env: nessuno riceverebbe gli avvisi.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER={$mailer}: la mail NON parte davvero, finisce solo nel registro dell'app. Configura l'SMTP in .env (MAIL_*).");
        }

        Mail::to($recipients)->queue(new BackupAlert(
            headline: 'mail di prova',
            details: [
                'Questa è una prova: il backup non ha nessun problema.',
                'Se la ricevi, gli avvisi del backup arriveranno a questi indirizzi: '.implode(', ', $recipients).'.',
            ],
            whatToDo: 'niente. Se un destinatario non l\'ha ricevuta, controlla BACKUP_ALERT_EMAIL in backup.env e la cartella spam.',
        ));

        $this->info('Mail di prova accodata per: '.implode(', ', $recipients).'.');
        $this->line('Arriva entro un minuto se il worker gira. Se non arriva: docker compose logs worker e php artisan queue:failed.');

        return self::SUCCESS;
    }
}
