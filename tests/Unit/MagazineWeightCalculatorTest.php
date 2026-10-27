<?php

use App\Support\Weight\MagazineWeightCalculator;
use App\Support\Weight\MagazineWeightInput;
use App\Support\Weight\MagazineWeightResult;
use App\Support\Weight\WeightLimits;

/**
 * Scaglioni usati in tutto il file: stessi valori di config/peso_rivista.php,
 * ripetuti qui perché il calculator è puro e questi sono unit test senza
 * framework avviato.
 */
const WEIGHT_TEST_BRACKETS = [250, 350, 500, 1000];

/**
 * Input con i parametri dell'Excel; le tolleranze di default (4% / 1 mm /
 * 1 punto) si possono sovrascrivere per i casi sul margine.
 */
function weightInput(array $overrides = []): MagazineWeightInput
{
    $params = array_merge([
        'formato_base_mm' => 210,
        'formato_altezza_mm' => 270,
        'grammatura_interno' => '80.00',
        'grammatura_copertina' => '170.00',
        'pagine_copertina' => 6,
        'maggiorazione_percentuale' => '2.00',
        'peso_bancale_kg' => '20.00',
        'tolleranza_grammatura_perc' => '4.00',
        'tolleranza_formato_mm' => '1.00',
        'tolleranza_maggiorazione_perc' => '1.00',
        'n_copie' => 8200,
        'n_bancali' => 5,
        'peso_reale_g' => null,
    ], $overrides);

    return MagazineWeightInput::fromArray($params, $overrides['pagine_interno'] ?? 208);
}

function calculateWeight(array $overrides = []): MagazineWeightResult
{
    return MagazineWeightCalculator::calculate(weightInput($overrides), WEIGHT_TEST_BRACKETS);
}

dataset('excel cases', [
    'NM' => [[
        'formato_altezza_mm' => 270, 'pagine_interno' => 208, 'pagine_copertina' => 6, 'n_copie' => 8200, 'n_bancali' => 5,
    ], 471.744, 28.917, 510.67422, 4187.528604, 4287.528604],
    'GL' => [[
        'formato_altezza_mm' => 297, 'pagine_interno' => 80, 'pagine_copertina' => 4, 'n_copie' => 6200, 'n_bancali' => 5,
    ], 199.584, 21.2058, 225.205596, 1396.2746952, 1496.2746952],
    'F&C' => [[
        'formato_altezza_mm' => 297, 'pagine_interno' => 112, 'pagine_copertina' => 4, 'n_copie' => 9500, 'n_bancali' => 5,
    ], 279.4176, 21.2058, 306.635868, 2913.040746, 3013.040746],
]);

test('reproduces the Excel sheet exactly', function (array $overrides, float $interno, float $copertina, float $copia, float $netto, float $lordo) {
    $result = calculateWeight($overrides);

    expect($result->complete)->toBeTrue()
        ->and($result->pesoInternoG)->toEqualWithDelta($interno, 0.0001)
        ->and($result->pesoCopertinaG)->toEqualWithDelta($copertina, 0.0001)
        ->and($result->pesoCopiaG)->toEqualWithDelta($copia, 0.0001)
        ->and($result->pesoNettoKg)->toEqualWithDelta($netto, 0.0001)
        ->and($result->pesoLordoKg)->toEqualWithDelta($lordo, 0.0001);
})->with('excel cases');

test('NM has the expected margin and stays in the 1000 g bracket even in the worst case', function () {
    $result = calculateWeight();

    expect($result->margineG)->toBe(31)
        ->and($result->pesoMinG)->toEqualWithDelta(481.3401, 0.001)
        ->and($result->pesoMaxG)->toEqualWithDelta(540.8573, 0.001)
        ->and($result->scaglione)->toBe(1000)
        ->and($result->scaglionePeggiore)->toBe(1000)
        ->and($result->aRischio)->toBeFalse()
        ->and($result->scaglionePrecedente)->toBe(500)
        ->and($result->grammiOltreScaglionePrecedente)->toEqualWithDelta(10.67422, 0.0001)
        ->and($result->grammiAlLimite)->toEqualWithDelta(1000 - 510.67422, 0.0001);
});

test('brackets are inclusive of the limit', function (float $grams, ?int $expected) {
    expect(MagazineWeightCalculator::bracketFor($grams, WEIGHT_TEST_BRACKETS))->toBe($expected);
})->with([
    [249.9, 250],
    [250.0, 250],
    [250.01, 350],
    [500.0, 500],
    [509.0, 1000],
    [1000.0, 1000],
    [1000.1, null],
]);

