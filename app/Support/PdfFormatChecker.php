<?php

namespace App\Support;

/**
 * Confronta le dimensioni reali (mm) di una pagina PDF con quelle nominali
 * di un formato pubblicitario (§2.3) — puro/testabile come PageReorderer/
 * PagePercentageAllocator: nessuna dipendenza da Imagick/filesystem, opera
 * solo su numeri già estratti (da App\Support\PdfPageMeasurer, separato
 * apposta per poter testare questa logica di confronto senza `ext-imagick`).
 */
class PdfFormatChecker
{
    /**
     * Abbondanza di stampa richiesta per lato (spec §2.3): il PDF atteso è
     * il formato nominale + 3mm per lato, cioè +6mm per dimensione.
     */
    public const BLEED_MM = 3.0;

    /**
     * Tolleranza di default se non diversamente configurato (vedi
     * config('timone.pdf_format_tolerance_mm')) — nel mezzo del range
     * "1-2mm" richiesto esplicitamente per assorbire piccoli errori di
     * esportazione, oltre l'abbondanza.
     */
    public const DEFAULT_TOLERANCE_MM = 1.5;

    /**
     * @param  array{width: float, height: float}  $nominalMm  Misura del formato SENZA abbondanza (App\Enums\AdFormat::dimensionsMm())
     * @param  array{width: float, height: float}  $measuredMm  Misura reale della pagina PDF
     *
     * Controlla anche l'orientamento scambiato (larghezza/altezza invertite
     * rispetto al nominale): un PDF può legittimamente arrivare ruotato di
     * 90° pur avendo l'area corretta, non ha senso segnalarlo come non
     * conforme solo per questo.
     */
    public static function matches(array $nominalMm, array $measuredMm, float $toleranceMm = self::DEFAULT_TOLERANCE_MM): bool
    {
        $expectedWidth = $nominalMm['width'] + self::BLEED_MM * 2;
        $expectedHeight = $nominalMm['height'] + self::BLEED_MM * 2;

        $straight = abs($measuredMm['width'] - $expectedWidth) <= $toleranceMm
            && abs($measuredMm['height'] - $expectedHeight) <= $toleranceMm;

        $rotated = abs($measuredMm['width'] - $expectedHeight) <= $toleranceMm
            && abs($measuredMm['height'] - $expectedWidth) <= $toleranceMm;

        return $straight || $rotated;
    }
}
