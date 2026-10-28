<?php

namespace App\Support\MagazineExport;

/**
 * Geometria di una pagina dell'export sfogliabile (revisione 2, Fase 5),
 * pura e in millimetri: il foglio è sempre A4 verticale, la pagina del
 * numero (es. 210×270) vi è scalata in proporzione e centrata, senza
 * deformarla, lasciando in fondo la fascia per il numero di pagina (fuori
 * dall'area del contenuto, come chiede la specifica).
 */
final class PageBox
{
    public const SHEET_WIDTH = 210.0;

    public const SHEET_HEIGHT = 297.0;

    /** Margine attorno alla pagina. */
    public const MARGIN = 8.0;

    /** Fascia in fondo per «Pagina 5» / «I di copertina». */
    public const FOOTER = 10.0;

    private function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $width,
        public readonly float $height,
    ) {}

    /** @param  float  $pageRatio  larghezza/altezza della pagina del numero */
    public static function forRatio(float $pageRatio): self
    {
        $pageRatio = $pageRatio > 0 ? $pageRatio : 3 / 4;
        $maxWidth = self::SHEET_WIDTH - 2 * self::MARGIN;
        $maxHeight = self::SHEET_HEIGHT - 2 * self::MARGIN - self::FOOTER;

        $width = min($maxWidth, $maxHeight * $pageRatio);
        $height = $width / $pageRatio;

        return new self(
            round((self::SHEET_WIDTH - $width) / 2, 3),
            round(self::MARGIN + ($maxHeight - $height) / 2, 3),
            round($width, 3),
            round($height, 3),
        );
    }

    /**
     * Da un rettangolo in percentuale della pagina (Page::layout(), griglia
     * 6×6) a millimetri sul foglio.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $percent
     * @return array{x: float, y: float, w: float, h: float}
     */
    public function rect(array $percent): array
    {
        return [
            'x' => $this->x + $percent['x'] * $this->width / 100,
            'y' => $this->y + $percent['y'] * $this->height / 100,
            'w' => $percent['w'] * $this->width / 100,
            'h' => $percent['h'] * $this->height / 100,
        ];
    }

    /** Tutta la pagina come rettangolo (file «a pagina intera»). */
    public function full(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'w' => $this->width, 'h' => $this->height];
    }

    /** Linea di base del numero di pagina, sotto la pagina. */
    public function footerY(): float
    {
        return $this->y + $this->height + 3;
    }

    /**
     * Un file «nel formato» dentro il suo rettangolo: intero, proporzioni
     * mantenute, centrato (come object-fit: contain nella card).
     *
     * @param  array{x: float, y: float, w: float, h: float}  $box
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function contain(array $box, float $sourceWidth, float $sourceHeight): array
    {
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return $box;
        }

        $scale = min($box['w'] / $sourceWidth, $box['h'] / $sourceHeight);
        $w = $sourceWidth * $scale;
        $h = $sourceHeight * $scale;

        return ['x' => $box['x'] + ($box['w'] - $w) / 2, 'y' => $box['y'] + ($box['h'] - $h) / 2, 'w' => $w, 'h' => $h];
    }
}