test('brackets are read from the given array, not hard-coded, and sorted defensively', function () {
    expect(MagazineWeightCalculator::bracketFor(300, [1000, 300, 100]))->toBe(300)
        ->and(MagazineWeightCalculator::bracketFor(301, [100, 300]))->toBeNull();
});

test('a copy over the last bracket is out of bracket', function () {
    $result = calculateWeight(['pagine_interno' => 440]);

    expect($result->pesoCopiaG)->toBeGreaterThan(1000)
        ->and($result->scaglione)->toBeNull()
        ->and($result->fuoriScaglione)->toBeTrue()
        ->and($result->grammiAlLimite)->toBeNull()
        ->and($result->scaglionePrecedente)->toBe(1000);
});

test('with all tolerances at zero min = max = nominal and the margin is zero', function () {
    $result = calculateWeight([
        'tolleranza_grammatura_perc' => '0',
        'tolleranza_formato_mm' => '0',
        'tolleranza_maggiorazione_perc' => '0',
    ]);

    expect($result->pesoMinG)->toBe($result->pesoCopiaG)
        ->and($result->pesoMaxG)->toBe($result->pesoCopiaG)
        ->and($result->margineG)->toBe(0)
        ->and($result->aRischio)->toBeFalse();
});

test('max is above nominal and min below it for every Excel case', function (array $overrides) {
    $result = calculateWeight($overrides);

    expect($result->pesoMaxG)->toBeGreaterThan($result->pesoCopiaG)
        ->and($result->pesoMinG)->toBeLessThan($result->pesoCopiaG)
        ->and($result->margineG)->toBeGreaterThanOrEqual((int) ceil($result->erroreSopraG))
        ->and($result->margineG)->toBeGreaterThanOrEqual((int) ceil($result->erroreSottoG));
})->with('excel cases');

test('the margin is the worst case of the full formula, not a linear estimate', function () {
    $result = calculateWeight();

    // Ricalcolo indipendente del caso massimo: 211 × 271 mm, +4% carta, 3%.
    $area = 21.1 * 27.1;
    $expectedMax = ($area * 80 * 1.04 / 10000 * 104 + $area * 170 * 1.04 / 10000 * 3) * 1.03;

    expect($result->pesoMaxG)->toEqualWithDelta($expectedMax, 0.0001)
        ->and($result->margineG)->toBe((int) ceil($expectedMax - $result->pesoCopiaG));
});

test('a copy near 500 g is at risk of the next bracket', function () {
    // 202 pagine interne + 6 di copertina su 21×27 a 80/170 g → ~496 g nominali.
    $result = calculateWeight(['pagine_interno' => 202]);

    expect($result->pesoCopiaG)->toBeGreaterThan(490)->toBeLessThan(500)
        ->and($result->scaglione)->toBe(500)
        ->and($result->scaglionePeggiore)->toBe(1000)
        ->and($result->aRischio)->toBeTrue()
        ->and($result->grammiAlLimite)->toBeGreaterThan(0)->toBeLessThan($result->margineG);
});

test('odd page counts are a blocking validation error, not an exception', function (array $overrides, string $fragment) {
    $result = calculateWeight($overrides);

    expect($result->complete)->toBeFalse()
        ->and($result->pesoCopiaG)->toBeNull()
        ->and(implode(' ', $result->errors))->toContain($fragment);
})->with([
    'odd internal pages' => [['pagine_interno' => 207], 'Le pagine interne (207)'],
    'odd cover pages' => [['pagine_copertina' => 5], 'Le pagine di copertina (5)'],
]);

test('internal pages not multiple of 4 are only a warning', function () {
    $result = calculateWeight(['pagine_interno' => 206]);

    expect($result->complete)->toBeTrue()
        ->and(implode(' ', $result->warnings))->toContain('multiplo di 4');
});

test('other invalid parameters are blocking errors', function (array $overrides) {
    $result = calculateWeight($overrides);

    expect($result->complete)->toBeFalse()->and($result->errors)->not->toBeEmpty();
})->with([
    'zero base' => [['formato_base_mm' => 0]],
    'zero grammage' => [['grammatura_interno' => '0']],
    'negative tolerance' => [['tolleranza_formato_mm' => '-1']],
    'tolerance above surcharge' => [['maggiorazione_percentuale' => '0.5', 'tolleranza_maggiorazione_perc' => '1']],
    'negative internal pages' => [['pagine_interno' => -2]],
]);

