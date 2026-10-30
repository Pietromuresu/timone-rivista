<?php

use App\Enums\AdFormat;
use App\Enums\ThumbnailStatus;
use App\Events\ContentAssigned;
use App\Events\ContentFileUploaded;
use App\Events\ContentListChanged;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use App\Models\Page;
use App\Support\ContentFileExtras;
use App\Support\ContentPlacer;
use App\Support\CroppedThumbnails;
use App\Support\ExportPageLayout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CropFixtures;

/*
 * Fase B0 (2026-10-30): riproduzione del problema «miniatura di un contenuto
 * frazionario rimpicciolita invece che ritagliata»; Fase B1: corretto. I
 * test descrivono il comportamento in termini di «quale parte del file si
 * vede nel riquadro» (finestra in % dell'immagine), indipendentemente dal
 * markup: quelli marcati «bug B0» fallivano prima della correzione, gli
 * altri sono la regressione di ciò che già funzionava.
 *
 * Regola attesa: la pagina del file (rifilata, senza abbondanza) copre la
 * pagina del numero, centrata; il riquadro del contenuto ne mostra la sua
 * porzione. Un file già nella misura del formato si vede intero.
 *
 * gridAd() è in PageGridMoveTest.php, reorderableIssue()/editorFor() in
 * TimoneGridReorderTest.php.
 */

beforeEach(function () {
    Event::fake([ContentAssigned::class, ContentListChanged::class, ContentFileUploaded::class]);
    Storage::fake('local');
    // Rotazione e simili trasmettono altri eventi: qui nessun server Reverb.
    config(['broadcasting.default' => 'null']);
});

/** Numero di prova con il formato pagina indicato (mm). */
function cropIssue(int $width, int $height): Issue
{
    $issue = reorderableIssue();
    $issue->update(['formato_base_mm' => $width, 'formato_altezza_mm' => $height]);

    return $issue->fresh();
}

/**
 * File di un contenuto con una miniatura PNG vera (GD) larga 400 px e con
 * le proporzioni date: quello che produce oggi il job delle miniature.
 * $pdf: uno dei PDF di CropFixtures, salvato come file del contenuto.
 */
function cropFile(Content $content, float $thumbnailRatio, ?float $measuredRatio, ?string $pdf = null, int $pdfPage = 1): ContentFile
{
    $thumbnail = "contents/{$content->id}/thumbnails/".uniqid().'.png';
    $image = imagecreatetruecolor(400, (int) round(400 / $thumbnailRatio));
    ob_start();
    imagepng($image);
    Storage::disk('local')->put($thumbnail, ob_get_clean());

    $path = "contents/{$content->id}/".uniqid().'.pdf';
    Storage::disk('local')->put($path, $pdf ?? CropFixtures::sixths(210, 297, 0, 1));

    return ContentFile::factory()->create([
        'content_id' => $content->id,
        'path' => $path,
        'disk' => 'local',
        'mime_type' => 'application/pdf',
        'pdf_page_number' => $pdfPage,
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_path' => $thumbnail,
        'aspect_ratio' => $measuredRatio,
    ]);
}

/**
 * Quale parte dell'immagine si vede nel riquadro, in % dell'immagine
 * [x, y, w, h], dallo stile dell'<img> (CSS della card o dell'export).
 * - object-fit: contain → tutta l'immagine, rimpicciolita ('contain');
 * - nessuno stile di posizione → tutta l'immagine, stirata ('stretch');
 * - immagine grande come la pagina e spostata, object-fit: cover → la
 *   porzione del riquadro, con il centraggio di cover se le proporzioni
 *   dell'immagine non sono quelle della pagina.
 *
 * @param  array{x: float, y: float, w: float, h: float}  $rect  riquadro in % della pagina
 * @return array{mode: string, window: list<float>}
 */
