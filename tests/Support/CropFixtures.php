<?php

namespace Tests\Support;

use TCPDF;

/**
 * PDF di prova per il ritaglio delle miniature (2026-10-30, Fase B0),
 * generati da codice e mai presi da materiale reale. Ogni pagina è divisa
 * in sesti (2 colonne × 3 righe), ciascuno di un colore e con il suo numero
 * grande, così in uno screenshot si vede subito quale parte è mostrata:
 *
 *   1 rosso   | 2 arancio
 *   3 giallo  | 4 verde
 *   5 blu     | 6 viola
 *
 * TCPDF è già una dipendenza (export PDF sfogliabile): niente pacchetti
 * nuovi. `php tests/Support/crop-fixtures.php <cartella>` li scrive su
 * disco per guardarli.
 */
final class CropFixtures
{
    /** Abbondanza per lato, mm (come nel listino). */
    public const BLEED_MM = 3.0;

    private const COLORS = [
        1 => [220, 38, 38], 2 => [234, 88, 12],
        3 => [234, 179, 8], 4 => [22, 163, 74],
        5 => [37, 99, 235], 6 => [147, 51, 234],
    ];

    /**
     * Scrive tutti i file in $dir e restituisce nome → percorso.
     *
     * @return array<string, string>
     */
    public static function writeAll(string $dir): array
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $files = [
            'a4-sesti' => self::sixths(210, 297, 0, 1),
            'a4-sesti-abbondanza' => self::sixths(210, 297, self::BLEED_MM, 1),
            'pagina-210x270-sesti' => self::sixths(210, 270, 0, 1),
            'mezza-verticale-nel-formato' => self::plain(103, 270, 'MEZZA', self::COLORS[4]),
            'a4-sesti-tre-pagine' => self::sixths(210, 297, 0, 3),
        ];

        $paths = [];

        foreach ($files as $name => $pdf) {
            $paths[$name] = $dir.'/'.$name.'.pdf';
            file_put_contents($paths[$name], $pdf);
        }

        return $paths;
    }

    /**
     * Pagina rifilata $width×$height mm divisa in sesti. Con $bleed > 0 la
     * MediaBox è più grande di $bleed per lato, i colori arrivano fino al
     * bordo dell'abbondanza e la TrimBox è la pagina rifilata. Con più
     * pagine, ogni numero ha davanti la pagina («2·4» = pagina 2, sesto 4).
     */
    public static function sixths(float $width, float $height, float $bleed, int $pages): string
    {
        $pdf = self::document();
        $mediaW = $width + 2 * $bleed;
        $mediaH = $height + 2 * $bleed;

        for ($n = 1; $n <= $pages; $n++) {
            $format = ['MediaBox' => ['llx' => 0, 'lly' => 0, 'urx' => $mediaW, 'ury' => $mediaH]];

            if ($bleed > 0) {
                $format['TrimBox'] = ['llx' => $bleed, 'lly' => $bleed, 'urx' => $bleed + $width, 'ury' => $bleed + $height];
                $format['BleedBox'] = $format['MediaBox'];
            }

            $pdf->AddPage($mediaW > $mediaH ? 'L' : 'P', $format);

            foreach (self::COLORS as $sixth => $rgb) {
                $col = ($sixth - 1) % 2;
                $row = intdiv($sixth - 1, 2);
                // I sesti sul bordo si allungano nell'abbondanza.
                $x = $bleed + $col * $width / 2 - ($col === 0 ? $bleed : 0);
                $y = $bleed + $row * $height / 3 - ($row === 0 ? $bleed : 0);
                $w = $width / 2 + $bleed;
                $h = $height / 3 + ($row === 1 ? 0 : $bleed);

                $pdf->SetFillColor(...$rgb);
                $pdf->Rect($x, $y, $w, $h, 'F');
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 60);
                $label = $pages > 1 ? "{$n}·{$sixth}" : (string) $sixth;
                $pdf->Text($bleed + $col * $width / 2 + 8, $bleed + $row * $height / 3 + 8, $label);
            }

            // Bordo della pagina rifilata: si vede se il ritaglio include l'abbondanza.
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.8);
            $pdf->Rect($bleed, $bleed, $width, $height, 'D');
        }

        return $pdf->Output('', 'S');
    }

    /** Un file già nella misura del formato: un solo colore e una scritta. */
    public static function plain(float $width, float $height, string $text, array $rgb): string
    {
        $pdf = self::document();
        $pdf->AddPage($width > $height ? 'L' : 'P', ['MediaBox' => ['llx' => 0, 'lly' => 0, 'urx' => $width, 'ury' => $height]]);
        $pdf->SetFillColor(...$rgb);
        $pdf->Rect(0, 0, $width, $height, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 40);
        $pdf->Text(8, 8, $text);

        return $pdf->Output('', 'S');
    }

    private static function document(): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCreator('timone-rivista test');

        return $pdf;
    }
}
