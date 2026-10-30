<?php

namespace App\Support;

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PageBoundaries;
use setasign\Fpdi\PdfReader\PdfReader;
use Throwable;

/**
 * Misure di una pagina di un PDF (2026-10-30, ritaglio delle miniature):
 * la MediaBox (quello che la miniatura rasterizza) e, se c'è, la TrimBox
 * (la pagina rifilata, senza abbondanza), in mm con l'origine in alto a
 * sinistra della MediaBox. Servono a ThumbnailCrop per ritagliare sulla
 * pagina rifilata invece che sull'immagine con l'abbondanza.
 *
 * Il parser di FPDI (già dipendenza dell'export) legge solo la struttura,
 * senza rasterizzare: veloce anche su file grandi. Non legge alcuni PDF
 * (stream di riferimenti compressi della versione gratuita) e non gestisce
 * qui le pagine ruotate (/Rotate): in quei casi null, e il ritaglio usa
 * l'immagine intera come prima.
 */
final class PdfBoxes
{
    private const MM_PER_POINT = 25.4 / 72;

    /**
     * @return array{media: array{0: float, 1: float}, trim: array{0: float, 1: float, 2: float, 3: float}|null}|null
     */
    public static function measure(string $absolutePath, int $pageNumber = 1): ?array
    {
        try {
            $reader = new PdfReader(new PdfParser(StreamReader::createByFile($absolutePath)));
            $page = $reader->getPage($pageNumber);

            if ($page->getRotation() % 180 !== 0) {
                return null;
            }

            $media = $page->getBoundary(PageBoundaries::MEDIA_BOX);
            $trim = $page->getBoundary(PageBoundaries::TRIM_BOX, false);
            $mm = fn (float $points) => round($points * self::MM_PER_POINT, 2);

            $trimBox = $trim === false ? null : [
                $mm($trim->getLlx() - $media->getLlx()),
                $mm($media->getUry() - $trim->getUry()),
                $mm($trim->getWidth()),
                $mm($trim->getHeight()),
            ];

            // Una TrimBox uguale alla MediaBox non aggiunge informazione.
            if ($trimBox !== null && $trimBox[0] == 0.0 && $trimBox[1] == 0.0 && abs($trimBox[2] - $mm($media->getWidth())) < 0.1 && abs($trimBox[3] - $mm($media->getHeight())) < 0.1) {
                $trimBox = null;
            }

            return ['media' => [$mm($media->getWidth()), $mm($media->getHeight())], 'trim' => $trimBox];
        } catch (Throwable) {
            return null;
        }
    }
}
