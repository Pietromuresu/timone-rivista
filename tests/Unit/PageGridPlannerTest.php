<?php

use App\Enums\ContentOrientation;
use App\Support\PageGrid\GridRect;
use App\Support\PageGrid\GridShapes;
use App\Support\PageGrid\PageGridPlanner;

// Revisione 2, Fase 2.3 (2026-10-27): griglia 6×6.

function gridItem(int $id, int $twelfths, ContentOrientation $orientation = ContentOrientation::Horizontal, ?GridRect $rect = null): array
{
    return ['id' => $id, 'twelfths' => $twelfths, 'orientation' => $orientation, 'rect' => $rect];
}

function rectOf(array $layout, int $id): string
{
    return $layout['blocks'][$id]['rect']->label();
}

test('every format of the specification has its exact rectangle', function (int $twelfths, ContentOrientation $orientation, array $shape) {
    expect(GridShapes::shapeFor($twelfths, $orientation))->toBe($shape);
})->with([
    'intera' => [12, ContentOrientation::Horizontal, ['w' => 6, 'h' => 6]],
    '1/2 verticale' => [6, ContentOrientation::Vertical, ['w' => 3, 'h' => 6]],
    '1/2 orizzontale' => [6, ContentOrientation::Horizontal, ['w' => 6, 'h' => 3]],
    '1/3 verticale' => [4, ContentOrientation::Vertical, ['w' => 2, 'h' => 6]],
    '1/3 orizzontale (piedino)' => [4, ContentOrientation::Bottom, ['w' => 6, 'h' => 2]],
    '2/3 verticale' => [8, ContentOrientation::Vertical, ['w' => 4, 'h' => 6]],
    '2/3 orizzontale' => [8, ContentOrientation::Horizontal, ['w' => 6, 'h' => 4]],
    '1/4 vetrina' => [3, ContentOrientation::Horizontal, ['w' => 3, 'h' => 3]],
    '1/12' => [1, ContentOrientation::Horizontal, ['w' => 3, 'h' => 1]],
    '5/12' => [5, ContentOrientation::Horizontal, ['w' => 5, 'h' => 3]],
]);

test('7/12, 3/4 and 11/12 are not rectangles in a 6×6 grid', function (int $twelfths) {
    expect(GridShapes::shapeFor($twelfths, ContentOrientation::Horizontal))->toBeNull();
})->with([7, 9, 11]);

test('contents without a stored rectangle go in the first free place, the footer at the bottom', function () {
    $layout = PageGridPlanner::resolve([
        gridItem(1, 6, ContentOrientation::Vertical),
        gridItem(2, 3),
        gridItem(3, 3, ContentOrientation::Bottom),
    ]);

    expect(rectOf($layout, 1))->toBe('3×6 in (0,0)')
        ->and(rectOf($layout, 2))->toBe('3×3 in (3,0)')
        ->and(rectOf($layout, 3))->toBe('3×3 in (3,3)')
        ->and($layout['free'])->toBe([])
        ->and($layout['overfull'])->toBeFalse();
});

test('a stored rectangle stays where it is; an invalid or overlapping one is placed again', function () {
    $layout = PageGridPlanner::resolve([
        gridItem(1, 3, rect: new GridRect(3, 3, 3, 3)),
        gridItem(2, 3, rect: new GridRect(3, 3, 3, 3)), // sovrapposto al primo
        gridItem(3, 6, rect: new GridRect(0, 0, 3, 3)), // area sbagliata per 1/2
    ]);

    // Prima il più grande: la mezza pagina (area sbagliata) torna nel suo
    // angolo con la sua forma; la vetrina sovrapposta va nel primo posto
    // libero.
    expect(rectOf($layout, 1))->toBe('3×3 in (3,3)')
        ->and(rectOf($layout, 3))->toBe('6×3 in (0,0)')
        ->and(rectOf($layout, 2))->toBe('3×3 in (0,3)')
        ->and($layout['overfull'])->toBeFalse();
});

test('a page that is not overfull is rearranged when the stored rectangles leave no room for a new content', function () {
    // Due vetrine in diagonale: 18 celle libere, ma nessun 6×3 o 3×6.
    $layout = PageGridPlanner::resolve([
        gridItem(1, 3, rect: new GridRect(0, 0, 3, 3)),
        gridItem(2, 3, rect: new GridRect(3, 3, 3, 3)),
        gridItem(3, 6),
    ]);

    expect($layout['overfull'])->toBeFalse()
        ->and(array_sum(array_map(fn ($b) => $b['rect']->area(), $layout['blocks'])))->toBe(36)
        ->and(rectOf($layout, 2))->toBe('3×3 in (3,3)'); // chi poteva restare al suo posto ci resta
});

