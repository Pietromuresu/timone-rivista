<?php

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\PageStatus;
use App\Enums\ThumbnailStatus;
use App\Enums\UserRole;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\PageFile;
use App\Models\User;
use App\Support\ContentPlacer;
use App\Support\ExportPageLayout;
use App\Support\ExportThumbnail;
use App\Support\IssueCovers;
use App\Support\IssueSummary;
use App\Support\PageSpreadBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// reorderableIssue() ed editorFor() sono definite globalmente in
// TimoneGridReorderTest.php (issue con total_pages = 6).

test('an editor can download the full timone pdf', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('the onlyAds filter excludes pages without an advertisement', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $adContent = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create([
        'content_id' => $adContent->id,
        'format' => AdFormat::PaginaIntera,
        'confirmation_status' => AdConfirmationStatus::Confermata,
    ]);
    $adPage = $issue->pages()->where('position', 1)->first();
    $adPage->contents()->attach($adContent->id, ['occupied_twelfths' => 12]);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'onlyAds' => 1]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('the onlyUnapproved filter excludes pages already revisionata or ok stampa', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $page = $issue->pages()->where('position', 1)->first();
    $page->update(['status' => PageStatus::OkStampa]);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'onlyUnapproved' => 1]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('withThumbnails embeds a real ready thumbnail without error', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Storage::disk('local')->put('pages/1/thumb.png', 'fake-png-bytes');
    PageFile::factory()->for($page)->ready()->create([
        'disk' => 'local',
        'path' => 'pages/1/doc.pdf',
        'thumbnail_path' => 'pages/1/thumb.png',
    ]);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'withThumbnails' => 1]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('withThumbnails does not crash when a thumbnail is still processing', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    PageFile::factory()->for($page)->create([
        'disk' => 'local',
        'path' => 'pages/1/doc.pdf',
    ]);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'withThumbnails' => 1]));

    $response->assertOk();
});

test('the export renders the covers in a separate section before the internal pages', function () {
    // Fase 2 (2026-10-26): copertine I-IV (e bandella) come slot separati.
    $issue = reorderableIssue();
    $user = editorFor($issue);
    IssueCovers::addBandella($issue);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]));
    $response->assertOk();

    $with = ['contents.article', 'contents.advertisement', 'files' => fn ($q) => $q->latest()->limit(1)];
    $covers = $issue->covers()->with($with)->get()->sortBy(fn ($c) => $c->cover_slot->order())->values();
    $pages = $issue->pages()->with($with)->get();

    $html = view('exports.timone', [
        'issue' => $issue,
        'coverPages' => $covers,
        'spreadRows' => array_chunk(PageSpreadBuilder::slots($pages), 6),
        'totalPages' => $pages->count(),
        'withThumbnails' => false,
        'filtersApplied' => false,
    ])->render();

    expect($html)
        ->toContain('Copertine')
        ->toContain('I di copertina')
        ->toContain('Bandella')
        ->toContain('IV di copertina')
        ->toContain('Pagine interne')
        ->toContain('6 pagine');

    // Le copertine compaiono prima delle pagine interne, mai dentro.
    expect(strpos($html, 'IV di copertina'))->toBeLessThan(strpos($html, 'Pagine interne'));
});

test('a sola lettura user (read-only) can still export the timone pdf', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);

    $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]))->assertOk();
});

test('a user without access to the magazine cannot export the timone pdf', function () {
    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    $this->actingAs($outsider)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]))->assertForbidden();
});

test('a guest cannot export the timone pdf', function () {
    $issue = reorderableIssue();

    $this->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]))->assertRedirect(route('login'));
});

test('an issue url is not found when the issue does not belong to the magazine in the url', function () {
    $issue = reorderableIssue();
    $otherIssue = reorderableIssue();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('issues.export.timone-pdf', [$issue->magazine, $otherIssue]))
        ->assertNotFound();
});

// Bug segnalato il 2026-10-27: le miniature dei contenuti (il percorso di
// caricamento dal 2026-09-02) non comparivano mai nel PDF esportato, che
// leggeva solo i vecchi PDF caricati sulla pagina. Il test precedente
// controllava solo "nessun errore": questi verificano che l'immagine sia
// davvero nel PDF (oggetto /Subtype /Image di dompdf).
function readyContentThumbnail($issue, ?string $coverSlot = null, int $position = 1): void
{
    $png = UploadedFile::fake()->image('thumb.png', 20, 30)->get();
    Storage::disk('local')->put('contents/thumb.png', $png);

    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    ContentFile::factory()->create([
        'content_id' => $content->id,
        'disk' => 'local',
        'thumbnail_path' => 'contents/thumb.png',
        'thumbnail_status' => ThumbnailStatus::Ready,
        'mime_type' => 'application/pdf',
    ]);

    $page = $coverSlot !== null
        ? $issue->covers()->where('cover_slot', $coverSlot)->first()
        : $issue->pages()->where('position', $position)->first();
    $page->contents()->attach($content->id, ['occupied_twelfths' => 12]);
}

test('withThumbnails embeds the thumbnail of the content assigned to a page', function () {
    Storage::fake('local');
    $issue = reorderableIssue();
    readyContentThumbnail($issue);

    $pdf = $this->actingAs(editorFor($issue))
        ->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'withThumbnails' => 1]))
        ->assertOk()
        ->getContent();

    expect($pdf)->toContain('/Subtype /Image');
});

