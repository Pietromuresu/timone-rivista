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
