<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\PageComment;
use App\Models\User;
use App\Support\Permissions\TimoneAbilities;

/**
 * Commenti (2026-10-29, scelta dell'utente in Fase 0): leggono tutti quelli
 * che vedono il numero; scrivono admin, redattore e commerciale su
 * qualunque pagina (commentare non sposta nulla); la sola lettura legge e
 * basta. Modifica: solo l'autore. Eliminazione: l'autore o un admin.
 */
class PageCommentPolicy
{
    public function create(User $user, Issue $issue): bool
    {
        return TimoneAbilities::for($user, $issue)->canComment();
    }

    public function update(User $user, PageComment $comment): bool
    {
        return $comment->user_id === $user->id && $this->create($user, $comment->issue);
    }

    public function delete(User $user, PageComment $comment): bool
    {
        if (! $user->canAccessMagazine($comment->issue->magazine)) {
            return false;
        }

        return $user->isAdmin() || ($comment->user_id === $user->id && $this->create($user, $comment->issue));
    }
}
