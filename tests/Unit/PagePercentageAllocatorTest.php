<?php

use App\Support\PagePercentageAllocator;

test('an empty page has all 100% free', function () {
    expect(PagePercentageAllocator::freeSpace([]))->toBe(100.0);
});

test('free space subtracts already occupied percentages', function () {
    expect(PagePercentageAllocator::freeSpace([50, 25]))->toBe(25.0);
});

test('a candidate filling exactly the empty page fits', function () {
    expect(PagePercentageAllocator::fits([], 100))->toBeTrue();
});

test('a candidate exceeding the empty page does not fit', function () {
    expect(PagePercentageAllocator::fits([], 100.01))->toBeFalse();
});

test('a candidate filling exactly the remaining space fits', function () {
    expect(PagePercentageAllocator::fits([50], 50))->toBeTrue();
});

test('a candidate exceeding the remaining space does not fit', function () {
    expect(PagePercentageAllocator::fits([50], 50.01))->toBeFalse();
});

test('a zero or negative candidate never fits', function () {
    expect(PagePercentageAllocator::fits([], 0))->toBeFalse()
        ->and(PagePercentageAllocator::fits([], -1))->toBeFalse();
});

test('rounding is applied before comparing against the 100% limit', function () {
    expect(PagePercentageAllocator::fits([33.3, 33.3, 33.3], 0.1))->toBeTrue()
        ->and(PagePercentageAllocator::fits([33.3, 33.3, 33.3], 0.2))->toBeFalse();
});
