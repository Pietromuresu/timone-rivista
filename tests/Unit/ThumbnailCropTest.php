<?php

use App\Enums\AdFormat;
use App\Enums\FileFit;
use App\Support\PageGrid\CropPlan;
use App\Support\PageGrid\GridRect;
use App\Support\PageGrid\GridShapes;
use App\Support\PageGrid\ThumbnailCrop;

// Ritaglio delle miniature (2026-10-30, Fase B1): una sola fonte per card,
// export del timone e PDF sfogliabile. Finestre in % dell'immagine.

const A4 = [210.0, 297.0];

function expectWindowIs(?array $window, array $expected): void
{
    expect($window)->not->toBeNull();

    foreach ($expected as $i => $value) {
        expect($window[$i])->toEqualWithDelta($value, 0.01);
    }
}

/** Ogni formato del listino in ogni posizione sensata: la finestra è il riquadro stesso. */
test('every format of the price list, in every corner, shows exactly its portion of a full-page file', function (AdFormat $format, int $x, int $y) {
    $shape = GridShapes::shapeFor($format->twelfths() % 12 ?: 12, $format->orientation());
    $cells = new GridRect($x, $y, $shape['w'], $shape['h']);
    $plan = ThumbnailCrop::plan(A4, [$cells], 210 / 297, ['media' => A4, 'trim' => null], null);
    $rect = $cells->toPercent();

    expect($plan->fit)->toBe(FileFit::FullPage);
    expectWindowIs($plan->windowFor($cells), [$rect['x'], $rect['y'], $rect['w'], $rect['h']]);
})->with([
    'mezza verticale a sinistra' => [AdFormat::MezzaPaginaVerticale, 0, 0],
    'mezza verticale a destra' => [AdFormat::MezzaPaginaVerticale, 3, 0],
    'mezza orizzontale sopra' => [AdFormat::MezzaPaginaOrizzontale, 0, 0],
    'mezza orizzontale sotto' => [AdFormat::MezzaPaginaOrizzontale, 0, 3],
    '1/3 verticale a sinistra' => [AdFormat::UnTerzoPaginaVerticale, 0, 0],
    '1/3 verticale a destra' => [AdFormat::UnTerzoPaginaVerticale, 4, 0],
    '1/3 orizzontale in mezzo' => [AdFormat::UnTerzoPaginaOrizzontale, 0, 2],
    'piedino' => [AdFormat::Piedino, 0, 4],
    'vetrina in alto a sinistra' => [AdFormat::UnQuartoPagina, 0, 0],
    'vetrina in alto a destra' => [AdFormat::UnQuartoPagina, 3, 0],
    'vetrina in basso a sinistra' => [AdFormat::UnQuartoPagina, 0, 3],
    'vetrina in basso a destra' => [AdFormat::UnQuartoPagina, 3, 3],
    '2/3 a sinistra' => [AdFormat::DueTerziPagina, 0, 0],
    '2/3 a destra' => [AdFormat::DueTerziPagina, 2, 0],
    'pagina intera' => [AdFormat::PaginaIntera, 0, 0],
    'doppia pagina (ogni pagina)' => [AdFormat::DoppiaPagina, 0, 0],
    'battente copertina (ogni pagina)' => [AdFormat::BattenteCopertina, 0, 0],
    'copertina' => [AdFormat::CopertinaSecondaTerzaQuarta, 0, 0],
]);

test('an L shape (3/4) is cropped piece by piece, each piece its own portion', function () {
    // 3/4 = la pagina meno la vetrina in basso a destra: due pezzi.
    $top = new GridRect(0, 0, 6, 3);
    $bottomLeft = new GridRect(0, 3, 3, 3);
    $plan = ThumbnailCrop::plan(A4, [$top, $bottomLeft], 210 / 297, null, null);

    expect($plan->fit)->toBe(FileFit::FullPage);
    expectWindowIs($plan->windowFor($top), [0, 0, 100, 50]);
    expectWindowIs($plan->windowFor($bottomLeft), [0, 50, 50, 50]);
    expect($plan->windowFor(new GridRect(3, 3, 3, 3)))->toBeNull();
});

