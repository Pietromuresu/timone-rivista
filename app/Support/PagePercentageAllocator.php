<?php

namespace App\Support;

class PagePercentageAllocator
{
    /**
     * Spazio libero rimanente su una pagina, date le percentuali già occupate.
     *
     * @param  array<int, float>  $occupiedPercentages
     */
    public static function freeSpace(array $occupiedPercentages): float
    {
        return round(100.0 - array_sum($occupiedPercentages), 2);
    }

    /**
     * Determina se una percentuale candidata può essere applicata senza
     * superare il 100% della pagina, date le altre percentuali già occupate
     * (esclusa quella del contenuto eventualmente in aggiornamento).
     *
     * @param  array<int, float>  $otherOccupiedPercentages
     */
    public static function fits(array $otherOccupiedPercentages, float $candidatePercentage): bool
    {
        if ($candidatePercentage <= 0) {
            return false;
        }

        return round(array_sum($otherOccupiedPercentages) + $candidatePercentage, 2) <= 100.0;
    }
}
