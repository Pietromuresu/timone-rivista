<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Gestione utenti/ruoli per rivista: solo un Admin può vedere,
     * creare o modificare gli account e la loro assegnazione alle
     * riviste — stesso livello di `MagazinePolicy::create/update`.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Revisione 2, Fase 4.3 (2026-10-28): solo un admin, mai sé stesso (si
     * chiuderebbe fuori da solo), mai l'ultimo admin rimasto (nessuno
     * potrebbe più gestire utenti e riviste). Il motivo torna al modulo.
     */
    public function delete(User $user, User $model): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny('Solo un amministratore può eliminare utenti.');
        }

        if ($user->is($model)) {
            return Response::deny('Non puoi eliminare il tuo stesso account.');
        }

        if ($model->isAdmin() && User::where('role', UserRole::Admin->value)->count() <= 1) {
            return Response::deny("È l'ultimo amministratore: nomina prima un altro admin.");
        }

        return Response::allow();
    }
}