function visibleWindow(?string $style, array $rect, float $pageRatio, float $imageRatio): array
{
    if ($style === null || ! preg_match('/width:\s*([\d.]+)%/', $style)) {
        return ['mode' => 'stretch', 'window' => [0.0, 0.0, 100.0, 100.0]];
    }

    if (str_contains($style, 'object-fit: contain')) {
        return ['mode' => 'contain', 'window' => [0.0, 0.0, 100.0, 100.0]];
    }

    preg_match('/left:\s*(-?[\d.]+)%/', $style, $l);
    preg_match('/top:\s*(-?[\d.]+)%/', $style, $t);
    preg_match('/width:\s*([\d.]+)%/', $style, $w);
    preg_match('/height:\s*([\d.]+)%/', $style, $h);

    // Riquadro dell'<img> in % della pagina.
    $box = ['x' => $rect['x'] + $l[1] / 100 * $rect['w'], 'y' => $rect['y'] + $t[1] / 100 * $rect['h'], 'w' => $w[1] / 100 * $rect['w'], 'h' => $h[1] / 100 * $rect['h']];
    $boxRatio = $box['w'] / $box['h'] * $pageRatio;
    // object-fit: cover, centrato.
    [$ix, $iy, $iw, $ih] = $imageRatio > $boxRatio
        ? [(1 - $boxRatio / $imageRatio) / 2, 0, $boxRatio / $imageRatio, 1]
        : [0, (1 - $imageRatio / $boxRatio) / 2, 1, $imageRatio / $boxRatio];

    $u = ($rect['x'] - $box['x']) / $box['w'];
    $v = ($rect['y'] - $box['y']) / $box['h'];

    return ['mode' => 'crop', 'window' => array_map(fn ($n) => round($n * 100, 2), [
        $ix + $u * $iw, $iy + $v * $ih, $rect['w'] / $box['w'] * $iw, $rect['h'] / $box['h'] * $ih,
    ])];
}

/**
 * La finestra attesa: la pagina rifilata del file ($trim, in % dell'immagine)
 * copre la pagina del numero, centrata; il riquadro ne mostra la porzione.
 *
 * @param  list<float>  $trim  [x, y, w, h] della pagina rifilata nell'immagine
 * @return list<float>
 */
function expectedWindow(array $rect, float $pageRatio, float $imageRatio, array $trim = [0, 0, 100, 100]): array
{
    $trimRatio = $imageRatio * $trim[2] / $trim[3];
    [$px, $py, $pw, $ph] = $trimRatio > $pageRatio
        ? [$trim[0] + $trim[2] * (1 - $pageRatio / $trimRatio) / 2, $trim[1], $trim[2] * $pageRatio / $trimRatio, $trim[3]]
        : [$trim[0], $trim[1] + $trim[3] * (1 - $trimRatio / $pageRatio) / 2, $trim[2], $trim[3] * $trimRatio / $pageRatio];

    return array_map(fn ($n) => round($n, 2), [
        $px + $rect['x'] / 100 * $pw, $py + $rect['y'] / 100 * $ph, $rect['w'] / 100 * $pw, $rect['h'] / 100 * $ph,
    ]);
}

function blockOf(Page $page, Content $content): array
{
    $page = $page->fresh(['issue', 'contents.files', 'contents.advertisement']);

    return $page->layoutBlockFor($page->contents->firstWhere('id', $content->id));
}

function expectWindow(array $actual, array $expected): void
{
    expect($actual['mode'])->toBe('crop');

    foreach ($expected as $i => $value) {
        expect($actual['window'][$i])->toEqualWithDelta($value, 0.6);
    }
}

test('regression: A4 full-page file on an A4 issue, half vertical on the left → left half', function () {
    $issue = cropIssue(210, 297);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    cropFile($ad, 400 / 566, 400 / 566);
    ContentPlacer::place($ad, $page);

    $block = blockOf($page, $ad);

    expectWindow(visibleWindow($block['imageStyle'], $block['rect'], 210 / 297, 400 / 566), [0, 0, 50, 100]);
});

