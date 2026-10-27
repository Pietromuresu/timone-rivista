<?php

use App\Enums\PageFillStatus;
use App\Support\PageCapacity;
use App\Support\Twelfths;

// Fase 3 (2026-10-27): capienza della pagina in dodicesimi.

// Test richiesto dalla specifica: 6 + 4 + 3 = 13 → errore.
test('a page with 6 + 4 + 3 twelfths does not fit (13 > 12)', function () {
    expect(PageCapacity::fits([6, 4], 3))->toBeFalse()
        ->and(PageCapacity::status(13))->toBe(PageFillStatus::Overfull);
});

// Test richiesto dalla specifica: 6 + 3 + 3 = 12 → piena.
test('a page with 6 + 3 + 3 twelfths fits exactly and is full', function () {
    expect(PageCapacity::fits([6, 3], 3))->toBeTrue()
        ->and(PageCapacity::used([6, 3, 3]))->toBe(12)
        ->and(PageCapacity::free([6, 3, 3]))->toBe(0)
        ->and(PageCapacity::status(12))->toBe(PageFillStatus::Full)
        ->and(PageCapacity::statusLabel(12))->toBe('Piena');
});

test('empty and partial pages are told apart, with the free space', function () {
    expect(PageCapacity::status(0))->toBe(PageFillStatus::Empty)
        ->and(PageCapacity::statusLabel(0))->toBe('Vuota')
        ->and(PageCapacity::status(8))->toBe(PageFillStatus::Partial)
        ->and(PageCapacity::statusLabel(8))->toBe('Libero: 1/3')
        ->and(PageCapacity::statusLabel(14))->toBe('Oltre capienza (+1/6)');
});

test('zero twelfths never fit (a content always takes some space)', function () {
    expect(PageCapacity::fits([], 0))->toBeFalse();
});

test('the insufficient space message says how much is left', function () {
    expect(PageCapacity::insufficientSpaceMessage('pagina 4', 6, 4, 'Articolo'))
        ->toBe('Spazio insufficiente sulla pagina 4: «Articolo» richiede 1/2 di pagina, ma resta libero solo 1/3 di pagina.')
        ->and(PageCapacity::insufficientSpaceMessage('pagina 4', 3, 0))
        ->toBe('Spazio insufficiente sulla pagina 4: richiesti 1/4 di pagina, ma la pagina è già piena.');
});

test('twelfths read as fractions and page counts', function () {
    expect(Twelfths::fractionLabel(6))->toBe('1/2')
        ->and(Twelfths::fractionLabel(5))->toBe('5/12')
        ->and(Twelfths::fractionLabel(9))->toBe('3/4')
        ->and(Twelfths::pagesLabel(52))->toBe('4 e 1/3 pagine')
        ->and(Twelfths::pagesLabel(24))->toBe('2 pagine')
        ->and(Twelfths::fromPercentage(33.3))->toBe(4)
        ->and(Twelfths::fromPercentage(99.7))->toBe(12)
        ->and(Twelfths::fromPercentage(0.5))->toBe(1);
});
