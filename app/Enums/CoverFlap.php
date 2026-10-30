<?php

namespace App\Enums;

/**
 * Un battente di copertina (2026-10-30): due pagine, prima della I
 * (battente di I) o dopo la IV (battente di IV). Si aggiunge e si toglie
 * sempre intero (App\Support\IssueCovers).
 */
enum CoverFlap: string
{
    case I = 'I';
    case IV = 'IV';

    public function label(): string
    {
        return "Battente {$this->value}";
    }

    /** Le due pagine, in ordine di sfoglio. @return array{CoverSlot, CoverSlot} */
    public function slots(): array
    {
        return match ($this) {
            self::I => [CoverSlot::BattenteI1, CoverSlot::BattenteI2],
            self::IV => [CoverSlot::BattenteIV1, CoverSlot::BattenteIV2],
        };
    }
}
