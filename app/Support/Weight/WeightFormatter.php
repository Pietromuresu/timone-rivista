<?php

namespace App\Support\Weight;

/**
 * Formattazione italiana (virgola decimale, punto delle migliaia) dei
 * valori del peso. Unico punto in cui i pesi vengono arrotondati: il
 * calcolo lavora sempre sui valori pieni.
 */
final class WeightFormatter
{
    /** Peso copia, min/max, scostamenti: 1 decimale ("510,7"). */
    public static function grams(?float $grams, int $decimals = 1): string
    {
        return $grams === null ? '—' : number_format($grams, $decimals, ',', '.');
    }

    /** Peso di una pagina: 3 decimali ("2,268", "4,820"), sotto il grammo serve il millesimo. */
    public static function pageGrams(?float $grams): string
    {
        return self::grams($grams, 3);
    }

    /** Differenza di peso con segno esplicito ("+12,4", "−3,0", "0,0"). */
    public static function signedGrams(float $grams): string
    {
        $formatted = self::grams(abs($grams));

        return match (true) {
            round($grams, 1) > 0 => '+'.$formatted,
            round($grams, 1) < 0 => '−'.$formatted,
            default => $formatted,
        };
    }

    /** Pesi della tiratura: 2 decimali ("4.187,53"). */
    public static function kilograms(?float $kg): string
    {
        return $kg === null ? '—' : number_format($kg, 2, ',', '.');
    }

    /** Interi con separatore delle migliaia ("8.200"). */
    public static function integer(?int $value): string
    {
        return $value === null ? '—' : number_format($value, 0, ',', '.');
    }

    /** Percentuali, 2 decimali ("+2,35"). */
    public static function percent(?float $value, bool $signed = false): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = number_format($value, 2, ',', '.');

        return $signed && $value > 0 ? '+'.$formatted : $formatted;
    }

    /** Valore di un parametro (decimal dal DB) senza zeri finali inutili ("80", "1,5"). */
    public static function parameter(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return str_replace('.', ',', (string) (0 + $value));
    }

    /** Etichetta dello scaglione ("500 g" o "Fuori scaglione"). */
    public static function bracket(?int $bracket): string
    {
        return $bracket === null ? 'Fuori scaglione' : self::integer($bracket).' g';
    }
}