test('bug B0: A4 full-page file on a 210×270 issue is shown cropped, not shrunk (timone and flipbook)', function () {
    $issue = cropIssue(210, 270);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    $file = cropFile($ad, 400 / 566, 400 / 566);
    ContentPlacer::place($ad, $page);
    // Misure del PDF (il job delle miniature lo fa all'upload): 210×297.
    ContentFileExtras::complete($file);

    $block = blockOf($page, $ad);

    // Prima: rapporto 0,707 contro 0,778 della pagina, oltre il 5% → «nel
    // formato» → object-fit: contain, tutto il PDF rimpicciolito. Lo stesso
    // piano decide il PDF sfogliabile (MagazinePdfComposer).
    expect($block['fit']->value)->toBe('full_page');
    expectWindow(visibleWindow($block['imageStyle'], $block['rect'], 210 / 270, 400 / 566), expectedWindow($block['rect'], 210 / 270, 400 / 566));
});

test('bug B0: a file uploaded before the proportions were measured is cropped too', function () {
    $issue = cropIssue(210, 297);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    // aspect_ratio NULL: 12 file su 18 nel database di sviluppo. Ora la card
    // lo misura dalla miniatura la prima volta (istantaneo).
    cropFile($ad, 400 / 566, null);
    ContentPlacer::place($ad, $page);

    $block = blockOf($page, $ad);

    expectWindow(visibleWindow($block['imageStyle'], $block['rect'], 210 / 297, 400 / 566), [0, 0, 50, 100]);
});

test('bug B0: with 3 mm of bleed and a TrimBox the crop is on the trimmed page', function () {
    $issue = cropIssue(210, 297);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    // La miniatura è la MediaBox: 216×303 mm; la TrimBox la misura PdfBoxes.
    $thumbRatio = 216 / 303;
    $file = cropFile($ad, $thumbRatio, $thumbRatio, CropFixtures::sixths(210, 297, CropFixtures::BLEED_MM, 1));
    ContentPlacer::place($ad, $page);
    // Quello che fa il job di completamento accodato dalla card (qui subito).
    ContentFileExtras::complete($file);
    expect($file->fresh()->trim_box)->toBe(['media' => [216, 303], 'trim' => [3, 3, 210, 297]]);

    $block = blockOf($page, $ad);
    $trim = [3 / 216 * 100, 3 / 303 * 100, 210 / 216 * 100, 297 / 303 * 100];

    // Prima la finestra partiva da 0: dentro c'era l'abbondanza, ritaglio spostato di 3 mm.
    expectWindow(visibleWindow($block['imageStyle'], $block['rect'], 210 / 297, $thumbRatio), expectedWindow($block['rect'], 210 / 297, $thumbRatio, $trim));
});

test('regression: a file already in the format of the content is shown whole', function () {
    $issue = cropIssue(210, 270);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    cropFile($ad, 103 / 270, 103 / 270, CropFixtures::plain(103, 270, 'MEZZA', [22, 163, 74]));
    ContentPlacer::place($ad, $page);

    $block = blockOf($page, $ad);

    expect(visibleWindow($block['imageStyle'], $block['rect'], 210 / 270, 103 / 270)['mode'])->toBe('contain');
});

test('bug B0: an L-shaped content (3/4) shows the file on every piece of the L', function () {
    $issue = cropIssue(210, 297);
    $page = $issue->pages()->where('position', 1)->first();
    $quarter = gridAd($issue, AdFormat::UnQuartoPagina, 'Vetrina');
    ContentPlacer::place($quarter, $page);
    $ell = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'articolo', 'title' => 'Tre quarti', 'occupancy_twelfths' => 9]);
    cropFile($ell, 400 / 566, 400 / 566);
    ContentPlacer::place($ell, $page);

    $block = blockOf($page, $ell);
    expect($block['kind'])->toBe('fluid')->and(count($block['pieces']))->toBeGreaterThan(1);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();
    preg_match_all('/<div[^>]*data-testid="layout-piece"[^>]*>(.*?)<\/div>/s', $html, $pieces);

    // Prima i pezzi della L oltre al rettangolo principale erano riquadri colorati vuoti.
    expect($pieces[1])->not->toBeEmpty();
    foreach ($pieces[1] as $inner) {
        expect($inner)->toContain('<img');
    }
});

