<?php

namespace App\Support;

use App\Models\Page;

/**
 * Disposizione dei contenuti di una pagina nell'export PDF del timone
 * (2026-10-27, richiesta dell'utente: "nel pdf c'è solo una miniatura, non
 * tutte quelle della pagina"). Stessa disposizione della card (Page::layout()
 * → PageLayoutPlanner, Fase 7a), convertita da percentuali a punti: dompdf
 * posiziona in modo affidabile solo con unità assolute dentro un contenitore
 * di dimensioni fisse. Ogni blocco porta la miniatura del PROPRIO contenuto
 * (ExportThumbnail), o null se non c'è: la vista lo disegna come riquadro
 * colorato.
 */
final class ExportPageLayout
{
    /**
     * @return array{blocks: list<array{x: float, y: float, w: float, h: float, src: string|null, isAd: bool, placeholder: bool, label: string, fraction: string}>, free: array{x: float, y: float, w: float, h: float}|null}|null null se la pagina non ha contenuti
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
            'blocks' => array_map(fn (array $block) => $toPt($block['rect']) + [
                'src' => $withThumbnails ? ExportThumbnail::dataUri($block['file']) : null,
                'isAd' => $block['content']->type->value === 'pubblicita',
                'placeholder' => $block['content']->isPlaceholder(),
                'label' => $block['content']->displayLabel(),
                'fraction' => Twelfths::fractionLabel((int) $block['content']->pivot->occupied_twelfths),
            ], $layout['blocks']),
            'free' => $layout['free'] !== null ? $toPt($layout['free']) : null,
        ];
    }
}
