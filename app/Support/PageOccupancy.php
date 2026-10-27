<?php

namespace App\Support;

use App\Enums\AdFormat;
use InvalidArgumentException;

/**
 * Occupazione TOTALE di un contenuto in dodicesimi di pagina (Fase 3,
 * 2026-10-27; prima, dal bug 5 del 2026-10-12, era un decimale in pagine:
 * 0.25/0.33/0.5/0.67 o N pagine intere, senza combinazioni come 2,5).
 * Colonna `contents.occupancy_twelfths`, valore unico per articoli e
 * pubblicità.
 *
 * 30 = 2 pagine e mezza. Nel modulo si inserisce come "pagine intere" +
 * "frazione" (fromParts()); sulle pagine si distribuisce con
 * distribution(): pagine intere in sequenza, la frazione sull'ultima
 * (30 → 12, 12, 6). Quanto finisce davvero su ciascuna pagina sta nel
 * pivot `page_content.occupied_twelfths` e resta modificabile a mano.
 */
final class PageOccupancy
{
    private function __construct(public readonly int $twelfths) {}

    /**
     * @throws InvalidArgumentException se non è almeno 1/12
     */
    public static function fromTwelfths(int $twelfths): self
    {
        if ($twelfths < 1) {
            throw new InvalidArgumentException("Occupazione non valida: {$twelfths} dodicesimi (minimo 1).");
        }

        return new self($twelfths);
    }

    /** Null per un dato mancante o non valido: il chiamante rifiuta, non sceglie un default. */
    public static function tryFromTwelfths(int|string|null $twelfths): ?self
    {
        if ($twelfths === null || $twelfths === '' || ! is_numeric($twelfths) || (int) $twelfths < 1) {
            return null;
        }

        return new self((int) $twelfths);
    }

    /**
     * Dai due campi del modulo: "Pagine intere" (≥ 0) e "Frazione" (una di
     * Twelfths::FRACTIONS o 0 = nessuna). Null se il totale è 0 o la
     * frazione non è tra quelle ammesse (un input libero reintrodurrebbe
     * le quantità "a occhio").
     */
    public static function fromParts(?int $wholePages, int $fractionTwelfths): ?self
    {
        $wholePages ??= 0;

        if ($wholePages < 0 || ($fractionTwelfths !== 0 && ! array_key_exists($fractionTwelfths, Twelfths::FRACTIONS))) {
            return null;
        }

        return self::tryFromTwelfths($wholePages * Twelfths::PER_PAGE + $fractionTwelfths);
    }

    /** N pagine intere (es. le pagine scelte di un PDF pubblicitario, bug 8). */
    public static function wholePages(int $pages): self
    {
        return self::fromTwelfths($pages * Twelfths::PER_PAGE);
    }

    /**
     * Occupazione proposta dal formato pubblicitario (AdFormat::twelfths(),
     * unica fonte della mappatura formato → spazio).
     */
    public static function forAdFormat(AdFormat $format): self
    {
        return self::fromTwelfths($format->twelfths());
    }

    public function wholePageCount(): int
    {
        return intdiv($this->twelfths, Twelfths::PER_PAGE);
    }

    /** Parte frazionaria (0 se pagine intere esatte). */
    public function fractionTwelfths(): int
    {
        return $this->twelfths % Twelfths::PER_PAGE;
    }

    /** Quante pagine consecutive attraversa: 2,5 pagine → 3. */
    public function pagesSpanned(): int
    {
        return intdiv($this->twelfths + Twelfths::PER_PAGE - 1, Twelfths::PER_PAGE);
    }

    /**
     * Distribuzione di default sulle pagine attraversate, in ordine di
     * sfoglio: pagine intere e la frazione sull'ultima (30 → [12, 12, 6]).
     *
     * @return list<int>
     */
    public function distribution(): array
    {
        $pages = array_fill(0, $this->wholePageCount(), Twelfths::PER_PAGE);

        if ($this->fractionTwelfths() > 0) {
            $pages[] = $this->fractionTwelfths();
        }

        return $pages;
    }

    /**
     * Quanto occupa su UNA pagina aggiuntiva quando lo si estende a mano
     * (Grid::extendToPage()): una pagina intera, o tutto il contenuto se
     * è meno di una pagina.
     */
    public function perExtraPage(): int
    {
        return min($this->twelfths, Twelfths::PER_PAGE);
    }

    public function isFraction(): bool
    {
        return $this->twelfths < Twelfths::PER_PAGE;
    }

    /** "2 e 1/2 pagine", "1/3 di pagina", "1 pagina". */
    public function label(): string
    {
        return Twelfths::pagesLabel($this->twelfths);
    }
}
