<?php

namespace App\Support\PageGrid;

use App\Enums\ContentOrientation;

/**
 * Disposizione dei contenuti di una pagina sulla griglia 6×6 (revisione 2,
 * Fase 2.3, 2026-10-27; sostituisce l'impilamento calcolato della Fase 7a,
 * App\Support\PageLayoutPlanner, ora usato solo per convertire i dati).
 * Pura: nessun accesso al database.
 *
 * resolve():
 * 1. un rettangolo SALVATO resta dov'è se è dentro la pagina, ha l'area
 *    giusta per i dodicesimi attuali e non si sovrappone a uno già tenuto;
 * 2. gli altri contenuti rettangolari vengono messi nel primo posto libero
 *    (dall'alto a sinistra; il piedino dal basso), provando prima il punto
 *    in cui erano (se hanno cambiato misura) e la forma del loro
 *    orientamento, poi quella ruotata;
 * 3. un contenuto a L (7/12, 3/4, 11/12: GridShapes) prende le celle libere
 *    rimaste, in ordine di lettura — è "fluido": non ha frecce e si
 *    riadatta quando gli altri si spostano (scelta dell'utente);
 * 4. ciò che non ci sta (solo pagine oltre capienza da dati vecchi) finisce
 *    in `overflow`, disegnato a parte.
 * Il risultato è deterministico: due utenti vedono la stessa pagina anche
 * per i contenuti che non hanno ancora un rettangolo salvato.
 *
 * moveTarget()/rotateTarget() calcolano lo spostamento di una freccia e la
 * rotazione, senza mai sovrapporre due contenuti.
 */
final class PageGridPlanner
{
    public const UP = 'up';

    public const DOWN = 'down';

    public const LEFT = 'left';

    public const RIGHT = 'right';

    public const DIRECTIONS = [self::UP, self::DOWN, self::LEFT, self::RIGHT];

    /**
     * @param  list<array{id: int, twelfths: int, orientation: ContentOrientation, rect: GridRect|null}>  $items  nell'ordine della pagina
     * @return array{blocks: array<int, array{kind: 'rect'|'fluid'|'overflow', rect: GridRect|null, pieces: list<GridRect>}>, free: list<GridRect>, overfull: bool}
     */
    public static function resolve(array $items): array
    {
        $items = array_values(array_filter($items, fn (array $item) => $item['twelfths'] > 0));
        $layout = self::attempt($items, keepStored: true);

        // Frammentazione: i rettangoli salvati possono lasciare lo spazio
        // giusto ma nella forma sbagliata (due vetrine in diagonale + una
        // mezza pagina). Se la pagina NON è oltre capienza, si ridispone
        // tutta, con le posizioni salvate solo come preferenza.
        if ($layout['overfull'] && array_sum(array_column($items, 'twelfths')) <= \App\Support\Twelfths::PER_PAGE) {
            $retry = self::attempt($items, keepStored: false);

            if (! $retry['overfull']) {
                return $retry;
            }
        }

        return $layout;
    }

    private static function attempt(array $items, bool $keepStored): array
    {
        $placed = [];
        $pending = [];
        $fluid = [];

        foreach ($items as $item) {
            if (! GridShapes::isRectangular($item['twelfths'])) {
                $fluid[] = $item;

                continue;
            }

            $rect = $item['rect'];

            if ($keepStored && $rect !== null && $rect->insidePage() && $rect->area() === GridShapes::cells($item['twelfths']) && ! self::overlapsAny($rect, $placed)) {
                $placed[$item['id']] = $rect;
            } else {
                $pending[] = $item;
            }
        }

        $overflow = [];

        // Dal più grande: i piccoli riempiono i buchi lasciati dai grandi
        // (usort non è stabile per chiavi uguali in PHP < 8: si confronta
        // anche l'ordine originale).
        $order = array_flip(array_column($pending, 'id'));
        usort($pending, fn (array $a, array $b) => [$b['twelfths'], $order[$a['id']]] <=> [$a['twelfths'], $order[$b['id']]]);

        foreach ($pending as $item) {
            $found = self::placeNew($item, $placed);

            if ($found === null) {
                $overflow[] = $item['id'];
            } else {
                $placed[$item['id']] = $found;
            }
        }

        $occupied = self::occupancyMap($placed);
        $fluidPieces = [];

        foreach ($fluid as $item) {
            $needed = GridShapes::cells($item['twelfths']);
            $free = self::freeCells($occupied);

            if (count($free) < $needed) {
                $overflow[] = $item['id'];

                continue;
            }

            $taken = array_slice($free, 0, $needed);

            foreach ($taken as [$x, $y]) {
                $occupied[$y][$x] = true;
            }

            $fluidPieces[$item['id']] = self::mergeCells($taken);
        }

        $blocks = [];

        foreach ($items as $item) {
            $id = $item['id'];

            if (isset($placed[$id])) {
                $blocks[$id] = ['kind' => 'rect', 'rect' => $placed[$id], 'pieces' => [$placed[$id]]];
            } elseif (isset($fluidPieces[$id])) {
                $blocks[$id] = ['kind' => 'fluid', 'rect' => self::largest($fluidPieces[$id]), 'pieces' => $fluidPieces[$id]];
            }
        }

        // Pagina oltre capienza: ciò che non ci sta è una striscia in fondo,
        // sopra gli altri, segnalata (stesso caso di PageCapacity).
        foreach ($overflow as $id) {
            $strip = new GridRect(0, GridRect::SIZE - 1, GridRect::SIZE, 1);
            $blocks[$id] = ['kind' => 'overflow', 'rect' => $strip, 'pieces' => [$strip]];
        }

        return [
            'blocks' => $blocks,
            'free' => self::mergeCells(self::freeCells($occupied)),
            'overfull' => $overflow !== [],
        ];
    }

