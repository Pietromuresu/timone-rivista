<?php

namespace App\Support;

use App\Models\Page;
use App\Support\PageGrid\GridRect;

/**
 * Disposizione dei contenuti di una pagina nell'export PDF del timone
 * (2026-10-27, richiesta dell'utente: "nel pdf c'è solo una miniatura, non
 * tutte quelle della pagina"). Stessa disposizione della card (Page::layout()
 * → griglia 6×6 dalla revisione 2, Fase 2.3; prima PageLayoutPlanner), convertita da percentuali a punti: dompdf
 * posiziona in modo affidabile solo con unità assolute dentro un contenitore
 * di dimensioni fisse. Ogni blocco porta la miniatura del PROPRIO contenuto
 * (ExportThumbnail), o null se non c'è: la vista lo disegna come riquadro
 * colorato. Dal 2026-10-30 l'immagine è già ritagliata per pezzo (vedi image()).
 */
final class ExportPageLayout
{
    /**
     * extra: pezzi in più di una forma a L (stesso colore, senza testo); free: celle libere in rettangoli.
     *
     * @return array{blocks: list<array{x: float, y: float, w: float, h: float, img: array|null, isAd: bool, placeholder: bool, label: string, fraction: string, extra: list<array>}>, free: list<array{x: float, y: float, w: float, h: float}>}|null null se la pagina non ha contenuti
     */
    public static function forPage(Page $page, float $widthPt, float $heightPt, bool $withThumbnails): ?array
    {
        if ($page->contents->isEmpty()) {
            return null;
        }

        $layout = $page->layout();
        $toPt = fn (array $rect) => [
            'x' => round($rect['x'] * $widthPt / 100, 2),
            'y' => round($rect['y'] * $heightPt / 100, 2),
            'w' => round($rect['w'] * $widthPt / 100, 2),
            'h' => round($rect['h'] * $heightPt / 100, 2),
        ];

        return [
            'blocks' => array_map(function (array $block) use ($toPt, $withThumbnails) {
                $main = $toPt($block['rect']);

                return $main + [
                    'img' => $withThumbnails ? self::image($block, $block['cells'], $main) : null,
                    'isAd' => $block['content']->type->value === 'pubblicita',
                    'placeholder' => $block['content']->isPlaceholder(),
                    'label' => $block['content']->displayLabel(),
                    'fraction' => Twelfths::fractionLabel((int) $block['content']->pivot->occupied_twelfths),
                    // Pezzi in più di una L: con un file ritagliato anche loro mostrano la propria porzione.
                    'extra' => self::extraPieces($block, $toPt, $withThumbnails),
                ];
            }, $layout['blocks']),
            'free' => array_map($toPt, $layout['free']),
        ];
    }

    /**
     * Immagine di un pezzo, già pronta per dompdf (che non ritaglia in modo
     * affidabile): ritagliata → la finestra del pezzo (CroppedThumbnails),
     * grande come il pezzo; nel formato → la miniatura intera, centrata e
     * non deformata nel rettangolo principale. Posizione in pt dentro il pezzo.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $at
     * @return array{src: string, x: float, y: float, w: float, h: float}|null
     */
    private static function image(array $block, GridRect $cells, array $at): ?array
    {
        $file = $block['file'];
        $crop = $block['crop'];

        if ($file === null || $crop === null) {
            return null;
        }

        if ($crop->crops()) {
            $window = $crop->windowFor($cells);
            $src = $window !== null ? CroppedThumbnails::dataUri($file, $window) : null;

            return $src === null ? null : ['src' => $src, 'x' => 0.0, 'y' => 0.0, 'w' => $at['w'], 'h' => $at['h']];
        }

        $src = ExportThumbnail::dataUri($file);
        $ratio = (float) ($file->aspect_ratio ?: 1.0);

        if ($src === null) {
            return null;
        }

        [$w, $h] = $ratio < $at['w'] / $at['h'] ? [$at['h'] * $ratio, $at['h']] : [$at['w'], $at['w'] / $ratio];

        return ['src' => $src, 'x' => round(($at['w'] - $w) / 2, 2), 'y' => round(($at['h'] - $h) / 2, 2), 'w' => round($w, 2), 'h' => round($h, 2)];
    }

    /** @return list<array{x: float, y: float, w: float, h: float, img: array|null}> */
    private static function extraPieces(array $block, callable $toPt, bool $withThumbnails): array
    {
        $extra = [];

        foreach ($block['pieces'] as $index => $piece) {
            if ($piece === $block['rect']) {
                continue;
            }

            $at = $toPt($piece);
            // Stesso ordine: i pezzi in % e quelli del piano vengono dalla stessa lista di celle.
            $cells = $block['crop']?->pieces[$index]['cells'] ?? null;
            $extra[] = $at + ['img' => $withThumbnails && $cells !== null && $block['crop']->crops() ? self::image($block, $cells, $at) : null];
        }

        return $extra;
    }
}
