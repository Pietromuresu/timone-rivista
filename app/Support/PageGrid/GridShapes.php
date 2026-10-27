<?php

namespace App\Support\PageGrid;

use App\Enums\ContentOrientation;
use App\Support\Twelfths;

/**
 * Forma (larghezza × altezza in celle) di un contenuto sulla griglia 6×6
 * a partire dai suoi dodicesimi su quella pagina e dall'orientamento
 * (revisione 2, Fase 2.3). Un dodicesimo = 3 celle.
 *
 * Orizzontale (articoli, mezza orizzontale, vetrina, piedino…): il più
 * largo possibile — a tutta larghezza quando le celle sono un multiplo di
 * 6 (1/3 = 6×2, 1/2 = 6×3, 2/3 = 6×4), altrimenti 3×1 (1/12), 3×3 (1/4),
 * 5×3 (5/12). Verticale: la stessa forma ruotata (1/2 = 3×6, 1/3 = 2×6,
 * 2/3 = 4×6). Sono esattamente le misure della specifica.
 *
 * 7/12, 3/4 e 11/12 (21, 27, 33 celle) non sono rettangoli dentro 6×6:
 * per scelta dell'utente restano permessi come forma «a L», che riempie le
 * celle libere lasciate dagli altri (PageGridPlanner): qui null.
 */
final class GridShapes
{
    public const CELLS_PER_TWELFTH = 3;

    /** @return array{w: int, h: int}|null null = non rettangolare (forma a L) */
    public static function shapeFor(int $twelfths, ContentOrientation $orientation): ?array
    {
        $wide = self::wideShape($twelfths);

        if ($wide === null) {
            return null;
        }

        return $orientation === ContentOrientation::Vertical
            ? ['w' => $wide['h'], 'h' => $wide['w']]
            : $wide;
    }

    public static function isRectangular(int $twelfths): bool
    {
        return self::wideShape($twelfths) !== null;
    }

    public static function cells(int $twelfths): int
    {
        return $twelfths * self::CELLS_PER_TWELFTH;
    }

    /** @return array{w: int, h: int}|null */
    private static function wideShape(int $twelfths): ?array
    {
        if ($twelfths < 1 || $twelfths > Twelfths::PER_PAGE) {
            return null;
        }

        $cells = self::cells($twelfths);

        if ($cells % GridRect::SIZE === 0) {
            return ['w' => GridRect::SIZE, 'h' => intdiv($cells, GridRect::SIZE)];
        }

        // Il rettangolo più largo (al massimo 6) con area esatta: 3×1, 3×3, 5×3.
        for ($w = GridRect::SIZE - 1; $w >= 1; $w--) {
            if ($cells % $w === 0 && intdiv($cells, $w) <= GridRect::SIZE && intdiv($cells, $w) <= $w) {
                return ['w' => $w, 'h' => intdiv($cells, $w)];
            }
        }

        return null;
    }
}
