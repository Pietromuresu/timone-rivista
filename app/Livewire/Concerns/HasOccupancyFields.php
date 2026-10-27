<?php

namespace App\Livewire\Concerns;

use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Support\ContentCreator;
use App\Support\PageOccupancy;

/**
 * Campi "Pagine intere" + "Frazione" (Fase 3) condivisi dai moduli di
 * creazione e modifica di un contenuto (Fase 4, 2026-10-27): stesse
 * proprietà, stessa proposta dal formato pubblicitario, stessa anteprima
 * «Occupa …». Il componente che lo usa deve avere `$type` e `$format`, e
 * NormalizesEmptyNumericInput per `$whole_pages` svuotabile.
 */
trait HasOccupancyFields
{
    public ?int $whole_pages = null;

    /** Valore di Twelfths::FRACTIONS o '0' (select → stringa). */
    public string $fraction_twelfths = '0';

    /**
     * Il formato PROPONE l'occupazione (AdFormat::twelfths()), che resta
     * modificabile; cambiando formato la proposta si riallinea.
     */
    public function updatedFormat(): void
    {
        $format = AdFormat::tryFrom($this->format);

        if ($this->type !== ContentType::Pubblicita->value || $format === null) {
            return;
        }

        $this->fillOccupancyFields(PageOccupancy::forAdFormat($format));
    }

    protected function fillOccupancyFields(PageOccupancy $occupancy): void
    {
        $this->whole_pages = $occupancy->wholePageCount();
        $this->fraction_twelfths = (string) $occupancy->fractionTwelfths();
    }

    /** Stessa risoluzione del salvataggio, per l'anteprima nel modulo. */
    protected function occupancyPreview(): ?PageOccupancy
    {
        return ContentCreator::occupancyFromFields([
            'whole_pages' => $this->whole_pages,
            'fraction_twelfths' => $this->fraction_twelfths,
        ]);
    }
}
