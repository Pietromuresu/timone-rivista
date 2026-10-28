<?php

namespace App\Support\Deletion;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Secondo passaggio della conferma di un'eliminazione (revisione 2, Fase
 * 4.2–4.3): password dell'admin verificata lato server con Hash::check e
 * nome digitato come su GitHub. I tentativi di password sbagliata sono
 * limitati (5 al minuto per admin): la modale non diventa un modo per
 * indovinare la password di una sessione lasciata aperta.
 */
final class DeletionConfirmation
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    /** @throws ValidationException */
    public static function verify(User $admin, string $password, string $typed, DeletionTarget $target): void
    {
        $key = self::key($admin);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'password' => 'Troppi tentativi con la password sbagliata: riprova tra '.RateLimiter::availableIn($key).' secondi.',
            ]);
        }

        if (trim($typed) !== $target->confirmationText()) {
            throw ValidationException::withMessages([
                'typed' => 'Il testo non corrisponde: scrivi esattamente «'.$target->confirmationText().'».',
            ]);
        }

        if (! Hash::check($password, $admin->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['password' => 'Password sbagliata.']);
        }

        RateLimiter::clear($key);
    }

    private static function key(User $admin): string
    {
        return 'dangerous-delete:'.$admin->id;
    }
}
