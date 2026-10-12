<?php

namespace App\Support;

use App\Enums\AdFormat;
use InvalidArgumentException;

/**
 * Occupazione di pagina di un contenuto, espressa in PAGINE (bug 5,
 * 2026-10-12): 0.25/0.33/0.5/0.67 per le frazioni di una pagina, 1, 2, 3…
 * per i contenuti che occupano pagine intere consecutive. Un valore unico
 * per articoli e pubblicità (colonna `contents.page_occupancy`) così che
 * distribuzione al drop e calcoli di foliazione leggano sempre lo stesso
 * dato invece di dedurlo caso per caso.
 *
 * Le frazioni sono le stesse del listino pubblicitario (AdFormat): 1/4,
 * 1/3, 1/2, 2/3 — non un input libero, per non reintrodurre percentuali
 * "a occhio". 1/3 e 2/3 sono salvate con 2 decimali (0.33/0.67) perché la
 * colonna è decimal(5,2); la percentuale vera è risolta da
 * percentagePerPage() tramite la mappa FRACTION_PERCENTAGES, non
 * moltiplicando per 100 (che darebbe 33% invece del 33.3% già usato da
 * AdFormat::defaultPercentage()).
 */
final class PageOccupancy
{
    /** @var array<string, array{label: string, percentage: float}> */
    private const FRACTIONS = [
        '0.25' => ['label' => '1/4 di pagina', 'percentage' => 25.0],
        '0.33' => ['label' => '1/3 di pagina', 'percentage' => 33.3],
        '0.50' => ['label' => '1/2 pagina', 'percentage' => 50.0],
        '0.67' => ['label' => '2/3 di pagina', 'percentage' => 66.7],
    ];

    private function __construct(public readonly string $value) {}

    /**
     * @throws InvalidArgumentException se il valore non è tra quelli ammessi
     */
    public static function from(string|float|int $value): self
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            throw new InvalidArgumentException("Occupazione di pagina non ammessa: «{$value}».");
        }

        return new self($normalized);
    }

    public static function tryFrom(string|float|int|null $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = self::normalize($value);

        return $normalized === null ? null : new self($normalized);
    }

    /**
     * Opzioni della select, in ordine crescente: le 4 frazioni e poi le
     * pagine intere da 1 a $maxPages (il totale pagine del numero: oltre
     * non avrebbe senso, non ci starebbe comunque).
     *
     * @return array<string, string> valore => etichetta
     */
    public static function options(int $maxPages): array
    {
        $options = array_map(fn (array $f) => $f['label'], self::FRACTIONS);

        foreach (range(1, max(1, $maxPages)) as $pages) {
            $options[self::formatPages($pages)] = $pages === 1 ? '1 pagina' : "{$pages} pagine";
        }

        return $options;
    }

    /**
     * Occupazione derivata dal formato pubblicitario: per la pubblicità
     * l'utente sceglie il FORMATO (dato commerciale del listino, già
     * obbligatorio), non un'occupazione separata — un secondo campo da
     * tenere coerente a mano sarebbe una doppia fonte dello stesso dato.
     * Battente/doppia pagina occupano 2 pagine intere (vedi
     * AdFormat::defaultPercentage(): 100% di CIASCUNA delle due pagine);
     * i formati con percentuali di listino non "a frazione" (52%, 32.6%,
     * 70.5%) sono arrotondati alla frazione ammessa più vicina — serve solo
     * a contare quante pagine attraversa il contenuto, la percentuale
     * reale sulla pagina resta Advertisement::occupiedPercentage().
     */
    public static function forAdFormat(AdFormat $format): self
    {
        if (in_array($format, [AdFormat::DoppiaPagina, AdFormat::BattenteCopertina], true)) {
            return self::from(2);
        }

        $fraction = $format->defaultPercentage() / 100;

        if ($fraction >= 1) {
            return self::from(1);
        }

        $closest = collect(self::FRACTIONS)
            ->keys()
            ->sortBy(fn (string $key) => abs((float) $key - $fraction))
            ->first();

        return self::from($closest);
    }

    /**
     * Quante pagine consecutive il contenuto attraversa: 1 per qualunque
     * frazione, N per N pagine intere.
     */
    public function pagesSpanned(): int
    {
        return $this->isFraction() ? 1 : (int) $this->value;
    }

    /**
     * Percentuale occupata su CIASCUNA pagina attraversata (per un
     * articolo — la pubblicità usa Advertisement::occupiedPercentage(),
     * vedi Content::occupiedPercentagePerPage()).
     */
    public function percentagePerPage(): float
    {
        return self::FRACTIONS[$this->value]['percentage'] ?? 100.0;
    }

    public function isFraction(): bool
    {
        return array_key_exists($this->value, self::FRACTIONS);
    }

    public function label(): string
    {
        return self::FRACTIONS[$this->value]['label']
            ?? ($this->value === '1' ? '1 pagina' : "{$this->value} pagine");
    }

    private static function normalize(string|float|int $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = round((float) $value, 2);

        foreach (array_keys(self::FRACTIONS) as $key) {
            if (abs((float) $key - $number) < 0.001) {
                return $key;
            }
        }

        if ($number >= 1 && floor($number) === $number) {
            return self::formatPages((int) $number);
        }

        return null;
    }

    private static function formatPages(int $pages): string
    {
        return (string) $pages;
    }
}
