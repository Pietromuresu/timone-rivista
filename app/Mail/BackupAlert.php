<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mail di avviso sul backup (2026-10-30). Una sola classe per tutti i tipi
 * di avviso: cambiano titolo, dettagli e «cosa fare», non la forma. Sempre
 * in coda (ShouldQueue), come le altre mail dell'app: chi la manda (un
 * comando dello scheduler) non deve aspettare l'SMTP.
 */
class BackupAlert extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $details  righe di dettaglio (già in italiano)
     */
    public function __construct(
        public string $headline,
        public array $details,
        public string $whatToDo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] Backup: '.$this->headline,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.backup-alert',
            with: [
                'appUrl' => config('app.url'),
            ],
        );
    }
}
