<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\Page;
use App\Models\User;
use App\Support\Permissions\TimoneAbilities;

class IssuePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Issue $issue): bool
    {
        return $user->canAccessMagazine($issue->magazine);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role->editsTimone();
    }

    /**
     * Tutto il timone del numero (pagine, stati, gruppi, articoli,
     * impostazioni): Admin e Redattore con accesso alla rivista. Regola in
     * TimoneAbilities (revisione 2, Fase 4.1), come le due sotto.
     */
    public function update(User $user, Issue $issue): bool
    {
        return TimoneAbilities::for($user, $issue)->editsTimone();
    }

    /**
     * Lavorare almeno sulle pubblicità del numero (revisione 2, Fase 4.1):
     * vale anche per il Commerciale. Apre modulo, caricamento multiplo e
     * modale di modifica; ogni azione su un contenuto controlla poi
     * ContentPolicy sul contenuto stesso.
     */
    public function workOnAds(User $user, Issue $issue): bool
    {
        return TimoneAbilities::for($user, $issue)->handlesAds();
    }

    /**
     * Spostare queste pagine (una, un gruppo, una selezione, uno scambio):
     * al Commerciale solo se nessuna contiene articoli.
     *
     * @param  iterable<Page>  $pages
     */
    public function movePages(User $user, Issue $issue, iterable $pages): bool
    {
        return TimoneAbilities::for($user, $issue)->canMovePages($pages);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Issue $issue): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Issue $issue): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Issue $issue): bool
    {
        return false;
    }
}
