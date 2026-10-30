<?php

namespace App\Enums;

/**
 * Quali battenti ha un numero (2026-10-30): la scelta dei default della
 * rivista (`magazines.default_cover_flaps`) e del modulo «Nuovo numero».
 * Sostituisce il numero «Pagine copertina» (4/6) dei default: con due
 * battenti 6 facciate non dicono più quale dei due. Le facciate di
 * copertina per il peso (`pagine_copertina`) ne derivano.
 */
enum CoverFlaps: string
{
    case None = 'nessuno';
    case I = 'I';
    case IV = 'IV';
    case Both = 'entrambi';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Nessun battente',
            self::I => 'Battente I',
            self::IV => 'Battente IV',
            self::Both => 'Battente I e battente IV',
        };
    }

    /** @return list<CoverFlap> */
    public function flaps(): array
    {
        return match ($this) {
            self::None => [],
            self::I => [CoverFlap::I],
            self::IV => [CoverFlap::IV],
            self::Both => [CoverFlap::I, CoverFlap::IV],
        };
    }

    /** Facciate di copertina: 4 più 2 per battente. */
    public function coverPages(): int
    {
        return 4 + 2 * count($this->flaps());
    }

    /**
     * Da un vecchio numero di facciate (prima del 2026-10-30): 6 voleva dire
     * «con la bandella», che è diventata il battente di I.
     */
    public static function fromCoverPages(?int $pages): self
    {
        return match (true) {
            ($pages ?? 4) >= 8 => self::Both,
            ($pages ?? 4) >= 6 => self::I,
            default => self::None,
        };
    }
}
