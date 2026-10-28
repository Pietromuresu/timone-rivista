<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Redattore = 'redattore';
    case Commerciale = 'commerciale';
    case SolaLettura = 'sola_lettura';

    /**
     * Lavora su tutto il timone: pagine (ordine, stati, blocchi, gruppi,
     * pagine totali), articoli e pubblicità. Revisione 2, Fase 4
     * (2026-10-28): unico punto che dice QUALI ruoli lo fanno — le policy e
     * App\Support\Permissions\TimoneAbilities leggono da qui.
     */
    public function editsTimone(): bool
    {
        return in_array($this, [self::Admin, self::Redattore], true);
    }

    /**
     * Crea, modifica e sposta le pubblicità (Fase 4: il Commerciale, prima
     * in sola lettura, lavora solo su queste — scelta dell'utente).
     */
    public function handlesAds(): bool
    {
        return $this->editsTimone() || $this === self::Commerciale;
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Redattore => 'Redattore',
            self::Commerciale => 'Commerciale',
            self::SolaLettura => 'Sola lettura',
        };
    }
}
