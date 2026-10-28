<?php

use App\Enums\AdFormat;
use App\Enums\CoverSlot;
use App\Enums\MagazineExportStatus;
use App\Enums\MagazineExportVariant;
use App\Enums\UserRole;
use App\Jobs\GenerateMagazinePdf;
use App\Livewire\Timone\MagazineExportPanel;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use App\Models\MagazineExport;
use App\Models\Page;
use App\Models\User;
use App\Support\Deletion\StoredFiles;
use App\Support\MagazineExport\MagazineExportRequester;
use App\Support\MagazineExport\MagazinePageSequence;
use App\Support\MagazineExport\PageBox;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\Process\Process;

// Revisione 2, Fase 5 (2026-10-28): PDF sfogliabile della rivista
// (MagazinePdfComposer con TCPDF + FPDI, job GenerateMagazinePdf). I PDF
// sorgente sono generati con TCPDF stesso (validi, con testo estraibile).
// Il testo del risultato si estrae con Ghostscript (txtwrite): quei test
// girano dove c'è `gs` (Docker), altrimenti sono saltati.

beforeEach(function () {
    Storage::fake('local');
});

function sourcePdf(string $text, float $widthMm = 210, float $heightMm = 270): string
{
    $pdf = new TCPDF('P', 'mm', [$widthMm, $heightMm], true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 24);
    $pdf->Text(20, 40, $text);

    return $pdf->Output('', 'S');
}

/** Contenuto su una pagina, con un file (o un file che manca su disco, o nessun file). */
function exportContent(Page $page, string $type, string $title, int $twelfths, ?string $pdfText = null, bool $fileMissingOnDisk = false, ?AdFormat $format = null, bool $placeholder = false): Content
{
    $content = Content::factory()->create(['issue_id' => $page->issue_id, 'type' => $type, 'title' => $title, 'occupancy_twelfths' => $twelfths, 'is_placeholder' => $placeholder]);

    $type === 'articolo'
        ? Article::factory()->create(['content_id' => $content->id])
        : Advertisement::factory()->create(['content_id' => $content->id, 'format' => $format ?? AdFormat::PaginaIntera, 'client' => $placeholder ? null : $title]);

    if ($pdfText !== null || $fileMissingOnDisk) {
        $path = "contents/{$content->id}/file.pdf";

        if (! $fileMissingOnDisk) {
            Storage::disk('local')->put($path, sourcePdf($pdfText));
        }

        ContentFile::factory()->create(['content_id' => $content->id, 'disk' => 'local', 'path' => $path, 'mime_type' => 'application/pdf', 'pdf_page_number' => 1, 'size' => 1000]);
    }

    $page->contents()->attach($content->id, ['occupied_twelfths' => $twelfths]);

    return $content;
}

/** Il numero della specifica: 4 copertine, intera, due mezze, segnaposto, pagina vuota, file mancante. */
function specIssue(): Issue
{
    $issue = Issue::factory()->create(['title' => 'Novembre 2026', 'total_pages' => 4, 'formato_base_mm' => 210, 'formato_altezza_mm' => 270]);
    $page = fn (int $position) => $issue->pages()->where('position', $position)->first();
    $cover = fn (CoverSlot $slot) => $issue->covers()->where('cover_slot', $slot->value)->first();

    exportContent($cover(CoverSlot::I), 'articolo', 'Copertina del mese', 12, 'COPERTINA-UNO');
    exportContent($page(1), 'articolo', 'Apertura', 12, 'APERTURA-INTERA');
    exportContent($page(2), 'pubblicita', 'Acme Tools', 6, 'ACME-MEZZA', format: AdFormat::MezzaPaginaOrizzontale);
    exportContent($page(2), 'pubblicita', 'Beta Spa', 6, 'BETA-MEZZA', format: AdFormat::MezzaPaginaOrizzontale);
    exportContent($page(3), 'pubblicita', 'Pubblicità pagina intera — da assegnare', 12, placeholder: true);
    exportContent($cover(CoverSlot::IV), 'pubblicita', 'Gamma Srl', 12, fileMissingOnDisk: true);

    return $issue;
}