test('missing parameters give an incomplete result listing them, without exceptions', function () {
    $result = calculateWeight(['formato_base_mm' => null, 'grammatura_copertina' => '']);

    expect($result->complete)->toBeFalse()
        ->and($result->missing)->toBe(['formato_base_mm', 'grammatura_copertina'])
        ->and($result->pesoCopiaG)->toBeNull()
        ->and($result->margineG)->toBeNull();
});

test('missing print run only leaves the print-run weights empty', function () {
    $result = calculateWeight(['n_copie' => null, 'n_bancali' => null]);

    expect($result->complete)->toBeTrue()
        ->and($result->pesoCopiaG)->toEqualWithDelta(510.67422, 0.0001)
        ->and($result->pesoNettoKg)->toBeNull()
        ->and($result->pesoLordoKg)->toBeNull();
});

test('the suggested surcharge reproduces the measured weight', function () {
    $result = calculateWeight(['peso_reale_g' => '523.40']);

    expect($result->scostamentoRealeG)->toEqualWithDelta(523.40 - 510.67422, 0.0001)
        ->and($result->scostamentoRealePerc)->toEqualWithDelta((523.40 - 510.67422) / 510.67422 * 100, 0.0001);

    $recalculated = MagazineWeightCalculator::calculate(
        weightInput(['peso_reale_g' => '523.40'])->withMaggiorazione($result->maggiorazioneSuggerita),
        WEIGHT_TEST_BRACKETS,
    );

    expect($recalculated->pesoCopiaG)->toEqualWithDelta(523.40, 0.0001);
});

test('simulation changes only the internal pages and writes nothing', function () {
    $input = weightInput();
    $plus = MagazineWeightCalculator::simulate($input, 4, WEIGHT_TEST_BRACKETS);
    $minus = MagazineWeightCalculator::simulate($input, -4, WEIGHT_TEST_BRACKETS);

    // 4 pagine = 2 fogli da 21×27 a 80 g/m² = 9,072 g, × 1,02.
    expect($plus->pesoCopiaG - 510.67422)->toEqualWithDelta(9.072 * 1.02, 0.0001)
        ->and(510.67422 - $minus->pesoCopiaG)->toEqualWithDelta(9.072 * 1.02, 0.0001)
        ->and($input->pagineInterno)->toBe(208);
});

test('addable pages stay within the current bracket on nominal and worst-case weight', function () {
    // ~496 g nominali nello scaglione 500: ogni foglio (2 pagine) vale 4,62672 g.
    $input = weightInput(['pagine_interno' => 202]);
    $addable = MagazineWeightCalculator::addablePages($input, WEIGHT_TEST_BRACKETS);

    $atLimit = MagazineWeightCalculator::simulate($input, $addable['nominale'], WEIGHT_TEST_BRACKETS);
    $overLimit = MagazineWeightCalculator::simulate($input, $addable['nominale'] + 2, WEIGHT_TEST_BRACKETS);

    expect($addable['nominale'] % 2)->toBe(0)
        ->and($atLimit->pesoCopiaG)->toBeLessThanOrEqual(500)
        ->and($overLimit->pesoCopiaG)->toBeGreaterThan(500)
        ->and($addable['sicure'])->toBe(0);
});

test('addable pages are null when the copy is already out of bracket', function () {
    expect(MagazineWeightCalculator::addablePages(weightInput(['pagine_interno' => 440]), WEIGHT_TEST_BRACKETS))->toBeNull();
});

// Fase 2 (2026-10-26): rimosso il test "ruoli copertina nel timone diversi
// dalle pagine copertina": il parametro deriva ora dalle copertine esistenti
// (Issue::coverPageCount()) e l'avviso non può più scattare.

// --- Grammatura vs peso a pagina (follow-up 2026-10-26) ---------------------

/** Stessi valori di config/peso_rivista.php (chiavi grammatura e formato), ripetuti per lo stesso motivo degli scaglioni. */
function weightTestLimits(): WeightLimits
{
    return WeightLimits::fromValues(30, 400, 50, 1000);
}

