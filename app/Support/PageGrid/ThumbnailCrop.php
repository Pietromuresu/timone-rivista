<?php

namespace App\Support\PageGrid;

use App\Enums\FileFit;

/**
 * Ritaglio della miniatura di un contenuto nei suoi pezzi sulla pagina
 * (2026-10-30, sostituisce ThumbnailFit della revisione 2, Fase 2.4).
 * Una sola fonte per card, export del timone e PDF sfogliabile.
 *
 * Regola (approvata nella Fase B0):
 * - il file si vede INTERO solo se ha la misura del riquadro del
 *   contenuto (mm, o proporzioni se le misure non si conoscono), ±5%; un
 *   riquadro a pagina intera mostra intero un file che non ha le
 *   proporzioni della pagina;
 * - altrimenti è A PAGINA INTERA: la pagina rifilata del file copre la
 *   pagina del numero, centrata (come object-fit: cover), e ogni pezzo
 *   mostra la sua porzione. Prima la regola era al contrario («intero se
 *   non ha le proporzioni della pagina»): un A4 su un numero 210×270, o
 *   un file mai misurato, finiva rimpicciolito invece che ritagliato.
 *
 * Pagina rifilata: la TrimBox del PDF se c'è (PdfBoxes); se no, se la
 * pagina misura il formato della pagina o del riquadro più 3 mm per lato,
 * si tolgono i 3 mm; se no la pagina intera. Altrimenti il ritaglio
 * risulterebbe spostato dell'abbondanza.
 *
 * Forme a L: un pezzo per volta, ognuno con la sua finestra (nessun
 * clip-path, che dompdf non sa disegnare).
 */
final class ThumbnailCrop
{
    /** Tolleranza su misure e proporzioni (specifica della revisione 2.4: 5%). */
    public const TOLERANCE = 0.05;

    /** Un file è «una pagina» se misura almeno il 90% della pagina in ciascun lato. */
    public const PAGE_LIKE = 0.9;

    /** Abbondanza per lato del listino, mm. */
    public const BLEED_MM = 3.0;

    /** Quanto può scostarsi una pagina «formato + abbondanza», mm. */
    public const BLEED_TOLERANCE_MM = 1.0;

    /**
     * @param  array{0: float, 1: float}  $pageMm  pagina del numero (base, altezza)
     * @param  list<GridRect>  $pieces  pezzi del contenuto sulla pagina (uno, o più per una L)
     * @param  float|null  $imageRatio  larghezza/altezza della miniatura (content_files.aspect_ratio)
     * @param  array{media?: array{0: float, 1: float}|null, trim?: array|null}|null  $boxes  content_files.trim_box (PdfBoxes::measure())
     */
    public static function plan(array $pageMm, array $pieces, ?float $imageRatio, ?array $boxes, ?FileFit $override): CropPlan
    {
        [$pageW, $pageH] = $pageMm;
        $media = $boxes['media'] ?? null;
        $bounds = self::bounds($pieces);
        $rectMm = [$bounds->w / GridRect::SIZE * $pageW, $bounds->h / GridRect::SIZE * $pageH];

        // Proporzioni dell'immagine: misurate; se no dalla MediaBox; se no
        // come la pagina (allora «coprire» non taglia nulla).
        $imageRatio = $imageRatio ?? ($media !== null ? $media[0] / $media[1] : $pageW / $pageH);
        $trim = self::trim($media, $boxes['trim'] ?? null, $pageMm, $rectMm);
        $trimRatio = $imageRatio * $trim[2] / $trim[3];

        $fit = $override ?? self::detect($bounds, $media, $trim, $trimRatio, $pageMm, $rectMm);
        $page = self::pageInTrim($trim, $trimRatio, $pageW / $pageH);

        $planned = array_map(function (GridRect $cells) use ($page) {
            $rect = $cells->toPercent();

            return [
                'cells' => $cells,
                'rect' => $rect,
                'window' => [
                    $page[0] + $rect['x'] / 100 * $page[2],
                    $page[1] + $rect['y'] / 100 * $page[3],
                    $rect['w'] / 100 * $page[2],
                    $rect['h'] / 100 * $page[3],
                ],
            ];
        }, $pieces);

        return new CropPlan($fit, $trim, $page, $planned);
    }

