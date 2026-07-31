<?php

use App\Support\PdfFormatChecker;

// Puro/senza I/O: nessun ext-imagick richiesto, a differenza di
// PdfPageMeasurerTest — vedi App\Support\PdfFormatChecker per il perché.

test('a pdf exactly at nominal size plus bleed matches', function () {
    $nominal = ['width' => 210.0, 'height' => 270.0];
    $measured = ['width' => 216.0, 'height' => 276.0]; // 210+6, 270+6

    expect(PdfFormatChecker::matches($nominal, $measured))->toBeTrue();
});

test('a pdf within the additional tolerance still matches', function () {
    $nominal = ['width' => 210.0, 'height' => 270.0];
    $measured = ['width' => 217.4, 'height' => 274.6]; // entro 1.5mm di default

    expect(PdfFormatChecker::matches($nominal, $measured))->toBeTrue();
});

test('a pdf without bleed at all does not match', function () {
    $nominal = ['width' => 210.0, 'height' => 270.0];
    $measured = ['width' => 210.0, 'height' => 270.0]; // manca l'abbondanza richiesta

    expect(PdfFormatChecker::matches($nominal, $measured))->toBeFalse();
});

test('a pdf far outside tolerance does not match', function () {
    $nominal = ['width' => 210.0, 'height' => 270.0];
    $measured = ['width' => 220.0, 'height' => 280.0];

    expect(PdfFormatChecker::matches($nominal, $measured))->toBeFalse();
});

test('a rotated pdf (width/height swapped) still matches', function () {
    $nominal = ['width' => 210.0, 'height' => 137.0];
    $measured = ['width' => 143.0, 'height' => 216.0]; // 137+6, 210+6 scambiati

    expect(PdfFormatChecker::matches($nominal, $measured))->toBeTrue();
});

test('a custom tolerance is respected', function () {
    $nominal = ['width' => 210.0, 'height' => 270.0];
    $measured = ['width' => 219.0, 'height' => 279.0]; // +3mm oltre l'abbondanza attesa

    expect(PdfFormatChecker::matches($nominal, $measured, toleranceMm: 1.5))->toBeFalse()
        ->and(PdfFormatChecker::matches($nominal, $measured, toleranceMm: 3.0))->toBeTrue();
});
