<?php

namespace App\Notifications;

use App\Models\PageComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Mail per un nuovo commento su una pagina (2026-10-29). In coda come i
 * solleciti di scadenza (Redis + container worker): salvare un commento non
 * aspetta il server di posta. Destinatari decisi da
 * App\Support\Comments\CommentRecipients.
 */
class PageCommentPosted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PageComment $comment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Commento eliminato prima che il worker arrivasse a spedire: niente
     * mail con un testo che nel timone non c'è più.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return PageComment::whereKey($this->comment->id)->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $comment = $this->comment->loadMissing(['issue.magazine', 'page', 'author']);
        $issue = $comment->issue;
        $magazine = $issue->magazine;
        $url = route('issues.show', [$magazine, $issue]).($comment->page_id !== null ? '?commenti='.$comment->page_id : '');

        return (new MailMessage)
            ->subject("Nuovo commento — {$magazine->name} {$issue->title}, ".$comment->pageName())
            ->greeting('Nuovo commento sul timone')
            ->line("**Rivista:** {$magazine->name}")
            ->line("**Numero:** {$issue->title}")
            ->line('**Pagina:** '.Str::ucfirst($comment->pageName()))
            ->line('**Autore:** '.$comment->authorName())
            ->line('> '.str_replace("\n", "\n> ", e($comment->body)))
            ->action('Apri la pagina nel timone', $url);
    }
}
