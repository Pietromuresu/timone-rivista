<?php

use App\Enums\AdFormat;
use App\Support\PageOccupancy;

// Fase 3 (2026-10-27): occupazione totale in dodicesimi di pagina.

test('pages and fraction combine into twelfths', function () {
    expect(PageOccupancy::fromParts(2, 6)->twelfths)->toBe(30)
        ->and(PageOccupancy::fromParts(null, 4)->twelfths)->toBe(4)
        ->and(PageOccupancy::fromParts(3, 0)->twelfths)->toBe(36)
        ->and(PageOccupancy::fromParts(0, 0))->toBeNull()
        ->and(PageOccupancy::fromParts(null, 0))->toBeNull()
        ->and(PageOccupancy::fromParts(1, 5))->toBeNull()
        ->and(PageOccupancy::fromParts(-1, 6))->toBeNull();
});

// Test richiesto dalla specifica: 2 intere + 1/2 = 30 dodicesimi, distribuiti 12/12/6.
test('2 whole pages and a half are 30 twelfths distributed as 12, 12, 6', function () {
    $occupancy = PageOccupancy::fromParts(2, 6);

    expect($occupancy->twelfths)->toBe(30)
        ->and($occupancy->pagesSpanned())->toBe(3)
        ->and($occupancy->distribution())->toBe([12, 12, 6])
        ->and($occupancy->wholePageCount())->toBe(2)
        ->and($occupancy->fractionTwelfths())->toBe(6)
        ->and($occupancy->label())->toBe('2 e 1/2 pagine');
});

test('fractions and whole pages span and distribute as expected', function () {
    expect(PageOccupancy::fromTwelfths(4)->pagesSpanned())->toBe(1)
        ->and(PageOccupancy::fromTwelfths(4)->distribution())->toBe([4])
        ->and(PageOccupancy::fromTwelfths(4)->label())->toBe('1/3 di pagina')
        ->and(PageOccupancy::fromTwelfths(36)->distribution())->toBe([12, 12, 12])
        ->and(PageOccupancy::fromTwelfths(12)->label())->toBe('1 pagina')
        ->and(PageOccupancy::fromTwelfths(30)->perExtraPage())->toBe(12)
        ->and(PageOccupancy::fromTwelfths(6)->perExtraPage())->toBe(6);
});

test('a missing or invalid value is null, never a default', function () {
    expect(PageOccupancy::tryFromTwelfths(null))->toBeNull()
        ->and(PageOccupancy::tryFromTwelfths(''))->toBeNull()
        ->and(PageOccupancy::tryFromTwelfths(0))->toBeNull()
        ->and(PageOccupancy::tryFromTwelfths('9')->twelfths)->toBe(9);
});

test('ad formats propose their occupancy', function () {
    expect(PageOccupancy::forAdFormat(AdFormat::UnQuartoPagina)->twelfths)->toBe(3)
        ->and(PageOccupancy::forAdFormat(AdFormat::MezzaPaginaVerticale)->twelfths)->toBe(6)
        ->and(PageOccupancy::forAdFormat(AdFormat::DueTerziPagina)->twelfths)->toBe(8)
        ->and(PageOccupancy::forAdFormat(AdFormat::PaginaIntera)->twelfths)->toBe(12)
        ->and(PageOccupancy::forAdFormat(AdFormat::DoppiaPagina)->pagesSpanned())->toBe(2)
        ->and(PageOccupancy::forAdFormat(AdFormat::BattenteCopertina)->pagesSpanned())->toBe(2);
});