test('7/12 and 11/12 L shapes cover every piece', function (array $pieces) {
    $plan = ThumbnailCrop::plan(A4, $pieces, 210 / 297, null, null);

    foreach ($pieces as $cells) {
        $rect = $cells->toPercent();
        expectWindowIs($plan->windowFor($cells), [$rect['x'], $rect['y'], $rect['w'], $rect['h']]);
    }
})->with([
    '7/12' => [[new GridRect(0, 0, 6, 3), new GridRect(0, 3, 1, 3)]],
    '11/12' => [[new GridRect(0, 0, 6, 5), new GridRect(0, 5, 3, 1)]],
]);

test('automatic: whole only when the file has the size of the box; an A4 on a 210×270 page is cropped, centred', function () {
    $half = new GridRect(0, 0, 3, 6);

    // Il file è la mezza pagina (103×270 contro 105×270): intero.
    expect(ThumbnailCrop::plan([210.0, 270.0], [$half], 103 / 270, ['media' => [103.0, 270.0], 'trim' => null], null)->fit)->toBe(FileFit::Format)
        // Solo proporzioni (immagine): quelle del riquadro e non della pagina → intero.
        ->and(ThumbnailCrop::plan([210.0, 270.0], [$half], 103 / 270, null, null)->fit)->toBe(FileFit::Format);

    // A4 su 210×270 (era il bug: «nel formato», rimpicciolito): ritagliato,
    // la pagina del numero cade al centro dell'A4 (27 mm in più in altezza).
    $plan = ThumbnailCrop::plan([210.0, 270.0], [$half], 210 / 297, ['media' => A4, 'trim' => null], null);
    $band = (1 - (210 / 297) / (210 / 270)) / 2 * 100;
    expect($plan->fit)->toBe(FileFit::FullPage);
    expectWindowIs($plan->windowFor($half), [0, $band, 50, 100 - 2 * $band]);
});

test('an image without sizes in mm and with the proportions of the box is shown whole, even for a vetrina', function () {
    // La vetrina ha le proporzioni della pagina: senza mm, nel dubbio intera (mai tagliare un annuncio a misura).
    expect(ThumbnailCrop::plan(A4, [new GridRect(3, 3, 3, 3)], 210 / 297, null, null)->fit)->toBe(FileFit::Format)
        // Lo stesso PDF misurato a pagina intera (210×297) si ritaglia.
        ->and(ThumbnailCrop::plan(A4, [new GridRect(3, 3, 3, 3)], 210 / 297, ['media' => A4, 'trim' => null], null)->fit)->toBe(FileFit::FullPage);
});

test('a file that is neither the box nor a page is shown whole (a vertical half placed horizontally)', function () {
    $bottom = new GridRect(0, 3, 6, 3);

    expect(ThumbnailCrop::plan(A4, [$bottom], 105 / 297, ['media' => [105.0, 297.0], 'trim' => null], null)->fit)->toBe(FileFit::Format)
        ->and(ThumbnailCrop::plan(A4, [$bottom], 0.375, null, null)->fit)->toBe(FileFit::Format);
});

test('a full-page box crops only a file with the proportions of the page, otherwise shows it whole', function () {
    $page = new GridRect(0, 0, 6, 6);

    expect(ThumbnailCrop::plan(A4, [$page], 216 / 303, ['media' => [216.0, 303.0], 'trim' => [3.0, 3.0, 210.0, 297.0]], null)->fit)->toBe(FileFit::FullPage)
        // Un'immagine larga (doppia pagina in un solo file) resta intera.
        ->and(ThumbnailCrop::plan(A4, [$page], 420 / 297, null, null)->fit)->toBe(FileFit::Format);
});

test('a file never measured is treated as full page at the proportions of the page', function () {
    $plan = ThumbnailCrop::plan(A4, [new GridRect(0, 0, 3, 6)], null, null, null);

    expect($plan->fit)->toBe(FileFit::FullPage);
    expectWindowIs($plan->windowFor(new GridRect(0, 0, 3, 6)), [0, 0, 50, 100]);
});

