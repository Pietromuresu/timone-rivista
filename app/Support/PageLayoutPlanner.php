<?php

namespace App\Support;

use App\Enums\ContentOrientation;

/**
 * Disposizione dei contenuti nella card di una pagina (Fase 7a,
 * 2026-10-27, approccio "B" scelto dall'utente: impilamento proporzionale
 * con orientamento). Pura: riceve i contenuti della pagina con i loro
 * dodicesimi e orientamento, restituisce rettangoli in percentuale della
 * card. Nulla viene salvato: la disposizione si ricalcola a ogni render,
 * quindi non c'è geometria da tenere allineata ai dati.
 *
 * Regola:
 * - i contenuti verticali sono colonne a tutta altezza affiancate da
 *   sinistra, larghe dodicesimi/12;
 * - gli orizzontali si impilano dall'alto nella larghezza rimasta R, alti
 *   (dodicesimi/12)/R: l'AREA di ogni blocco resta esattamente la sua
 *   quota di pagina (1/4 in mezza larghezza = metà altezza);
 * - i piedini si impilano dal basso, nella stessa larghezza;
 * - ciò che resta è lo spazio libero (un rettangolo, o nessuno).
 * Ci sta sempre perché la pagina non supera 12 dodicesimi. Una pagina
 * oltre capienza (solo da dati convertiti, vedi PageCapacity) viene
 * disegnata come semplici righe in proporzione tra loro, segnalata da
 * `overfull`.
 */
final class PageLayoutPlanner
{
    /**
     * @param  list<array{id: int, twelfths: int, orientation: ContentOrientation}>  $items  nell'ordine della pagina
     * @return array{blocks: list<array{id: int, x: float, y: float, w: float, h: float}>, free: array{x: float, y: float, w: float, h: float}|null, overfull: bool}
     */
    public static function plan(array $items): array
    {
        $items = array_values(array_filter($items, fn (array $item) => $item['twelfths'] > 0));
        $total = array_sum(array_column($items, 'twelfths'));

        if ($total > Twelfths::PER_PAGE) {
            return self::overfull($items, $total);
        }

        $blocks = [];
        $x = 0.0;

        foreach ($items as $item) {
            if ($item['orientation'] === ContentOrientation::Vertical) {
                $width = $item['twelfths'] / Twelfths::PER_PAGE * 100;
                $blocks[$item['id']] = ['id' => $item['id'], 'x' => $x, 'y' => 0.0, 'w' => $width, 'h' => 100.0];
                $x += $width;
            }
        }

        $remainingWidth = 100.0 - $x;
        $top = 0.0;
        $bottom = 100.0;

        foreach ($items as $item) {
            if ($item['orientation'] === ContentOrientation::Vertical || $remainingWidth <= 0.0) {
                continue;
            }

            $height = ($item['twelfths'] / Twelfths::PER_PAGE) / ($remainingWidth / 100) * 100;

            if ($item['orientation'] === ContentOrientation::Bottom) {
                $bottom -= $height;
                $blocks[$item['id']] = ['id' => $item['id'], 'x' => $x, 'y' => $bottom, 'w' => $remainingWidth, 'h' => $height];
            } else {
                $blocks[$item['id']] = ['id' => $item['id'], 'x' => $x, 'y' => $top, 'w' => $remainingWidth, 'h' => $height];
                $top += $height;
            }
        }

        $freeHeight = $bottom - $top;
        $free = $remainingWidth > 0.01 && $freeHeight > 0.01
            ? ['x' => $x, 'y' => $top, 'w' => $remainingWidth, 'h' => $freeHeight]
            : null;

        // Ordine della pagina conservato (l'ordine dei blocchi nell'HTML è
        // quello di lettura, utile anche alla tastiera e agli screen reader).
        $ordered = [];
        foreach ($items as $item) {
            $ordered[] = self::rounded($blocks[$item['id']]);
        }

        return ['blocks' => $ordered, 'free' => $free === null ? null : self::rounded($free), 'overfull' => false];
    }

    /** @param  list<array{id: int, twelfths: int, orientation: ContentOrientation}>  $items */
    private static function overfull(array $items, int $total): array
    {
        $y = 0.0;
        $blocks = [];

        foreach ($items as $item) {
            $height = $item['twelfths'] / $total * 100;
            $blocks[] = self::rounded(['id' => $item['id'], 'x' => 0.0, 'y' => $y, 'w' => 100.0, 'h' => $height]);
            $y += $height;
        }

        return ['blocks' => $blocks, 'free' => null, 'overfull' => true];
    }

    private static function rounded(array $rect): array
    {
        foreach (['x', 'y', 'w', 'h'] as $key) {
            $rect[$key] = round($rect[$key], 4);
        }

        return $rect;
    }
}
