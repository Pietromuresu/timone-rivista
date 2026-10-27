<?php

use App\Enums\AdFormat;

// Copertura dei nuovi formati aggiunti in Fase 2 dal listino ADV allegato
// dall'utente — vedi il docblock di AdFormat::dimensionsMm() per le
// assunzioni sui casi ambigui (Doppia pagina, 1/3 orizzontale).

test('every format has a label and an occupancy in twelfths', function () {
    foreach (AdFormat::cases() as $format) {
        expect($format->label())->not->toBeEmpty()
            ->and($format->twelfths())->toBeGreaterThan(0);
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

// Fase 3 (2026-10-27): mappatura formato → dodicesimi richiesta dalla
// specifica (intera 12, 2/3 8, 1/2 6, piedino 1/3 4, vetrina 1/4 3) e
// scelte di default per i formati senza frazione esatta.
test('ad formats map to twelfths as agreed in phase 3', function () {
    expect(AdFormat::PaginaIntera->twelfths())->toBe(12)
        ->and(AdFormat::DueTerziPagina->twelfths())->toBe(8)
        ->and(AdFormat::MezzaPaginaOrizzontale->twelfths())->toBe(6)
        ->and(AdFormat::MezzaPaginaVerticale->twelfths())->toBe(6)
        ->and(AdFormat::Piedino->twelfths())->toBe(4)
        ->and(AdFormat::UnTerzoPaginaVerticale->twelfths())->toBe(4)
        ->and(AdFormat::UnTerzoPaginaOrizzontale->twelfths())->toBe(4)
        ->and(AdFormat::UnQuartoPagina->twelfths())->toBe(3)
        ->and(AdFormat::CopertinaSecondaTerzaQuarta->twelfths())->toBe(12)
        ->and(AdFormat::BattenteCopertina->twelfths())->toBe(12)
        ->and(AdFormat::DoppiaPagina->twelfths())->toBe(24);
});
