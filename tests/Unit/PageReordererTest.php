<?php

use App\Support\PageReorderer;

test('moving a page forward shifts the pages in between back by one', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3, 40 => 4, 50 => 5, 60 => 6];

    $changes = PageReorderer::move($positions, 20, 5);

    expect($changes)->toBe([
        30 => 2,
        40 => 3,
        50 => 4,
        20 => 5,
    ]);
});

test('moving a page backward shifts the pages in between forward by one', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3, 40 => 4, 50 => 5, 60 => 6];

    $changes = PageReorderer::move($positions, 50, 2);

    expect($changes)->toBe([
        50 => 2,
        20 => 3,
        30 => 4,
        40 => 5,
    ]);
});

test('moving a page to its own position produces no changes', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3];

    expect(PageReorderer::move($positions, 20, 2))->toBe([]);
});

test('a target position below 1 is clamped to the first position', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3];

    $changes = PageReorderer::move($positions, 30, -5);

    expect($changes)->toBe([
        30 => 1,
        10 => 2,
        20 => 3,
    ]);
});

test('a target position beyond the total is clamped to the last position', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3];

    $changes = PageReorderer::move($positions, 10, 999);

    expect($changes)->toBe([
        20 => 1,
        30 => 2,
        10 => 3,
    ]);
});

test('moving an unknown page id throws', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3];

    expect(fn () => PageReorderer::move($positions, 999, 1))
        ->toThrow(InvalidArgumentException::class);
});

test('swapping two pages exchanges only their two positions', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3, 40 => 4];

    $changes = PageReorderer::swap($positions, 20, 40);

    expect($changes)->toBe([
        20 => 4,
        40 => 2,
    ]);
});

test('swapping a page with itself produces no changes', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3];

    expect(PageReorderer::swap($positions, 20, 20))->toBe([]);
});

test('swapping an unknown page id throws', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3];

    expect(fn () => PageReorderer::swap($positions, 10, 999))
        ->toThrow(InvalidArgumentException::class);
});

test('moving a contiguous block preserves internal order and shifts the rest to fill the gap', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3, 40 => 4, 50 => 5, 60 => 6, 70 => 7];

    $changes = PageReorderer::moveBlock($positions, [20, 30], 6);

    expect($changes)->toBe([
        40 => 2,
        50 => 3,
        60 => 4,
        70 => 5,
        20 => 6,
        30 => 7,
    ]);
});

test('moving a non-contiguous block compacts it into a single contiguous run at the destination', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3, 40 => 4, 50 => 5, 60 => 6];

    // Blocco sparso (10, 30, 50 non sono adiacenti), passato in un ordine
    // qualunque (50 prima di 10) per verificare che l'ordine relativo
    // ORIGINALE (per posizione), non l'ordine dell'array, sia quello
    // rispettato al reinserimento.
    $changes = PageReorderer::moveBlock($positions, [50, 10, 30], 2);

    expect($changes)->toBe([
        20 => 1,
        10 => 2,
        50 => 4,
        40 => 5,
    ]);

    // Il blocco è ora davvero contiguo alla destinazione (posizioni 2,3,4),
    // nell'ordine originale 10 < 30 < 50 — non l'ordine [50, 10, 30] passato.
    $final = $positions;
    foreach ($changes as $id => $newPosition) {
        $final[$id] = $newPosition;
    }
    $order = collect($final)->sortBy(fn ($p) => $p)->keys()->values()->all();
    expect(array_slice($order, 1, 3))->toBe([10, 30, 50]);
});

test('a block move whose target exceeds the issue bounds is clamped to the end', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3, 40 => 4, 50 => 5];

    $changes = PageReorderer::moveBlock($positions, [10, 20], 999);

    expect($changes)->toBe([
        30 => 1,
        40 => 2,
        50 => 3,
        10 => 4,
        20 => 5,
    ]);
});

test('a block covering every page produces no changes, order is already preserved', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3];

    expect(PageReorderer::moveBlock($positions, [30, 10, 20], 2))->toBe([]);
});

test('moving a block with an unknown page id throws', function () {
    $positions = [10 => 1, 20 => 2, 30 => 3];

    expect(fn () => PageReorderer::moveBlock($positions, [10, 999], 1))
        ->toThrow(InvalidArgumentException::class);
});
