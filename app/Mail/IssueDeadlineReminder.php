<?php

namespace App\Mail;

use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IssueDeadlineReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Issue $issue)
    {
        // Solo l'id sopravvive alla serializzazione in coda (SerializesModels
        // rilegge il model fresco quando il job viene processato) — se
        // l'issue viene cancellata tra l'accodamento e l'invio, il job fallirà
        // in modo esplicito invece di spedire dati stantii, comportamento
        // di default di Laravel per i model in coda.
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Scadenza stampa in avvicinamento — {$this->issue->magazine->name} {$this->issue->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.issue-deadline-reminder',
            with: [
                'issue' => $this->issue,
                'magazine' => $this->issue->magazine,
                'daysRemaining' => (int) now()->startOfDay()->diffInDays($this->issue->print_deadline_at->copy()->startOfDay(), false),
            ],
        );
    }
}
