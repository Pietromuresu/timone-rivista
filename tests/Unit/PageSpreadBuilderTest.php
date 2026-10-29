<?php

use App\Support\PageSpreadBuilder;
use Illuminate\Support\Collection;

test('an empty page collection produces no spreads', function () {
    expect(PageSpreadBuilder::build(new Collection))->toBe([]);
});

test('a single page produces a single cover spread', function () {
    $spreads = PageSpreadBuilder::build(collect([1]));

    expect($spreads)->toBe([[1]]);
});

test('an odd number of pages leaves the cover alone and pairs the rest evenly', function () {
    $spreads = PageSpreadBuilder::build(collect([1, 2, 3, 4, 5]));

    expect($spreads)->toBe([
        [1],
        [2, 3],
        [4, 5],
    ]);
});

test('an even number of pages leaves the last page alone after the cover and pairs', function () {
    $spreads = PageSpreadBuilder::build(collect([1, 2, 3, 4, 5, 6]));

    expect($spreads)->toBe([
        [1],
        [2, 3],
        [4, 5],
        [6],
    ]);
});

// Griglia a doppia pagina (2026-10-29): accoppiamento dalla posizione.

function spreadPages(array $positions): Collection
{
    return collect($positions)->map(fn (int $position) => (object) ['position' => $position]);
}

/** Le posizioni di ogni coppia, «—» per lo slot vuoto. */
function spreadPositions(array $layout): array
{
    return array_map(fn (array $pair) => array_map(fn ($page) => $page?->position ?? '—', $pair), $layout);
}

test('side: even pages on the left, odd on the right, page 1 always on the right', function () {
    expect(PageSpreadBuilder::side(1))->toBe('right')
        ->and(PageSpreadBuilder::side(2))->toBe('left')
        ->and(PageSpreadBuilder::side(33))->toBe('right')
        ->and(PageSpreadBuilder::side(64))->toBe('left');
});

test('layout with an odd total: page 1 alone on the right (empty slot on the left for the II cover), then [2|3] … [4|5]', function () {
    expect(spreadPositions(PageSpreadBuilder::layout(spreadPages(range(1, 5)))))->toBe([['—', 1], [2, 3], [4, 5]]);
});

test('layout with an even total: the last page on the left, empty slot on the right (III cover)', function () {
    expect(spreadPositions(PageSpreadBuilder::layout(spreadPages(range(1, 6)))))->toBe([['—', 1], [2, 3], [4, 5], [6, '—']])
        ->and(spreadPositions(PageSpreadBuilder::layout(spreadPages([1]))))->toBe([['—', 1]])
        ->and(PageSpreadBuilder::layout(new Collection))->toBe([]);
});

test('layout is derived from the position: after a move every following page changes side by itself', function () {
    // Pagine A..F in posizione 1..6, poi la A va in fondo: le altre scalano di uno.
    $pages = collect(['A', 'B', 'C', 'D', 'E', 'F'])->map(fn ($name, $i) => (object) ['name' => $name, 'position' => $i + 1]);
    $names = fn () => array_map(fn ($pair) => array_map(fn ($p) => $p?->name ?? '—', $pair), PageSpreadBuilder::layout($pages->sortBy('position')));

    expect($names())->toBe([['—', 'A'], ['B', 'C'], ['D', 'E'], ['F', '—']]);

    $pages->each(fn ($page) => $page->position = $page->name === 'A' ? 6 : $page->position - 1);

    expect($names())->toBe([['—', 'B'], ['C', 'D'], ['E', 'F'], ['A', '—']]);
});

test('layout after inserting and deleting pages', function () {
    expect(spreadPositions(PageSpreadBuilder::layout(spreadPages(range(1, 7)))))->toBe([['—', 1], [2, 3], [4, 5], [6, 7]])
        ->and(spreadPositions(PageSpreadBuilder::layout(spreadPages(range(1, 4)))))->toBe([['—', 1], [2, 3], [4, '—']])
        // Una posizione mancante resta uno slot vuoto, non fa cambiare lato alle altre.
        ->and(spreadPositions(PageSpreadBuilder::layout(spreadPages([1, 2, 4, 5]))))->toBe([['—', 1], [2, '—'], [4, 5]]);
});
