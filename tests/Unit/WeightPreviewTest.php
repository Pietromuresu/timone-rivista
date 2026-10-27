<?php

use App\Support\Weight\MagazineWeightResult;
use App\Support\Weight\WeightLimits;
use App\Support\Weight\WeightParameters;
use App\Support\Weight\WeightPreview;

/**
 * Valori di form NM (stringhe, come arrivano da Livewire).
 */
function previewForm(array $overrides = []): array
{
    return array_merge([
        'formato_base_mm' => '210',
        'formato_altezza_mm' => '270',
        'grammatura_interno' => '80',
        'grammatura_copertina' => '170',
        'pagine_copertina' => '6',
        'maggiorazione_percentuale' => '2',
        'peso_bancale_kg' => '20',
        'tolleranza_grammatura_perc' => '4',
        'tolleranza_formato_mm' => '1',
        'tolleranza_maggiorazione_perc' => '1',
    ], $overrides);
}

// Fase 2 (2026-10-26): $totalPages sono le pagine INTERNE (prima 214 totali
// − 6 di copertina = 208 interne); stessi numeri attesi di prima.
function preview(array $overrides = [], ?int $totalPages = 208, ?MagazineWeightResult $saved = null): WeightPreview
{
    return WeightPreview::fromForm(previewForm($overrides), $totalPages, [250, 350, 500, 1000], WeightLimits::fromValues(30, 400, 50, 1000), $saved);
}

test('the paper lines give the weight per page and per section from the calculator', function () {
    $p = preview();
    $interno = $p->paper['grammatura_interno'];
    $copertina = $p->paper['grammatura_copertina'];

    expect($interno->pesoPaginaG)->toEqualWithDelta(2.268, 0.0000001)
        ->and($interno->pagine)->toBe(208)
        ->and($interno->totaleG)->toEqualWithDelta(471.744, 0.0001)
        ->and($copertina->pesoPaginaG)->toEqualWithDelta(4.8195, 0.0000001)
        ->and($copertina->pagine)->toBe(6)
        ->and($copertina->totaleG)->toEqualWithDelta(28.917, 0.0001)
        ->and($p->result->pesoCopiaG)->toEqualWithDelta(510.67422, 0.0001);
});

test('comma and dot decimals give the same preview', function () {
    expect(preview(['grammatura_interno' => '80,5'])->result->pesoCopiaG)
        ->toBe(preview(['grammatura_interno' => '80.5'])->result->pesoCopiaG);
});

test('an out-of-range grammage shows the unit message instead of a weight', function () {
    $p = preview(['grammatura_interno' => '0,8']);

    expect($p->paper['grammatura_interno']->outOfRange)->toBeTrue()
        ->and($p->paper['grammatura_interno']->pesoPaginaG)->toBeNull()
        ->and($p->paper['grammatura_interno']->message)->toBe(WeightLimits::GRAMMATURA_TOO_LOW)
        ->and($p->result->complete)->toBeFalse();
});

test('without total pages only the weight per page is shown', function () {
    $p = preview(totalPages: null);

    expect($p->paper['grammatura_interno']->pesoPaginaG)->toEqualWithDelta(2.268, 0.0000001)
        ->and($p->paper['grammatura_interno']->pagine)->toBeNull()
        ->and($p->paper['grammatura_interno']->totaleG)->toBeNull()
        ->and($p->paper['grammatura_copertina']->totaleG)->toEqualWithDelta(28.917, 0.0001);
});

test('the delta against the saved value reports a bracket change', function () {
    $saved = preview()->result;
    $p = preview(['grammatura_interno' => '70'], saved: $saved);

    expect($p->deltaG())->toEqualWithDelta(450.52686 - 510.67422, 0.0001)
        ->and($p->bracketChanged())->toBeTrue()
        ->and($saved->scaglione)->toBe(1000)
        ->and($p->result->scaglione)->toBe(500);
});

test('no delta without a saved value or when a side is incomplete', function () {
    expect(preview()->deltaG())->toBeNull()
        ->and(preview(['grammatura_interno' => ''], saved: preview()->result)->deltaG())->toBeNull();
});

test('normalizeInput turns a decimal comma into a dot and leaves the rest alone', function () {
    expect(WeightParameters::normalizeInput(['a' => '80,5', 'b' => ' 80.5 ', 'c' => null, 'd' => 6]))
        ->toBe(['a' => '80.5', 'b' => '80.5', 'c' => null, 'd' => 6]);
});

test('a format in centimetres shows no weight per page, only what to check', function () {
    $line = preview(['formato_base_mm' => '21', 'formato_altezza_mm' => '27'])->paper['grammatura_interno'];

    expect($line->outOfRange)->toBeFalse()
        ->and($line->pesoPaginaG)->toBeNull()
        ->and($line->message)->toContain(WeightLimits::FORMATO_TOO_LOW);
});
