<?php

use App\Enums\AdFormat;
use App\Enums\ContentOrientation;
use App\Support\PageLayoutPlanner;

// Fase 7a (2026-10-27): disposizione proporzionale dei contenuti nella card.

function item(int $id, int $twelfths, ContentOrientation $orientation = ContentOrientation::Horizontal): array
{
    return ['id' => $id, 'twelfths' => $twelfths, 'orientation' => $orientation];
}

function area(array $rect): float
{
    return round($rect['w'] * $rect['h'] / 100, 2); // % della pagina
}

test('a full page article fills the card', function () {
    $plan = PageLayoutPlanner::plan([item(1, 12)]);

    expect($plan['blocks'])->toBe([['id' => 1, 'x' => 0.0, 'y' => 0.0, 'w' => 100.0, 'h' => 100.0]])
        ->and($plan['free'])->toBeNull();
});

test('horizontal contents stack from the top, the rest is free space', function () {
    $plan = PageLayoutPlanner::plan([item(1, 6), item(2, 3)]);

    expect($plan['blocks'][0])->toMatchArray(['y' => 0.0, 'h' => 50.0, 'w' => 100.0])
        ->and($plan['blocks'][1])->toMatchArray(['y' => 50.0, 'h' => 25.0])
        ->and($plan['free'])->toBe(['x' => 0.0, 'y' => 75.0, 'w' => 100.0, 'h' => 25.0]);
});

test('a vertical half is a full-height column and a quarter beside it keeps its exact area', function () {
    $plan = PageLayoutPlanner::plan([item(1, 6, ContentOrientation::Vertical), item(2, 3), item(3, 3)]);

    expect($plan['blocks'][0])->toBe(['id' => 1, 'x' => 0.0, 'y' => 0.0, 'w' => 50.0, 'h' => 100.0])
        ->and($plan['blocks'][1])->toBe(['id' => 2, 'x' => 50.0, 'y' => 0.0, 'w' => 50.0, 'h' => 50.0])
        ->and($plan['blocks'][2])->toBe(['id' => 3, 'x' => 50.0, 'y' => 50.0, 'w' => 50.0, 'h' => 50.0])
        ->and(area($plan['blocks'][1]))->toBe(25.0)
        ->and($plan['free'])->toBeNull();
});

test('a 2/3 column and a 1/3 horizontal fill the page exactly', function () {
    $plan = PageLayoutPlanner::plan([item(1, 8, ContentOrientation::Vertical), item(2, 4)]);

    expect($plan['blocks'][0]['w'])->toBe(66.6667)
        ->and($plan['blocks'][1])->toMatchArray(['x' => 66.6667, 'w' => 33.3333, 'h' => 100.0])
        ->and($plan['free'])->toBeNull();
});

test('a piedino is anchored at the bottom, free space stays between', function () {
    $plan = PageLayoutPlanner::plan([item(1, 4, ContentOrientation::Bottom), item(2, 6)]);

    expect($plan['blocks'][0])->toMatchArray(['y' => 66.6667, 'h' => 33.3333])
        ->and($plan['blocks'][1])->toMatchArray(['y' => 0.0, 'h' => 50.0])
        ->and($plan['free'])->toMatchArray(['y' => 50.0, 'h' => 16.6667]);
});

test('every block area equals its share of the page, whatever the mix', function () {
    $plan = PageLayoutPlanner::plan([
        item(1, 4, ContentOrientation::Vertical),
        item(2, 3),
        item(3, 2, ContentOrientation::Bottom),
        item(4, 1),
    ]);

    expect(array_map('area', $plan['blocks']))->toBe([33.33, 25.0, 16.67, 8.33]);
});

test('blocks keep the page order (reading order)', function () {
    $plan = PageLayoutPlanner::plan([item(7, 3), item(5, 6, ContentOrientation::Vertical)]);

    expect(array_column($plan['blocks'], 'id'))->toBe([7, 5]);
});

test('an overfull page is drawn as proportional rows and flagged', function () {
    $plan = PageLayoutPlanner::plan([item(1, 12), item(2, 6)]);

    expect($plan['overfull'])->toBeTrue()
        ->and($plan['blocks'][0]['h'])->toBe(66.6667)
        ->and($plan['blocks'][1]['h'])->toBe(33.3333)
        ->and($plan['free'])->toBeNull();
});

test('ad formats get their orientation from the rate card sizes', function () {
    expect(AdFormat::MezzaPaginaVerticale->orientation())->toBe(ContentOrientation::Vertical)
        ->and(AdFormat::UnTerzoPaginaVerticale->orientation())->toBe(ContentOrientation::Vertical)
        ->and(AdFormat::DueTerziPagina->orientation())->toBe(ContentOrientation::Vertical)
        ->and(AdFormat::MezzaPaginaOrizzontale->orientation())->toBe(ContentOrientation::Horizontal)
        ->and(AdFormat::UnQuartoPagina->orientation())->toBe(ContentOrientation::Horizontal)
        ->and(AdFormat::PaginaIntera->orientation())->toBe(ContentOrientation::Horizontal)
        ->and(AdFormat::UnTerzoPaginaOrizzontale->orientation())->toBe(ContentOrientation::Horizontal)
        ->and(AdFormat::Piedino->orientation())->toBe(ContentOrientation::Bottom);
});
