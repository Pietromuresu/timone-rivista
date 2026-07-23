<?php

use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Canale presence per Issue
|--------------------------------------------------------------------------
|
| Ogni numero (Issue) di ogni rivista ha un proprio canale presence a cui
| si iscrivono solo gli utenti abilitati sulla rivista di appartenenza.
| Il payload restituito alimenta gli eventi here/joining/leaving di Echo
| (usati per mostrare gli avatar degli utenti online sulla vista timone).
|
| NB: il canale va registrato SENZA il prefisso "presence-": è Echo
| (lato client, con Echo.join()) ad aggiungerlo automaticamente. Il nome
| registrato qui deve combaciare con quello dopo la normalizzazione del
| prefisso fatta internamente da Laravel.
*/
Broadcast::channel('issue.{issueId}', function (User $user, int $issueId) {
    $issue = Issue::find($issueId);

    if (! $issue || ! $user->canAccessMagazine($issue->magazine)) {
        return null;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role->value,
    ];
});
