<?php

namespace App\Policies;

use App\Models\User;

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
}
