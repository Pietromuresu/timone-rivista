<?php

namespace App\Support\PageGrid;

use App\Enums\FileFit;

/**
 * Ritaglio dell'anteprima di un contenuto nel suo rettangolo (revisione 2,
 * Fase 2.4, 2026-10-27). Prima l'immagine era sempre `object-cover`
 * centrata: di un PDF a pagina intera con una mezza pagina verticale a
 * sinistra si vedeva il centro della pagina, cioè metà annuncio e metà
 * bianco ("tagliata a caso").
 *
 * Puro: decide il modo (FileFit) e restituisce lo stile CSS dell'<img>,
 * senza rigenerare immagini. Con FullPage l'immagine viene dimensionata
 * come l'intera pagina e spostata in modo che dentro il rettangolo si veda
 * esattamente la sua porzione (6/w volte più larga, spostata di x/w);
 * con Format è `object-fit: contain` (intera, senza tagli).
 */
final class ThumbnailFit
{
    /** Tolleranza sul rapporto larghezza/altezza (specifica: 5%). */
    public const TOLERANCE = 0.05;

    public static function resolve(?float $fileRatio, ?FileFit $override, float $pageRatio): FileFit
    {
        if ($override !== null) {
            return $override;
        }

        // Rapporto sconosciuto (file caricati prima della Fase 2.4 e non
        // ancora misurati): intero, senza tagli — mai un ritaglio sbagliato.
        if ($fileRatio === null || $fileRatio <= 0 || $pageRatio <= 0) {
            return FileFit::Format;
        }

        return abs($fileRatio / $pageRatio - 1) <= self::TOLERANCE ? FileFit::FullPage : FileFit::Format;
    }

    /** Stile inline dell'<img> dentro il blocco (position: relative/overflow hidden). */
    public static function imageStyle(FileFit $fit, GridRect $cells): string
    {
        if ($fit === FileFit::Format || ($cells->w === GridRect::SIZE && $cells->h === GridRect::SIZE)) {
            return 'position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain;';
        }

        $width = round(GridRect::SIZE / $cells->w * 100, 4);
        $height = round(GridRect::SIZE / $cells->h * 100, 4);
        $left = round(-$cells->x / $cells->w * 100, 4);
        $top = round(-$cells->y / $cells->h * 100, 4);

        return "position: absolute; left: {$left}%; top: {$top}%; width: {$width}%; height: {$height}%; max-width: none; object-fit: cover;";
    }
}
