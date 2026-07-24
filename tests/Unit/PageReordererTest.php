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