    /**
     * Nuovi rettangoli dopo una freccia: solo quello spostato, oppure i due
     * scambiati. Passo: il massimo divisore comune tra la misura e 6, così
     * il contenuto resta sulle posizioni valide del suo formato (1/4 a
     * passi di 3, 1/3 verticale di 2, 1/12 di 1). Se il posto è occupato da
     * un solo contenuto che insieme a questo forma un rettangolo lungo la
     * direzione della freccia (stessa altezza per ←→, stessa larghezza per
     * ↑↓, affiancati), i due si scambiano di posto lì dentro; altrimenti,
     * o se si uscirebbe dalla pagina, null (freccia disattivata).
     *
     * @param  array<int, GridRect>  $rects  solo i contenuti rettangolari (i fluidi si riadattano)
     * @return array<int, GridRect>|null
     */
    public static function moveTarget(array $rects, int $id, string $direction): ?array
    {
        $a = $rects[$id] ?? null;

        if ($a === null || ! in_array($direction, self::DIRECTIONS, true)) {
            return null;
        }

        $horizontal = $direction === self::LEFT || $direction === self::RIGHT;
        $sign = $direction === self::RIGHT || $direction === self::DOWN ? 1 : -1;
        $step = self::gcd($horizontal ? $a->w : $a->h, GridRect::SIZE);
        $target = $horizontal ? $a->at($a->x + $sign * $step, $a->y) : $a->at($a->x, $a->y + $sign * $step);

        if (! $target->insidePage()) {
            return null;
        }

        $others = $rects;
        unset($others[$id]);
        $hit = array_filter($others, fn (GridRect $r) => $r->overlaps($target));

        if ($hit === []) {
            return [$id => $target];
        }

        if (count($hit) > 1) {
            return null;
        }

        $bId = array_key_first($hit);
        $b = $hit[$bId];

        if ($horizontal) {
            $adjacent = $sign > 0 ? $b->x === $a->x + $a->w : $b->x + $b->w === $a->x;

            if (! $adjacent || $b->y !== $a->y || $b->h !== $a->h) {
                return null;
            }

            [$newA, $newB] = $sign > 0
                ? [$a->at($a->x + $b->w, $a->y), $b->at($a->x, $b->y)]
                : [$a->at($b->x, $a->y), $b->at($b->x + $a->w, $b->y)];
        } else {
            $adjacent = $sign > 0 ? $b->y === $a->y + $a->h : $b->y + $b->h === $a->y;

            if (! $adjacent || $b->x !== $a->x || $b->w !== $a->w) {
                return null;
            }

            [$newA, $newB] = $sign > 0
                ? [$a->at($a->x, $a->y + $b->h), $b->at($b->x, $a->y)]
                : [$a->at($a->x, $b->y), $b->at($b->x, $b->y + $a->h)];
        }

        return [$id => $newA, $bId => $newB];
    }

    /**
     * Rettangolo ruotato (larghezza ↔ altezza): nello stesso angolo se ci
     * sta (spostato dentro la pagina se sborda), altrimenti nel primo posto
     * libero; null se è quadrato o non c'è posto.
     *
     * @param  array<int, GridRect>  $rects
     */
    public static function rotateTarget(array $rects, int $id): ?GridRect
    {
        $a = $rects[$id] ?? null;

        if ($a === null || $a->w === $a->h) {
            return null;
        }

        $others = $rects;
        unset($others[$id]);
        $rotated = $a->transposed();
        $clamped = $rotated->at(min($rotated->x, GridRect::SIZE - $rotated->w), min($rotated->y, GridRect::SIZE - $rotated->h));

        if ($clamped->insidePage() && ! self::overlapsAny($clamped, $others)) {
            return $clamped;
        }

        return self::firstFit($rotated->w, $rotated->h, $others, false);
    }

