<?php

namespace App\Support\Comments;

use App\Models\Page;
use App\Models\PageComment;
use App\Models\User;
use App\Notifications\PageCommentPosted;
use App\Support\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Scritture sui commenti (2026-10-29). Ogni scrittura aggiorna
 * issues.comments_updated_at: è l'unico segnale che gli altri utenti
 * ricevono (polling, nessun broadcast). I permessi li controlla chi chiama
 * (PageCommentPolicy), qui solo il dominio.
 */
final class PageCommentWriter
{
    /**
     * @param  list<int>  $recipientIds
     * @return array{comment: PageComment, recipients: Collection<int, User>}
     */
    public static function post(Page $page, User $author, string $body, array $recipientIds, bool $everyone): array
    {
        $comment = PageComment::create([
            'issue_id' => $page->issue_id,
            'page_id' => $page->id,
            'user_id' => $author->id,
            'body' => trim($body),
            // Nome di adesso: resta se la pagina viene eliminata senza
            // passare da DeletedPageComments (vedi la migration).
            'page_label' => $page->label(),
        ]);

        $page->issue->markCommentsUpdated();

        $recipients = CommentRecipients::resolve($page->issue, $author, $recipientIds, $everyone, $body);

        // In coda (PageCommentPosted è ShouldQueue): l'invio non rallenta il salvataggio.
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new PageCommentPosted($comment));
        }

        ActivityLogger::log(
            issue: $page->issue,
            entityType: 'PageComment',
            entityId: $comment->id,
            action: 'page_comment.created',
            description: "Commento su {$page->label()}".($recipients->isNotEmpty() ? ', notificato a '.$recipients->count().' persone' : ''),
            new: ['recipientIds' => $recipients->pluck('id')->all()],
        );

        return ['comment' => $comment, 'recipients' => $recipients];
    }

    /** Nessuna nuova mail: chi è stato avvisato vede il testo aggiornato nel timone. */
    public static function update(PageComment $comment, string $body): void
    {
        $comment->update(['body' => trim($body), 'edited_at' => now()]);
        $comment->issue->markCommentsUpdated();
    }

    public static function delete(PageComment $comment): void
    {
        $comment->delete();
        $comment->issue->markCommentsUpdated();
    }
}