function generate(Issue $issue, MagazineExportVariant $variant = MagazineExportVariant::Full): MagazineExport
{
    Queue::fake();
    $export = MagazineExportRequester::request($issue, $variant, null);
    (new GenerateMagazinePdf($export->id))->handle();

    return $export->fresh();
}

function exportedPath(MagazineExport $export): string
{
    return Storage::disk($export->disk)->path($export->path);
}

function pdfPageText(string $path, int $page): string
{
    $process = new Process(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=txtwrite', "-dFirstPage={$page}", "-dLastPage={$page}", '-sOutputFile=-', $path]);
    $process->run();

    return $process->getOutput();
}

function requireGhostscript(): void
{
    if ((new Process(['gs', '--version']))->run() !== 0) {
        test()->markTestSkipped('Ghostscript non disponibile su questa macchina (solo dentro Docker).');
    }
}

test('page box: the magazine page is scaled into A4 keeping its proportions, centred, footer outside', function () {
    $box = PageBox::forRatio(210 / 270);

    expect(round($box->width / $box->height, 4))->toBe(round(210 / 270, 4))
        ->and($box->x + $box->width / 2)->toEqualWithDelta(PageBox::SHEET_WIDTH / 2, 0.01)
        ->and($box->y + $box->height + PageBox::FOOTER)->toBeLessThanOrEqual(PageBox::SHEET_HEIGHT - PageBox::MARGIN + 0.01)
        ->and($box->footerY())->toBeGreaterThan($box->y + $box->height)
        ->and(PageBox::contain(['x' => 0, 'y' => 0, 'w' => 100, 'h' => 50], 200, 200))->toBe(['x' => 25.0, 'y' => 0.0, 'w' => 50.0, 'h' => 50.0]);
});

test('page order: I and II of cover, internal pages 1…N, III and IV (bandella after the I)', function () {
    $issue = Issue::factory()->create(['total_pages' => 3]);
    Page::create(['issue_id' => $issue->id, 'cover_slot' => CoverSlot::Bandella->value, 'status' => 'da_assegnare', 'content_type' => 'bianca']);

    expect(MagazinePageSequence::for($issue)->pluck('label')->all())->toBe([
        'I di copertina', 'Bandella', 'II di copertina', 'Pagina 1', 'Pagina 2', 'Pagina 3', 'III di copertina', 'IV di copertina',
    ]);
});

test('spec: N+4 pages, all A4, and the job does not fail with a source file missing on disk', function () {
    $export = generate(specIssue());

    expect($export->status)->toBe(MagazineExportStatus::Ready)
        ->and($export->page_count)->toBe(8)
        ->and($export->missing_files)->toBe(2) // il segnaposto (nessun file) e la IV (file assente su disco)
        ->and($export->error)->toBeNull();

    $reader = new Fpdi;
    $count = $reader->setSourceFile(exportedPath($export));
    expect($count)->toBe(8);

    foreach (range(1, $count) as $n) {
        $size = $reader->getTemplateSize($reader->importPage($n));
        expect(round($size['width'], 1))->toBe(210.0)->and(round($size['height'], 1))->toBe(297.0);
    }

    $raw = file_get_contents(exportedPath($export));
    expect($raw)->toContain('/PageLayout /TwoPageRight')->toContain('/Outlines');
});

test('spec: pages in the right order with their content, placeholder and missing file drawn with their text', function () {
    requireGhostscript();

    $path = exportedPath(generate(specIssue()));
    $text = fn (int $page) => preg_replace('/\s+/', ' ', pdfPageText($path, $page));

    expect($text(1))->toContain('COPERTINA-UNO')->toContain('I di copertina')
        ->and($text(2))->toContain('II di copertina')
        ->and($text(3))->toContain('APERTURA-INTERA')->toContain('Pagina 1')
        ->and($text(4))->toContain('ACME-MEZZA')->toContain('BETA-MEZZA')->toContain('Pagina 2')
        ->and($text(5))->toContain('Pubblicità pagina intera')->toContain('segnaposto')->toContain('File mancante')->toContain('Pagina 3')
        ->and($text(6))->toContain('Pagina bianca')->toContain('Pagina 4')
        ->and($text(7))->toContain('III di copertina')
        ->and($text(8))->toContain('Gamma Srl')->toContain('File mancante')->toContain('IV di copertina');
});

test('a "full page" file shows only its portion: placed in the bottom half, the top of the source page is cropped away', function () {
    requireGhostscript();

    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile (solo dentro Docker).');
    }

    $issue = Issue::factory()->create(['total_pages' => 1, 'formato_base_mm' => 210, 'formato_altezza_mm' => 270]);
    $content = exportContent($issue->pages()->first(), 'pubblicita', 'Delta', 6, 'TESTO-IN-ALTO', format: AdFormat::MezzaPaginaOrizzontale);
    // File a pagina intera (stesso rapporto del numero) nella metà in basso:
    // si deve vedere solo la metà inferiore della pagina sorgente, bianca.
    ContentFile::where('content_id', $content->id)->update(['aspect_ratio' => 210 / 270]);
    $issue->pages()->first()->contents()->updateExistingPivot($content->id, ['grid_x' => 0, 'grid_y' => 3, 'grid_w' => 6, 'grid_h' => 3]);

    // Pagina 3 del PDF = «Pagina 1» del numero (prima ci sono I e II di copertina).
    $darkPixelsInTopHalf = function (string $pdf) {
        $png = sys_get_temp_dir().'/crop-'.uniqid().'.png';
        (new Process(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pnggray', '-r50', '-dFirstPage=3', '-dLastPage=3', '-o', $png, $pdf]))->run();
        $image = new Imagick($png);
        $box = PageBox::forRatio(210 / 270);
        $pxPerMm = 50 / 25.4;
        $dark = 0;
        // Solo dentro la pagina del numero, metà alta, escluso il bordo sottile.
        for ($y = (int) (($box->y + 2) * $pxPerMm); $y < (int) (($box->y + $box->height / 2 - 2) * $pxPerMm); $y++) {
            for ($x = (int) (($box->x + 2) * $pxPerMm); $x < (int) (($box->x + $box->width - 2) * $pxPerMm); $x++) {
                $dark += $image->getImagePixelColor($x, $y)->getColorValue(Imagick::COLOR_RED) < 0.5 ? 1 : 0;
            }
        }
        unlink($png);

        return $dark;
    };

    expect($darkPixelsInTopHalf(exportedPath(generate($issue))))->toBe(0);

    // Controprova: lo stesso file nel formato (senza ritaglio) occupa la
    // metà in basso intero, quindi anche lì la metà alta resta bianca; con
    // il contenuto spostato nella metà alta il testo si vede.
    $issue->pages()->first()->contents()->updateExistingPivot($content->id, ['grid_y' => 0]);
    expect($darkPixelsInTopHalf(exportedPath(generate($issue))))->toBeGreaterThan(0);
});

test('a PDF 1.5 with compressed object streams (not readable by free FPDI) is rewritten with Ghostscript and imported', function () {
    requireGhostscript();

    $issue = Issue::factory()->create(['total_pages' => 1, 'formato_base_mm' => 210, 'formato_altezza_mm' => 270]);
    $content = exportContent($issue->pages()->first(), 'articolo', 'Servizio', 12, 'SORGENTE-COMPRESSO');
    $file = ContentFile::where('content_id', $content->id)->first();
    $plain = Storage::disk('local')->path($file->path);
    $compressed = $plain.'.objstm.pdf';
    (new Process(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.5', '-dWriteObjStms=true', '-dWriteXRefStm=true', '-sOutputFile='.$compressed, $plain]))->run();
    rename($compressed, $plain);

    expect(file_get_contents($plain))->toContain('/ObjStm');
    expect(fn () => (new Fpdi)->setSourceFile($plain))->toThrow(PdfParserException::class);

    $export = generate($issue);

    expect($export->missing_files)->toBe(0)
        ->and(pdfPageText(exportedPath($export), 3))->toContain('SORGENTE-COMPRESSO');
});

test('the preview variant is a separate, lighter file and keeps the two-page layout', function () {
    requireGhostscript();

    $issue = specIssue();
    $full = generate($issue);
    $preview = generate($issue, MagazineExportVariant::Preview);

    expect($preview->status)->toBe(MagazineExportStatus::Ready)
        ->and($preview->path)->not->toBe($full->path)
        ->and($preview->page_count)->toBe(8)
        ->and(file_get_contents(exportedPath($preview)))->toContain('TwoPageRight');
});

test('a second request while a job is queued does not queue another one', function () {
    Queue::fake();
    $issue = Issue::factory()->create(['total_pages' => 2]);

    MagazineExportRequester::request($issue, MagazineExportVariant::Full, null);
    MagazineExportRequester::request($issue, MagazineExportVariant::Full, null);

    Queue::assertPushed(GenerateMagazinePdf::class, 1);
    expect(MagazineExport::count())->toBe(1);
});

test('regenerating replaces the previous file of the same variant', function () {
    $issue = specIssue();
    $first = generate($issue);
    $second = generate($issue);

    expect($second->id)->toBe($first->id)
        ->and($second->path)->toBe($first->path)
        ->and(MagazineExport::count())->toBe(1)
        ->and(Storage::disk('local')->files('exports/issue-'.$issue->id))->toBe(['exports/issue-'.$issue->id.'/full.pdf']);
});

test('a failure marks the export as "errore" with the reason, and the temporary folder is removed', function () {
    $issue = specIssue();
    config(['timone.magazine_export.preview_settings' => '/inesistente']);
    Queue::fake();
    $export = MagazineExportRequester::request($issue, MagazineExportVariant::Preview, null);

    // Senza Ghostscript (locale) o con impostazioni non valide la variante leggera fallisce.
    (new GenerateMagazinePdf($export->id))->handle();

    expect($export->fresh()->status)->toBe(MagazineExportStatus::Failed)
        ->and($export->fresh()->error)->toContain('bassa risoluzione')
        ->and(collect(Storage::disk('local')->directories('exports'))->filter(fn ($dir) => str_contains($dir, 'tmp-'))->all())->toBe([]);
});

test('panel: anyone who can see the issue queues it and sees the status; download only when ready and allowed', function () {
    Queue::fake();
    $issue = specIssue();
    $reader = User::factory()->create(['role' => UserRole::SolaLettura]);
    $reader->magazines()->attach($issue->magazine);
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    Livewire::actingAs($reader)->test(MagazineExportPanel::class, ['issue' => $issue])
        ->call('generate')
        ->assertSee('In coda')
        ->assertSeeHtml('wire:poll.3s');
    Queue::assertPushed(GenerateMagazinePdf::class);

    $url = route('issues.export.magazine-pdf', [$issue->magazine, $issue, 'full']);
    $this->actingAs($reader)->get($url)->assertNotFound(); // non ancora pronto

    (new GenerateMagazinePdf(MagazineExport::first()->id))->handle();

    $this->actingAs($reader)->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($outsider)->get($url)->assertForbidden();
});

test('deleting the issue also removes its generated PDFs from disk (queued with the other files)', function () {
    $issue = specIssue();
    $export = generate($issue);

    expect(collect(StoredFiles::forIssues([$issue->id])->paths)->pluck('path'))->toContain($export->path);
});
