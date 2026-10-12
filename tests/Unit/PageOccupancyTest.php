<?php

use App\Enums\AdFormat;
use App\Support\PageOccupancy;

test('options list the four fractions then whole pages up to the issue size, nothing preselected', function () {
    // array_map strval: PHP converte le chiavi stringa numeriche intere
    // ('1', '2') in int — irrilevante per la select (HTML) e per Rule::in.
    expect(array_map('strval', array_keys(PageOccupancy::options(3))))->toBe(['0.25', '0.33', '0.50', '0.67', '1', '2', '3']);
});

test('values read back from the decimal column normalize to the same option keys', function () {
    expect(PageOccupancy::from('0.50')->value)->toBe('0.50')
        ->and(PageOccupancy::from('3.00')->value)->toBe('3')
        ->and(PageOccupancy::tryFrom(null))->toBeNull()
        ->and(PageOccupancy::tryFrom(''))->toBeNull()
        ->and(PageOccupancy::tryFrom('0.40'))->toBeNull()
        ->and(PageOccupancy::tryFrom('1.5'))->toBeNull();
});

test('fractions span one page with their own percentage, whole pages span N pages at 100%', function () {
    expect(PageOccupancy::from('0.33')->pagesSpanned())->toBe(1)
        ->and(PageOccupancy::from('0.33')->percentagePerPage())->toBe(33.3)
        ->and(PageOccupancy::from('3')->pagesSpanned())->toBe(3)
        ->and(PageOccupancy::from('3')->percentagePerPage())->toBe(100.0);
});

test('ad formats map to the closest allowed occupancy, double page and battente span two pages', function () {
    expect(PageOccupancy::forAdFormat(AdFormat::UnQuartoPagina)->value)->toBe('0.25')
        ->and(PageOccupancy::forAdFormat(AdFormat::MezzaPaginaVerticale)->value)->toBe('0.50')
        ->and(PageOccupancy::forAdFormat(AdFormat::DueTerziPagina)->value)->toBe('0.67')
        ->and(PageOccupancy::forAdFormat(AdFormat::PaginaIntera)->value)->toBe('1')
        ->and(PageOccupancy::forAdFormat(AdFormat::DoppiaPagina)->pagesSpanned())->toBe(2)
        ->and(PageOccupancy::forAdFormat(AdFormat::BattenteCopertina)->pagesSpanned())->toBe(2);
});