test('regression: a content on 3 pages uses on each page its own page of the PDF', function () {
    $issue = cropIssue(210, 297);
    $start = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'articolo', 'title' => 'Speciale', 'occupancy_twelfths' => 30]);
    $files = collect([1, 2, 3])->map(fn ($n) => cropFile($content, 400 / 566, 400 / 566, CropFixtures::sixths(210, 297, 0, 3), $n));
    ContentPlacer::place($content, $start);

    foreach ([1, 2, 3] as $position) {
        $block = blockOf($issue->pages()->where('position', $position)->first(), $content);
        expect($block['file']->id)->toBe($files[$position - 1]->id);
    }

    $last = blockOf($issue->pages()->where('position', 3)->first(), $content);
    expectWindow(visibleWindow($last['imageStyle'], $last['rect'], 210 / 297, 400 / 566), expectedWindow($last['rect'], 210 / 297, 400 / 566));
});

test('regression: after «⟳ Ruota» the crop follows the new rectangle', function () {
    $issue = cropIssue(210, 297);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    cropFile($ad, 400 / 566, 400 / 566);
    ContentPlacer::place($ad, $page);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->call('rotateContentOnGrid', $page->id, $ad->id);

    $block = blockOf($page, $ad);
    expect($block['rect']['w'])->toEqualWithDelta(100, 0.01);
    expectWindow(visibleWindow($block['imageStyle'], $block['rect'], 210 / 297, 400 / 566), expectedWindow($block['rect'], 210 / 297, 400 / 566));
});

test('bug B0: the timone export shows the same portion as the card, not the whole file stretched', function () {
    $issue = cropIssue(210, 297);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    cropFile($ad, 400 / 566, 400 / 566);
    ContentPlacer::place($ad, $page);

    $page = $page->fresh(['issue', 'contents.files', 'contents.advertisement', 'contents.article']);
    $layout = ExportPageLayout::forPage($page, 100, 141.43, true);
    $html = view('exports.partials.timone-sheet', [
        'page' => $page, 'side' => 'single', 'title' => '1', 'status' => $page->status->hexColors(), 'layout' => $layout, 'legacyThumb' => null,
        'layoutWidthPt' => 100, 'layoutHeightPt' => 141.43, 'articleFill' => '#e0f2fe', 'adFill' => '#fef3c7', 'lineChars' => fn () => 20,
    ])->render();

    // L'immagine dell'export è il ritaglio della STESSA finestra della card,
    // grande come il riquadro (proporzioni uguali: niente stiramento).
    $block = blockOf($page, $ad);
    $window = $block['crop']->windowFor($block['cells']);
    expectWindow(['mode' => 'crop', 'window' => $window], [0, 0, 50, 100]);
    expect($layout['blocks'][0]['img'])->toMatchArray(['src' => CroppedThumbnails::dataUri($block['file'], $window), 'x' => 0.0, 'y' => 0.0, 'w' => 50.0, 'h' => 141.43])
        ->and($html)->toContain('style="left: 0pt; top: 0pt; width: 50pt; height: 141.43pt;"');

    // Il ritaglio ha le proporzioni del riquadro (50 × 141,43 pt).
    $size = getimagesize(Storage::disk('local')->path(CroppedThumbnails::relativePath($block['file'], $window)));
    expect($size[0] / $size[1])->toEqualWithDelta(50 / 141.43, 0.01);
});
