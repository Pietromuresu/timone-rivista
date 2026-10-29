<?php

namespace App\Support\Comments;

use App\Enums\UserRole;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Chi riceve la mail di un commento (2026-10-29): utenti scelti
 * nell'elenco, oppure «tutti gli utenti di questa rivista», più chi è
 * menzionato con @nome nel testo. Mai l'autore, mai due volte la stessa
 * persona, mai chi non vede il numero (un id manipolato nel browser viene
 * ignorato: si sceglie solo tra eligible()).
 */
final class CommentRecipients
{
    /**
     * Chi vede il numero (stessa regola di User::canAccessMagazine()):
     * utenti assegnati alla rivista + admin. Ordinati per nome per
     * l'elenco del modulo.
     *
     * @return Collection<int, User>
     */
    public static function eligible(Issue $issue): Collection
    {
        return User::query()
            ->where(fn ($query) => $query
                ->where('role', UserRole::Admin->value)
                ->orWhereHas('magazines', fn ($magazines) => $magazines->whereKey($issue->magazine_id)))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int>  $selectedIds
     * @return Collection<int, User>
     */
    public static function resolve(Issue $issue, User $author, array $selectedIds, bool $everyone, string $body): Collection
    {
        $candidates = self::eligible($issue)->reject(fn (User $user) => $user->id === $author->id);
        $selectedIds = array_map('intval', $selectedIds);

        $chosen = $everyone ? $candidates : $candidates->filter(fn (User $user) => in_array($user->id, $selectedIds, true));

        return $chosen->concat(self::mentioned($body, $candidates))->unique('id')->values();
    }

    /**
     * Menzioni: «@Mario Rossi» (nome completo, maiuscole indifferenti) o
     * «@MarioRossi» (senza spazi). Il nome completo e non il solo nome di
     * battesimo: due «Mario» nella stessa redazione farebbero arrivare la
     * mail alla persona sbagliata. Dopo il nome non deve seguire una
     * lettera o una cifra («@Marione» non è «@Mario»).
     *
     * @param  Collection<int, User>  $candidates
     * @return Collection<int, User>
     */
    public static function mentioned(string $body, Collection $candidates): Collection
    {
        if (! str_contains($body, '@')) {
            return collect();
        }

        return $candidates->filter(function (User $user) use ($body) {
            foreach (array_unique([$user->name, str_replace(' ', '', $user->name)]) as $name) {
                if ($name !== '' && preg_match('/@'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu', $body) === 1) {
                    return true;
                }
            }

            return false;
        })->values();
    }
}