test('pesoPagina is the weight of one page (half a sheet) of the given paper', function (float $base, float $altezza, float $grammatura, float $expected) {
    expect(MagazineWeightCalculator::pesoPagina($base, $altezza, $grammatura))->toEqualWithDelta($expected, 0.0000001);
})->with([
    'NM interno 21×27 a 80' => [210, 270, 80, 2.268],
    'NM copertina 21×27 a 170' => [210, 270, 170, 4.8195],
    '21×29,7 a 80' => [210, 297, 80, 2.4948],
    '21×29,7 a 170' => [210, 297, 170, 5.30145],
]);

test('the copy weight is built from pesoPagina: interno and copertina are pesoPagina × pages', function () {
    $result = calculateWeight();

    expect($result->pesoInternoG)->toEqualWithDelta(MagazineWeightCalculator::pesoPagina(210, 270, 80) * 208, 0.0000001)
        ->and($result->pesoCopertinaG)->toEqualWithDelta(MagazineWeightCalculator::pesoPagina(210, 270, 170) * 6, 0.0000001);
});

test('regression: 208 pages at 80 g/m² + 6 at 170 g/m², 21×27, +2% weighs 510.67422 g, not 4.7 g', function () {
    $result = MagazineWeightCalculator::calculate(weightInput(), WEIGHT_TEST_BRACKETS, weightTestLimits());

    expect($result->complete)->toBeTrue()
        ->and($result->pesoCopiaG)->toEqualWithDelta(510.67422, 0.0001);
});

test('a grammage typed as the weight of a page (0.8) gives an incomplete result with an explicit error', function () {
    $result = MagazineWeightCalculator::calculate(weightInput(['grammatura_interno' => '0.80']), WEIGHT_TEST_BRACKETS, weightTestLimits());

    expect($result->complete)->toBeFalse()
        ->and($result->pesoCopiaG)->toBeNull()
        ->and($result->scaglione)->toBeNull()
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0])->toContain('Grammatura interno 0,8 g/m² fuori dall\'intervallo ammesso (30–400 g/m²)')
        ->and($result->errors[0])->toContain('non il peso di una pagina');
});

test('a grammage above the range gives an incomplete result too', function () {
    $result = MagazineWeightCalculator::calculate(weightInput(['grammatura_copertina' => '450']), WEIGHT_TEST_BRACKETS, weightTestLimits());

    expect($result->complete)->toBeFalse()
        ->and($result->errors[0])->toContain('Grammatura copertina 450 g/m²')
        ->and($result->errors[0])->toContain('controlla l\'unità di misura');
});

test('the range limits are inclusive', function (string $grammatura) {
    $result = MagazineWeightCalculator::calculate(weightInput(['grammatura_interno' => $grammatura]), WEIGHT_TEST_BRACKETS, weightTestLimits());

    expect($result->complete)->toBeTrue();
})->with(['30', '400']);

test('simulation and addable pages honour the grammage range', function () {
    $in = weightInput(['grammatura_interno' => '0.8']);

    expect(MagazineWeightCalculator::simulate($in, 4, WEIGHT_TEST_BRACKETS, weightTestLimits())->complete)->toBeFalse()
        ->and(MagazineWeightCalculator::addablePages($in, WEIGHT_TEST_BRACKETS, weightTestLimits()))->toBeNull();
});

test('a format typed in centimetres gives an incomplete result with an explicit error', function () {
    $result = MagazineWeightCalculator::calculate(
        weightInput(['formato_base_mm' => 21, 'formato_altezza_mm' => 27]),
        WEIGHT_TEST_BRACKETS,
        weightTestLimits(),
    );

    expect($result->complete)->toBeFalse()
        ->and($result->pesoCopiaG)->toBeNull()
        ->and($result->errors)->toBe([
            'Formato base 21 mm fuori dall\'intervallo ammesso (50–1000 mm). '.WeightLimits::FORMATO_TOO_LOW,
            'Formato altezza 27 mm fuori dall\'intervallo ammesso (50–1000 mm). '.WeightLimits::FORMATO_TOO_LOW,
        ]);
});

test('without limits the same centimetre format would give a plausible-looking 5.1 g', function () {
    // Documenta perché i limiti servono: 21 × 27 "mm" = area 100 volte più piccola.
    expect(calculateWeight(['formato_base_mm' => 21, 'formato_altezza_mm' => 27])->pesoCopiaG)->toEqualWithDelta(5.1067422, 0.0000001);
});