    /** Il file è già nella misura del riquadro? Il resto è «a pagina intera». */
    private static function detect(GridRect $bounds, ?array $media, array $trim, float $trimRatio, array $pageMm, array $rectMm): FileFit
    {
        // Riquadro a pagina intera: si ritaglia (per togliere l'abbondanza)
        // solo un file con le proporzioni della pagina; un file diverso (es.
        // un'immagine larga di una doppia pagina) si vede intero, come prima.
        if ($bounds->w === GridRect::SIZE && $bounds->h === GridRect::SIZE) {
            return self::close($trimRatio, $pageMm[0] / $pageMm[1]) ? FileFit::FullPage : FileFit::Format;
        }

        // Misure vere (PDF): intero se la pagina rifilata ha quelle del
        // riquadro; ritagliato se è grande (quasi) quanto la pagina, anche
        // con proporzioni diverse (un A4 su un numero 210×270); un file più
        // piccolo della pagina e non a misura (una mezza verticale messa in
        // orizzontale) si vede intero: ritagliarlo mostrerebbe un pezzo a caso.
        if ($media !== null) {
            $trimmed = [$media[0] * $trim[2] / 100, $media[1] * $trim[3] / 100];

            if (self::close($trimmed[0], $rectMm[0]) && self::close($trimmed[1], $rectMm[1])) {
                return FileFit::Format;
            }

            return $trimmed[0] >= self::PAGE_LIKE * $pageMm[0] && $trimmed[1] >= self::PAGE_LIKE * $pageMm[1] ? FileFit::FullPage : FileFit::Format;
        }

        // Solo proporzioni (immagini, PDF non leggibili): intero se ha quelle
        // del riquadro; ritagliato se ha quelle della pagina; intero se non
        // somiglia a nessuno dei due. Una vetrina ha le proporzioni della
        // pagina: senza mm, nel dubbio intera (un annuncio consegnato a
        // misura non va mai tagliato; si può forzare a mano).
        if (self::close($trimRatio, $rectMm[0] / $rectMm[1])) {
            return FileFit::Format;
        }

        return self::close($trimRatio, $pageMm[0] / $pageMm[1]) ? FileFit::FullPage : FileFit::Format;
    }

    /**
     * Pagina rifilata in % dell'immagine.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function trim(?array $media, ?array $trim, array $pageMm, array $rectMm): array
    {
        if ($media === null || $media[0] <= 0 || $media[1] <= 0) {
            return [0.0, 0.0, 100.0, 100.0];
        }

        if ($trim !== null) {
            return [$trim[0] / $media[0] * 100, $trim[1] / $media[1] * 100, $trim[2] / $media[0] * 100, $trim[3] / $media[1] * 100];
        }

        foreach ([$pageMm, $rectMm] as $nominal) {
            if (abs($media[0] - $nominal[0] - 2 * self::BLEED_MM) <= self::BLEED_TOLERANCE_MM
                && abs($media[1] - $nominal[1] - 2 * self::BLEED_MM) <= self::BLEED_TOLERANCE_MM) {
                return [self::BLEED_MM / $media[0] * 100, self::BLEED_MM / $media[1] * 100, ($media[0] - 2 * self::BLEED_MM) / $media[0] * 100, ($media[1] - 2 * self::BLEED_MM) / $media[1] * 100];
            }
        }

        return [0.0, 0.0, 100.0, 100.0];
    }

    /**
     * Dove cade la pagina del numero nell'immagine: la pagina rifilata la
     * copre, centrata. Se le proporzioni coincidono è la pagina rifilata.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function pageInTrim(array $trim, float $trimRatio, float $pageRatio): array
    {
        [$x, $y, $w, $h] = $trim;

        return $trimRatio > $pageRatio
            ? [$x + $w * (1 - $pageRatio / $trimRatio) / 2, $y, $w * $pageRatio / $trimRatio, $h]
            : [$x, $y + $h * (1 - $trimRatio / $pageRatio) / 2, $w, $h * $trimRatio / $pageRatio];
    }

    /** @param  list<GridRect>  $pieces */
    private static function bounds(array $pieces): GridRect
    {
        $x = min(array_map(fn (GridRect $r) => $r->x, $pieces));
        $y = min(array_map(fn (GridRect $r) => $r->y, $pieces));
        $right = max(array_map(fn (GridRect $r) => $r->x + $r->w, $pieces));
        $bottom = max(array_map(fn (GridRect $r) => $r->y + $r->h, $pieces));

        return new GridRect($x, $y, $right - $x, $bottom - $y);
    }

    private static function close(float $a, float $b): bool
    {
        return $b > 0 && abs($a / $b - 1) <= self::TOLERANCE;
    }
}
