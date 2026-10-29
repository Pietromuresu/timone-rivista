<?php

namespace App\Support\Signatures;

use App\Models\Issue;

/**
 * Organizzazione del numero in segnature (2026-10-29): quanti trentaduesimi
 * (o sedicesimi…) pieni, quante pagine restano e in che formati
 * tipografici si scompongono, se il totale è stampabile, quante pagine
 * aggiungere o togliere per arrivare a un multiplo, e a quale segnatura
 * appartiene ogni pagina (confini disegnati sulla griglia).
 *
 * Pura: lavora su quattro numeri. Indipendente dal calcolo del peso
 * (MagazineWeightCalculator): entrambi leggono le stesse due quantità
 * (pagine interne = issues.total_pages, facciate di copertina =
 * Issue::coverPageCount()), ma il peso non dipende dalle segnature e le
 * segnature non dipendono dalla carta; legarle avrebbe accoppiato due
 * calcoli che cambiano per motivi diversi.
 *
 * Sequenza di stampa: con le copertine incluse le loro facciate stanno in
 * testa (la pagina interna p è la (p + copertine)-esima); senza, la
 * pagina 1 è la prima della prima segnatura.
 */
final class SignaturePlan
{
    /** Dimensioni ammesse (stesse della scelta nel modulo della rivista). */
    public const SIZES = [8, 16, 32, 64];

    public const DEFAULT_SIZE = 32;

    /** Formato tipografico più piccolo: sotto il quartino non si stampa. */
    public const MIN_FORMAT = 4;

    public function __construct(
        public readonly int $internalPages,
        public readonly int $coverPages,
        public readonly int $size = self::DEFAULT_SIZE,
        public readonly bool $includeCovers = false,
    ) {}

    public static function forIssue(Issue $issue): self
    {
        $magazine = $issue->magazine;
        $size = (int) ($magazine->signature_pages ?? self::DEFAULT_SIZE);

        return new self(
            (int) $issue->total_pages,
            $issue->coverPageCount(),
            in_array($size, self::SIZES, true) ? $size : self::DEFAULT_SIZE,
            (bool) ($magazine->signature_includes_covers ?? false),
        );
    }

    /** Pagine che entrano nelle segnature. */
    public function countedPages(): int
    {
        return $this->internalPages + $this->headPages();
    }

    public function fullSignatures(): int
    {
        return intdiv($this->countedPages(), $this->size);
    }

    public function remainder(): int
    {
        return $this->countedPages() % $this->size;
    }

    /**
     * Il resto in formati tipografici standard, dal più grande: metà
     * segnatura, un quarto… fino al quartino (32 → 16, 8, 4). Es. 24 =
     * 16 + 8. `loose` = pagine che non formano nemmeno un quartino (il
     * totale non è multiplo di 4).
     *
     * @return array{parts: list<int>, loose: int}
     */
    public function remainderBreakdown(): array
    {
        $left = $this->remainder();
        $parts = [];

        for ($format = intdiv($this->size, 2); $format >= self::MIN_FORMAT; $format = intdiv($format, 2)) {
            if ($left >= $format) {
                $parts[] = $format;
                $left -= $format;
            }
        }

        return ['parts' => $parts, 'loose' => $left];
    }

    /** Stampabile così: multiplo di 4 (quartini interi). */
    public function isPrintable(): bool
    {
        return $this->countedPages() % self::MIN_FORMAT === 0;
    }

    /**
     * Pagine interne da aggiungere/togliere per un numero intero di
     * segnature (multiplo più vicino in su e in giù). Null se è già un
     * multiplo; `remove` null se in giù si arriverebbe a zero segnature.
     *
     * @return array{add: int, addTotal: int, remove: int|null, removeTotal: int|null}|null
     */
    public function suggestion(): ?array
    {
        $remainder = $this->remainder();

        if ($remainder === 0) {
            return null;
        }

        $add = $this->size - $remainder;
        $canRemove = $this->fullSignatures() > 0 && $remainder <= $this->internalPages;

        return [
            'add' => $add,
            'addTotal' => $this->internalPages + $add,
            'remove' => $canRemove ? $remainder : null,
            'removeTotal' => $canRemove ? $this->internalPages - $remainder : null,
        ];
    }

    /** Segnatura (da 1) della pagina interna in quella posizione. */
    public function signatureOf(int $position): int
    {
        return intdiv($position + $this->headPages() - 1, $this->size) + 1;
    }

    /**
     * La pagina apre una segnatura che non è la prima: lì si disegna il
     * confine, sul suo lato SINISTRO. Con pagine dispari a destra la
     * pagina che apre una segnatura è dispari (dimensioni e copertine
     * sono pari), quindi il confine cade dentro la coppia [32 | 33], tra
     * le due pagine — vedi SignaturePlanTest.
     */
    public function startsSignature(int $position): bool
    {
        return $position > 1 && $this->signatureOf($position) !== $this->signatureOf($position - 1);
    }

    /** «trentaduesimo»/«trentaduesimi», «sedicesimo»… per una dimensione. */
    public static function formatName(int $pages, int $count = 1): string
    {
        $names = [64 => 'sessantaquattresim', 32 => 'trentaduesim', 16 => 'sedicesim', 8 => 'ottav'];

        if ($pages === 4) {
            return $count === 1 ? 'quartino' : 'quartini';
        }

        return isset($names[$pages]) ? $names[$pages].($count === 1 ? 'o' : 'i') : "segnatura da {$pages}";
    }

    /** Etichetta breve di una segnatura sulla griglia: «32° n. 2». */
    public function label(int $signature): string
    {
        return "{$this->size}° n. {$signature}";
    }

    /** «3 trentaduesimi + 24 pagine» (o «4 trentaduesimi», «24 pagine»). */
    public function summaryLabel(): string
    {
        $full = $this->fullSignatures();
        $remainder = $this->remainder();
        $parts = [];

        if ($full > 0 || $remainder === 0) {
            $parts[] = $full.' '.self::formatName($this->size, $full);
        }

        if ($remainder > 0) {
            $parts[] = $remainder.' '.($remainder === 1 ? 'pagina' : 'pagine');
        }

        return implode(' + ', $parts);
    }

    /** «16 + 8», «16 + 8 + 4 + 2 sciolte»; vuoto se non c'è resto. */
    public function breakdownLabel(): string
    {
        ['parts' => $parts, 'loose' => $loose] = $this->remainderBreakdown();
        $labels = array_map('strval', $parts);

        if ($loose > 0) {
            $labels[] = $loose.($loose === 1 ? ' sciolta' : ' sciolte');
        }

        return implode(' + ', $labels);
    }

    private function headPages(): int
    {
        return $this->includeCovers ? $this->coverPages : 0;
    }
}
