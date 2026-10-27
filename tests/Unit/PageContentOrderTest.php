<?php

use App\Support\PageContentOrder;

// Fase 7b (2026-10-27): spostamento di un contenuto nell'ordine della pagina.

test('moving earlier or later swaps with the neighbour', function () {
    expect(PageContentOrder::move([10, 20, 30], 20, -1))->toBe([20, 10, 30])
        ->and(PageContentOrder::move([10, 20, 30], 20, 1))->toBe([10, 30, 20]);
});

test('at the edges nothing moves', function () {
    expect(PageContentOrder::move([10, 20, 30], 10, -1))->toBeNull()
        ->and(PageContentOrder::move([10, 20, 30], 30, 1))->toBeNull();
});

test('unknown contents and steps other than one are refused', function () {
    expect(PageContentOrder::move([10, 20], 99, 1))->toBeNull()
        ->and(PageContentOrder::move([10, 20], 10, 2))->toBeNull();
});