test('the thumbnail of a content on a cover is embedded too', function () {
    Storage::fake('local');
    $issue = reorderableIssue();
    readyContentThumbnail($issue, coverSlot: 'II');

    $pdf = $this->actingAs(editorFor($issue))
        ->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'withThumbnails' => 1]))
        ->getContent();

    expect($pdf)->toContain('/Subtype /Image');
});

test('without the thumbnails option no image is embedded', function () {
    Storage::fake('local');
    $issue = reorderableIssue();
    readyContentThumbnail($issue);

    $pdf = $this->actingAs(editorFor($issue))
        ->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]))
        ->getContent();

    expect($pdf)->not->toContain('/Subtype /Image');
});

test('the export thumbnail helper skips files that are not ready or missing on disk', function () {
    Storage::fake('local');
    $file = ContentFile::factory()->make(['disk' => 'local', 'thumbnail_path' => 'nope.png', 'thumbnail_status' => ThumbnailStatus::Ready, 'mime_type' => 'application/pdf']);

    expect(ExportThumbnail::dataUri($file))->toBeNull()
        ->and(ExportThumbnail::dataUri(null))->toBeNull();

    Storage::disk('local')->put('ok.png', 'x');
    $file->thumbnail_path = 'ok.png';
    expect(ExportThumbnail::dataUri($file))->toStartWith('data:image/png;base64,');

    $file->thumbnail_status = ThumbnailStatus::Processing;
    expect(ExportThumbnail::dataUri($file))->toBeNull();
});

// Segnalazione del 2026-10-27, dopo la correzione sopra: "nel pdf c'è solo
// una miniatura, non tutte quelle della pagina". Ora ogni contenuto ha la
// sua miniatura nella stessa disposizione della card (ExportPageLayout).
function colouredPng(string $path, array $rgb): void
{
    $image = imagecreatetruecolor(40, 60);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
    ob_start();
    imagepng($image);
    Storage::disk('local')->put($path, ob_get_clean());
}

test('every content of a page gets its own thumbnail in the exported pdf', function () {
    Storage::fake('local');
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    foreach ([['a.png', [220, 30, 30]], ['b.png', [30, 60, 220]], ['c.png', [30, 160, 60]]] as [$path, $rgb]) {
        colouredPng($path, $rgb);
        $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => 4]);
        ContentFile::factory()->create(['content_id' => $content->id, 'disk' => 'local', 'thumbnail_path' => $path, 'thumbnail_status' => ThumbnailStatus::Ready, 'mime_type' => 'application/pdf']);
        ContentPlacer::place($content, $page);
    }

    $pdf = $this->actingAs(editorFor($issue))
        ->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'withThumbnails' => 1]))
        ->getContent();

    expect(substr_count($pdf, '/Subtype /Image'))->toBe(3);
});

test('the export layout uses the same blocks as the card, converted to points', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $vertical = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'occupancy_twelfths' => 6]);
    Advertisement::factory()->create(['content_id' => $vertical->id, 'format' => AdFormat::MezzaPaginaVerticale]);
    $quarter = Content::factory()->article()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => 3]);
    ContentPlacer::place($vertical, $page);
    ContentPlacer::place($quarter, $page);

    $layout = ExportPageLayout::forPage($page->fresh()->load('contents.advertisement', 'contents.files'), 80, 100, false);

    expect($layout['blocks'][0])->toMatchArray(['x' => 0.0, 'y' => 0.0, 'w' => 40.0, 'h' => 100.0, 'isAd' => true, 'src' => null])
        ->and($layout['blocks'][1])->toMatchArray(['x' => 40.0, 'y' => 0.0, 'w' => 40.0, 'h' => 50.0, 'isAd' => false])
        ->and($layout['free'])->toBe(['x' => 40.0, 'y' => 50.0, 'w' => 40.0, 'h' => 50.0])
        ->and(ExportPageLayout::forPage($issue->pages()->where('position', 2)->first(), 80, 100, true))->toBeNull();
});

// Restyling dell'export (2026-10-27): aperture come sfogliando la rivista.
test('pages are laid out in facing spreads: page 1 alone on the right, the last one alone on the left when even', function () {
    $issue = reorderableIssue(); // 6 pagine
    $positions = fn (array $slots) => array_map(fn (array $slot) => array_map(fn ($page) => $page?->position, $slot), $slots);

    expect($positions(PageSpreadBuilder::slots($issue->pages()->get())))
        ->toBe([[null, 1], [2, 3], [4, 5], [6, null]]);
});

test('the export header carries the same numbers as the summary bar', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->with('contents.advertisement')->get();
    $covers = $issue->covers()->with('contents.advertisement')->get();

    $html = view('exports.timone', [
        'issue' => $issue,
        'coverPages' => $covers,
        'spreadRows' => array_chunk(PageSpreadBuilder::slots($pages), 6),
        'totalPages' => $pages->count(),
        'withThumbnails' => false,
        'filtersApplied' => false,
        'summary' => IssueSummary::summarize($pages, $covers, collect(), ['adLoadPercentage' => 12.5], null),
    ])->render();

    expect($html)->toContain('Spazio libero')
        ->toContain('6 pagine')
        ->toContain('12,50%')
        ->toContain('0 piene')
        ->toContain('6 bianche');
});
