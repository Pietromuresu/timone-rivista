<?php

use App\Enums\AdFormat;

// Copertura dei nuovi formati aggiunti in Fase 2 dal listino ADV allegato
// dall'utente — vedi il docblock di AdFormat::dimensionsMm() per le
// assunzioni sui casi ambigui (Doppia pagina, 1/3 orizzontale).

test('every format has a label and a default percentage', function () {
    foreach (AdFormat::cases() as $format) {
        expect($format->label())->not->toBeEmpty()
            ->and($format->defaultPercentage())->toBeGreaterThan(0.0);
    }
});

test('dimensions match the official rate card for the formats it defines', function () {
    expect(AdFormat::PaginaIntera->dimensionsMm())->toBe(['width' => 210.0, 'height' => 270.0])
        ->and(AdFormat::MezzaPaginaOrizzontale->dimensionsMm())->toBe(['width' => 210.0, 'height' => 137.0])
        ->and(AdFormat::MezzaPaginaVerticale->dimensionsMm())->toBe(['width' => 103.0, 'height' => 270.0])
        ->and(AdFormat::UnQuartoPagina->dimensionsMm())->toBe(['width' => 103.0, 'height' => 137.0])
        ->and(AdFormat::UnTerzoPaginaVerticale->dimensionsMm())->toBe(['width' => 58.0, 'height' => 270.0])
        ->and(AdFormat::BattenteCopertina->dimensionsMm())->toBe(['width' => 420.0, 'height' => 270.0])
        ->and(AdFormat::DoppiaPagina->dimensionsMm())->toBe(['width' => 210.0, 'height' => 270.0])
        ->and(AdFormat::CopertinaSecondaTerzaQuarta->dimensionsMm())->toBe(['width' => 152.0, 'height' => 194.0])
        ->and(AdFormat::Piedino->dimensionsMm())->toBe(['width' => 210.0, 'height' => 88.0])
        ->and(AdFormat::DueTerziPagina->dimensionsMm())->toBe(['width' => 148.0, 'height' => 270.0])
        ->and(AdFormat::PrimaRomana->dimensionsMm())->toBe(['width' => 210.0, 'height' => 270.0])
        ->and(AdFormat::Controsommario->dimensionsMm())->toBe(['width' => 210.0, 'height' => 270.0])
        ->and(AdFormat::ElencoInserzionisti->dimensionsMm())->toBe(['width' => 210.0, 'height' => 270.0])
        ->and(AdFormat::Controeditoriale->dimensionsMm())->toBe(['width' => 210.0, 'height' => 270.0])
        ->and(AdFormat::Pubbliredazionale->dimensionsMm())->toBe(['width' => 210.0, 'height' => 270.0]);
});

test('un terzo pagina orizzontale has no dimensions because the rate card does not define one', function () {
    expect(AdFormat::UnTerzoPaginaOrizzontale->dimensionsMm())->toBeNull();
});

test('battente copertina and doppia pagina occupy 100% of each page they are assigned to, not 200% of one', function () {
    expect(AdFormat::BattenteCopertina->defaultPercentage())->toBe(100.0)
        ->and(AdFormat::DoppiaPagina->defaultPercentage())->toBe(100.0);
});
