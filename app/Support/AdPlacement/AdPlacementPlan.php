<?php

namespace App\Support\AdPlacement;

use App\Enums\CoverSlot;
use App\Enums\PlannedAdKind;
use App\Support\Twelfths;

/**
 * Risultato di AdPlacementService::plan() (Fase 5): dove va ogni
 * pubblicità prevista, quali restano senza posto e gli avvisi. Valore
 * immutabile, nessuna query: lo usano sia l'anteprima del modulo "Nuovo
 * numero" sia la creazione vera (PlaceholderAdCreator), così ciò che si
 * vede nel modulo è ciò che viene creato.
 */
final class AdPlacementPlan
{
    /**
     * @param  list<array{kind: PlannedAdKind, cover: CoverSlot|null, position: int|null}>  $placements
     * @param  list<PlannedAdKind>  $unplaced
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly int $totalPages,
        public readonly array $placements,
        public readonly array $unplaced,
        public readonly array $warnings,
    ) {}

    public function fits(): bool
    {
        return $this->unplaced === [];
    }

    public function count(): int
    {
        return count($this->placements) + count($this->unplaced);
    }

    /** Dodicesimi pubblicitari totali previsti (copertine comprese). */
    public function totalTwelfths(): int
    {
        return array_sum(array_map(fn (array $p) => $p['kind']->twelfths(), $this->placements))
            + array_sum(array_map(fn (PlannedAdKind $k) => $k->twelfths(), $this->unplaced));
    }

    /**
     * Dodicesimi sulle pagine interne: quelli che entrano nella percentuale
     * pubblicitaria (dalla Fase 2 le copertine sono escluse). Le non
     * posizionate contano come le prenotate del cruscotto.
     */
    public function internalTwelfths(): int
    {
        return $this->totalTwelfths() - $this->coverTwelfths();
    }

    public function coverTwelfths(): int
    {
        return array_sum(array_map(fn (array $p) => $p['cover'] !== null ? $p['kind']->twelfths() : 0, $this->placements));
    }

    public function internalPercentage(): float
    {
        return Twelfths::percentage($this->internalTwelfths(), $this->totalPages * Twelfths::PER_PAGE);
    }

    /** "I di copertina", "pagina 4"… nell'ordine del piano. @return list<string> */
    public function labels(): array
    {
        return array_map(
            fn (array $p) => $p['cover'] !== null ? $p['cover']->label() : "pagina {$p['position']}",
            $this->placements,
        );
    }
}