    /**
     * Rettangoli dei soli contenuti rettangolari di un resolve(), per
     * moveTarget()/rotateTarget().
     *
     * @return array<int, GridRect>
     */
    public static function rectsOf(array $layout): array
    {
        $rects = [];

        foreach ($layout['blocks'] as $id => $block) {
            if ($block['kind'] === 'rect') {
                $rects[$id] = $block['rect'];
            }
        }

        return $rects;
    }

    /** @param  array<int, GridRect>  $placed */
    private static function placeNew(array $item, array $placed): ?GridRect
    {
        $shape = GridShapes::shapeFor($item['twelfths'], $item['orientation']);
        $shapes = [$shape];

        if ($shape['w'] !== $shape['h']) {
            $shapes[] = ['w' => $shape['h'], 'h' => $shape['w']];
        }

        // Ha cambiato misura (o era sovrapposto): stesso angolo, se può.
        if ($item['rect'] !== null) {
            foreach ($shapes as $candidate) {
                $rect = new GridRect($item['rect']->x, $item['rect']->y, $candidate['w'], $candidate['h']);

                if ($rect->insidePage() && ! self::overlapsAny($rect, $placed)) {
                    return $rect;
                }
            }
        }

        foreach ($shapes as $candidate) {
            $rect = self::firstFit($candidate['w'], $candidate['h'], $placed, $item['orientation'] === ContentOrientation::Bottom);

            if ($rect !== null) {
                return $rect;
            }
        }

        return null;
    }

    /** @param  array<int, GridRect>  $placed */
    private static function firstFit(int $w, int $h, array $placed, bool $fromBottom): ?GridRect
    {
        $rows = range(0, GridRect::SIZE - $h);

        if ($fromBottom) {
            $rows = array_reverse($rows);
        }

        foreach ($rows as $y) {
            for ($x = 0; $x <= GridRect::SIZE - $w; $x++) {
                $rect = new GridRect($x, $y, $w, $h);

                if (! self::overlapsAny($rect, $placed)) {
                    return $rect;
                }
            }
        }

        return null;
    }

    /** @param  array<int, GridRect>  $rects */
    private static function overlapsAny(GridRect $rect, array $rects): bool
    {
        foreach ($rects as $other) {
            if ($rect->overlaps($other)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, GridRect>  $placed
     * @return array<int, array<int, bool>> [y][x]
     */
    private static function occupancyMap(array $placed): array
    {
        $map = array_fill(0, GridRect::SIZE, array_fill(0, GridRect::SIZE, false));

        foreach ($placed as $rect) {
            foreach ($rect->cells() as [$x, $y]) {
                $map[$y][$x] = true;
            }
        }

        return $map;
    }

    /** @return list<array{0: int, 1: int}> celle libere in ordine di lettura */
    private static function freeCells(array $occupied): array
    {
        $free = [];

        for ($y = 0; $y < GridRect::SIZE; $y++) {
            for ($x = 0; $x < GridRect::SIZE; $x++) {
                if (! $occupied[$y][$x]) {
                    $free[] = [$x, $y];
                }
            }
        }

        return $free;
    }

    /**
     * Celle → pochi rettangoli: tratti orizzontali per riga, poi fusi con
     * quelli identici delle righe sotto.
     *
     * @param  list<array{0: int, 1: int}>  $cells
     * @return list<GridRect>
     */
    public static function mergeCells(array $cells): array
    {
        $byRow = [];

        foreach ($cells as [$x, $y]) {
            $byRow[$y][$x] = true;
        }

        $runs = [];
        ksort($byRow);

        foreach ($byRow as $y => $xs) {
            ksort($xs);
            $start = null;
            $prev = null;

            foreach (array_keys($xs) as $x) {
                if ($start !== null && $x !== $prev + 1) {
                    $runs[] = new GridRect($start, $y, $prev - $start + 1, 1);
                    $start = null;
                }

                $start ??= $x;
                $prev = $x;
            }

            if ($start !== null) {
                $runs[] = new GridRect($start, $y, $prev - $start + 1, 1);
            }
        }

        $merged = [];

        foreach ($runs as $run) {
            $extended = false;

            foreach ($merged as $i => $rect) {
                if ($rect->x === $run->x && $rect->w === $run->w && $rect->y + $rect->h === $run->y) {
                    $merged[$i] = new GridRect($rect->x, $rect->y, $rect->w, $rect->h + 1);
                    $extended = true;

                    break;
                }
            }

            if (! $extended) {
                $merged[] = $run;
            }
        }

        return array_values($merged);
    }

    /** @param  list<GridRect>  $pieces */
    private static function largest(array $pieces): GridRect
    {
        usort($pieces, fn (GridRect $a, GridRect $b) => $b->area() <=> $a->area());

        return $pieces[0];
    }

    private static function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : self::gcd($b, $a % $b);
    }
}