test('a 3/4 around a 1/4 is an L that fills the free cells and has no arrows', function () {
    $layout = PageGridPlanner::resolve([
        gridItem(1, 3, rect: new GridRect(3, 3, 3, 3)),
        gridItem(2, 9),
    ]);

    $pieces = array_map(fn (GridRect $r) => $r->label(), $layout['blocks'][2]['pieces']);

    expect($layout['blocks'][2]['kind'])->toBe('fluid')
        ->and($pieces)->toBe(['6×3 in (0,0)', '3×3 in (0,3)'])
        ->and(array_sum(array_map(fn ($r) => $r->area(), $layout['blocks'][2]['pieces'])))->toBe(27)
        ->and(PageGridPlanner::rectsOf($layout))->toHaveKeys([1])->not->toHaveKey(2);
});

test('arrows move by one step on the valid positions of the format and never leave the page', function () {
    $rects = [1 => new GridRect(0, 0, 2, 6)]; // 1/3 verticale

    expect(PageGridPlanner::moveTarget($rects, 1, 'right')[1]->label())->toBe('2×6 in (2,0)')
        ->and(PageGridPlanner::moveTarget($rects, 1, 'left'))->toBeNull()
        ->and(PageGridPlanner::moveTarget($rects, 1, 'up'))->toBeNull()
        ->and(PageGridPlanner::moveTarget([1 => new GridRect(0, 0, 3, 3)], 1, 'down')[1]->label())->toBe('3×3 in (0,3)');
});

test('moving onto a content that forms a common rectangle swaps them, otherwise the arrow is disabled', function () {
    // 1/3 orizzontale sopra 2/3 orizzontale: stessa larghezza, affiancati.
    $rects = [1 => new GridRect(0, 0, 6, 2), 2 => new GridRect(0, 2, 6, 4)];
    $swap = PageGridPlanner::moveTarget($rects, 1, 'down');

    expect($swap[1]->label())->toBe('6×2 in (0,4)')
        ->and($swap[2]->label())->toBe('6×4 in (0,0)');

    // Due vetrine affiancate: scambio orizzontale.
    $vetrine = PageGridPlanner::moveTarget([1 => new GridRect(0, 0, 3, 3), 2 => new GridRect(3, 0, 3, 3)], 1, 'right');
    expect($vetrine[1]->label())->toBe('3×3 in (3,0)')->and($vetrine[2]->label())->toBe('3×3 in (0,0)');

    // Vetrina sopra una mezza orizzontale: non formano un rettangolo → nessuno scambio.
    expect(PageGridPlanner::moveTarget([1 => new GridRect(0, 0, 3, 3), 2 => new GridRect(0, 3, 6, 3)], 1, 'down'))->toBeNull();
});

test('rotation keeps the corner when there is room, otherwise the first free place; squares do not rotate', function () {
    expect(PageGridPlanner::rotateTarget([1 => new GridRect(0, 0, 6, 3)], 1)->label())->toBe('3×6 in (0,0)')
        ->and(PageGridPlanner::rotateTarget([1 => new GridRect(0, 3, 6, 3)], 1)->label())->toBe('3×6 in (0,0)')
        ->and(PageGridPlanner::rotateTarget([1 => new GridRect(0, 0, 6, 3), 2 => new GridRect(0, 3, 6, 3)], 1))->toBeNull()
        ->and(PageGridPlanner::rotateTarget([1 => new GridRect(0, 0, 3, 3)], 1))->toBeNull();
});

test('contents that do not fit (old overfull pages) are flagged, not lost', function () {
    $layout = PageGridPlanner::resolve([gridItem(1, 12), gridItem(2, 3)]);

    expect($layout['overfull'])->toBeTrue()
        ->and($layout['blocks'][2]['kind'])->toBe('overflow');
});

test('free space is returned as merged rectangles', function () {
    $layout = PageGridPlanner::resolve([gridItem(1, 4)]); // 6×2 in alto

    expect(array_map(fn (GridRect $r) => $r->label(), $layout['free']))->toBe(['6×4 in (0,2)']);
});
