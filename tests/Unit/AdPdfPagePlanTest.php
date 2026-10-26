<?php

use App\Enums\AdFormat;
use App\Support\AdPdfPagePlan;
use Illuminate\Validation\ValidationException;

// Fase 1, bug 8 (2026-10-26): PDF pubblicitario più lungo del formato.
// TestCase per ValidationException::withMessages(), che usa il validator del container.
uses(Tests\TestCase::class);

test('a choice is needed only when the pdf has more pages than the format occupies', function () {
    expect(AdPdfPagePlan::needsChoice(AdFormat::CopertinaSecondaTerzaQuarta, 3))->toBeTrue()
        ->and(AdPdfPagePlan::needsChoice(AdFormat::PaginaIntera, 1))->toBeFalse()
        ->and(AdPdfPagePlan::needsChoice(AdFormat::DoppiaPagina, 2))->toBeFalse()
        ->and(AdPdfPagePlan::needsChoice(AdFormat::DoppiaPagina, 1))->toBeFalse()
        ->and(AdPdfPagePlan::needsChoice(AdFormat::MezzaPaginaVerticale, null))->toBeFalse();
});

test('"use all pages" makes the ad occupy every pdf page as whole pages', function () {
    $plan = AdPdfPagePlan::fromChoice(3, AdPdfPagePlan::ALL, null, null);

    expect($plan->pdfPages())->toBe([1, 2, 3])
        ->and($plan->occupancy(AdFormat::CopertinaSecondaTerzaQuarta)->value)->toBe('3')
        ->and($plan->expandsFormat(AdFormat::CopertinaSecondaTerzaQuarta))->toBeTrue();
});

test('a range keeps the original pdf page numbers', function () {
    $plan = AdPdfPagePlan::fromChoice(5, AdPdfPagePlan::RANGE, 2, 4);

    expect($plan->pdfPages())->toBe([2, 3, 4])
        ->and($plan->pageCount())->toBe(3)
        ->and($plan->occupancy(AdFormat::PaginaIntera)->value)->toBe('3');
});

test('a range as long as the format keeps the format occupancy, fractions included', function () {
    $plan = AdPdfPagePlan::fromChoice(3, AdPdfPagePlan::RANGE, 2, 2);

    expect($plan->occupancy(AdFormat::MezzaPaginaOrizzontale)->value)->toBe('0.50')
        ->and($plan->expandsFormat(AdFormat::MezzaPaginaOrizzontale))->toBeFalse();
});

test('an invalid range is rejected with errors on the form fields', function (?int $from, ?int $to, string $field) {
    try {
        AdPdfPagePlan::fromChoice(3, AdPdfPagePlan::RANGE, $from, $to);
        $this->fail('Nessuna eccezione');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'da vuoto' => [null, 2, 'pdfPageFrom'],
    'oltre la fine' => [1, 4, 'pdfPageTo'],
    'al contrario' => [3, 2, 'pdfPageTo'],
]);

test('a missing choice is rejected', function () {
    AdPdfPagePlan::fromChoice(3, '', null, null);
})->throws(ValidationException::class);