test('bleed: TrimBox, or format plus 3 mm per side, or nothing', function () {
    $half = new GridRect(0, 0, 3, 6);
    $mediaRatio = 216 / 303;
    $trimmed = [3 / 216 * 100, 3 / 303 * 100, 105 / 216 * 100, 297 / 303 * 100];

    // TrimBox vera.
    expectWindowIs(ThumbnailCrop::plan(A4, [$half], $mediaRatio, ['media' => [216.0, 303.0], 'trim' => [3.0, 3.0, 210.0, 297.0]], null)->windowFor($half), $trimmed);
    // Nessuna TrimBox ma 216×303 = A4 + 3 mm per lato: si tolgono i 3 mm.
    expectWindowIs(ThumbnailCrop::plan(A4, [$half], $mediaRatio, ['media' => [216.0, 303.0], 'trim' => null], null)->windowFor($half), $trimmed);
    // Né TrimBox né misura riconosciuta: la pagina intera del file.
    $plan = ThumbnailCrop::plan(A4, [$half], 230 / 320, ['media' => [230.0, 320.0], 'trim' => null], null);
    expect($plan->trim)->toBe([0.0, 0.0, 100.0, 100.0]);
});

test('the manual choice wins over the detection', function () {
    $half = new GridRect(0, 0, 3, 6);

    expect(ThumbnailCrop::plan(A4, [$half], 103 / 270, ['media' => [103.0, 270.0], 'trim' => null], FileFit::FullPage)->fit)->toBe(FileFit::FullPage)
        ->and(ThumbnailCrop::plan(A4, [$half], 210 / 297, ['media' => A4, 'trim' => null], FileFit::Format)->fit)->toBe(FileFit::Format);
});

test('the CSS of the card and the drawing of the flipbook show the same window', function () {
    $cells = new GridRect(3, 2, 3, 2);
    $plan = ThumbnailCrop::plan([210.0, 270.0], [$cells], 216 / 303, ['media' => [216.0, 303.0], 'trim' => [3.0, 3.0, 210.0, 297.0]], null);
    $window = $plan->windowFor($cells);

    // Card: l'<img> grande W% e spostata di L%: la finestra si rilegge dallo stile.
    preg_match('/left: (-?[\d.]+)%; top: (-?[\d.]+)%; width: ([\d.]+)%; height: ([\d.]+)%/', $plan->imageStyle($cells), $css);
    $fromCss = [-$css[1] / $css[3] * 100, -$css[2] / $css[4] * 100, 100 / $css[3] * 100, 100 / $css[4] * 100];

    // PDF sfogliabile: il pezzo (in punti) e il disegno della sorgente.
    $piece = ['x' => 50.0, 'y' => 40.0, 'w' => 60.0, 'h' => 35.0];
    $fromPdf = fn (array $source, array $trim) => [
        $trim[0] + ($piece['x'] - $source['x']) / $source['w'] * $trim[2],
        $trim[1] + ($piece['y'] - $source['y']) / $source['h'] * $trim[3],
        $piece['w'] / $source['w'] * $trim[2],
        $piece['h'] / $source['h'] * $trim[3],
    ];

    expectWindowIs($fromCss, $window);
    // Sorgente = immagine intera (abbondanza compresa) o già rifilata (template FPDI con TrimBox).
    expectWindowIs($fromPdf($plan->sourceRect($piece, $window, false), [0, 0, 100, 100]), $window);
    expectWindowIs($fromPdf($plan->sourceRect($piece, $window, true), $plan->trim), $window);
});

test('a plan in format has no windows and the card shows the file whole', function () {
    $plan = new CropPlan(FileFit::Format, [0, 0, 100, 100], [0, 0, 100, 100], []);

    expect($plan->imageStyle(new GridRect(0, 0, 3, 6)))->toContain('object-fit: contain');
});
