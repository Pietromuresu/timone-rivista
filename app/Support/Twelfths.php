<?php

namespace App\Support;

/**
 * Unità di misura dello spazio in pagina (Fase 3 del batch "revisione con
 * gli utenti", 2026-10-27): il DODICESIMO di pagina, sempre un intero.
 *
 * Perché 12: è il più piccolo denominatore comune delle frazioni usate in
 * redazione e nel listino (1/4 = 3, 1/3 = 4, 1/2 = 6, 2/3 = 8, 3/4 = 9),
 * quindi ogni occupazione reale è un intero e somme/confronti sono esatti.
 * Prima si usavano percentuali decimali (33.3 + 33.3 + 33.3 = 99.9, mai
 * "pagina piena"; 52% o 70.5% dal listino che non sommavano a 100 con
 * niente).
 *
 * Questa classe è l'unico posto in cui un numero di dodicesimi diventa
 * testo ("1/3", "2 e 1/2 pagine"): viste, messaggi d'errore, log ed export
 * passano tutti da qui, così la stessa quantità si legge ovunque allo
 * stesso modo.
 */
final class Twelfths
{
    /** Capienza di una pagina. */
    public const PER_PAGE = 12;

    /**
     * Frazioni proposte nei moduli (oltre alle pagine intere): le stesse
     * del listino pubblicitario. Valore in dodicesimi => etichetta.
     */
    public const FRACTIONS = [
        3 => '1/4',
        4 => '1/3',
        6 => '1/2',
        8 => '2/3',
        9 => '3/4',
    ];

    /**
     * Frazione ridotta ai minimi termini: 6 → "1/2", 5 → "5/12", 12 → "1",
     * 0 → "0". Per valori oltre la pagina usare pagesLabel().
     */
    public static function fractionLabel(int $twelfths): string
    {
        if ($twelfths % self::PER_PAGE === 0) {
            return (string) intdiv($twelfths, self::PER_PAGE);
        }

        $divisor = self::gcd($twelfths, self::PER_PAGE);

        return ($twelfths / $divisor).'/'.(self::PER_PAGE / $divisor);
    }

    /**
     * Quantità di pagine leggibile: "1/3 di pagina", "1 pagina",
     * "3 pagine", "2 e 1/2 pagine". Una sola forma (pagine intere + "e" +
     * frazione) invece dei decimali: "2,33 pagine" non lo legge nessuno in
     * redazione, "2 e 1/3" sì.
     */
    public static function pagesLabel(int $twelfths): string
    {
        $whole = intdiv($twelfths, self::PER_PAGE);
        $rest = $twelfths % self::PER_PAGE;

        if ($whole === 0) {
            return $rest === 0 ? '0 pagine' : self::fractionLabel($rest).' di pagina';
        }

        if ($rest === 0) {
            return $whole === 1 ? '1 pagina' : "{$whole} pagine";
        }

        return "{$whole} e ".self::fractionLabel($rest).' pagine';
    }

    /**
     * Equivalente in pagine (per i soli riepiloghi numerici, es. "2.5
     * pagine equiv."): MAI usato per decidere se qualcosa ci sta — per
     * quello si confrontano gli interi.
     */
    public static function toPages(int $twelfths): float
    {
        return round($twelfths / self::PER_PAGE, 2);
    }

    /**
     * Percentuale di $part su $whole calcolata sugli interi e arrotondata
     * solo alla fine (es. carico pubblicitario): 0 se $whole è 0.
     */
    public static function percentage(int $part, int $whole): float
    {
        return $whole > 0 ? round($part * 100 / $whole, 2) : 0.0;
    }

    /**
     * Conversione da una vecchia percentuale di pagina al dodicesimo più
     * vicino (usata solo dalla migration di conversione della Fase 3):
     * 50 → 6, 33.3 → 4, 99.7 → 12. Mai 0: un contenuto assegnato occupa
     * almeno 1/12, altrimenti sparirebbe dai conteggi; mai oltre la pagina.
     */
    public static function fromPercentage(float $percentage): int
    {
        return max(1, min(self::PER_PAGE, (int) round($percentage * self::PER_PAGE / 100)));
    }

    private static function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : self::gcd($b, $a % $b);
    }
}
