<?php

use App\Enums\AdFormat;
use App\Enums\ThumbnailStatus;
use App\Events\ContentAssigned;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Fase 7a (2026-10-27): disposizione dei contenuti nella card della pagina.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class]));

function layoutContent(Issue $issue, int $position, int $twelfths, ?AdFormat $format = null, bool $placeholder = false): Content
{
    $content = Content::factory()->create([
        'issue_id' => $issue->id,
        'type' => $format ? 'pubblicita' : 'articolo',
        'occupancy_twelfths' => $twelfths,
        'is_placeholder' => $placeholder,
    ]);

    if ($format) {
        Advertisement::factory()->create(['content_id' => $content->id, 'format' => $format, 'client' => $placeholder ? null : 'Cliente']);
    }

    $issue->pages()->where('position', $position)->first()->contents()->attach($content->id, ['occupied_twelfths' => $twelfths]);

    return $content;
}

/** Stile inline del blocco di un contenuto nell'HTML renderizzato. */
function blockStyle(string $html, int $contentId): string
{
    preg_match('/style="([^"]*)"\s+title="[^"]*"\s+data-testid="layout-block"\s+data-content-id="'.$contentId.'"/', $html, $m);

    return $m[1] ?? '';
}

test('each content is a block proportional to its space, with the free space shown', function (string $viewMode) {
    $issue = reorderableIssue();
    $vertical = layoutContent($issue, 1, 6, AdFormat::MezzaPaginaVerticale);
    $article = layoutContent($issue, 1, 3);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', $viewMode)
        ->html();

    expect(blockStyle($html, $vertical->id))->toBe('left: 0%; top: 0%; width: 50%; height: 100%;')
        ->and(blockStyle($html, $article->id))->toBe('left: 50%; top: 0%; width: 50%; height: 50%;')
        ->and($html)->toContain('data-testid="layout-free"');
})->with(['griglia', 'doppia']);

test('each block shows the preview of its own file', function () {
    $issue = reorderableIssue();
    $ad = layoutContent($issue, 1, 6, AdFormat::MezzaPaginaOrizzontale);
    $article = layoutContent($issue, 1, 6);
    $adFile = ContentFile::factory()->create(['content_id' => $ad->id, 'thumbnail_status' => ThumbnailStatus::Ready, 'thumbnail_path' => 'a.png']);
    $articleFile = ContentFile::factory()->create(['content_id' => $article->id, 'thumbnail_status' => ThumbnailStatus::Ready, 'thumbnail_path' => 'b.png']);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('src="'.route('content-files.thumbnail', $adFile).'"')
        ->assertSeeHtml('src="'.route('content-files.thumbnail', $articleFile).'"');
});

// Revisione 2, Fase 1, bug 3: i comandi dei blocchi sono nel menu «⋯»
// (prima comparivano al passaggio del mouse, tagliati nei blocchi piccoli).
test('block controls are in the block menu and placeholders stay dashed', function () {
    $issue = reorderableIssue();
    layoutContent($issue, 2, 3, AdFormat::UnQuartoPagina, placeholder: true);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('data-testid="content-menu-button"')
        ->assertSeeHtml('border-dashed border-amber-500')
        ->assertSee('Da assegnare');
});

test('a blank page still says so, and the list view keeps the text rows', function () {
    $issue = reorderableIssue();
    $content = layoutContent($issue, 1, 12);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSee('Pagina bianca');

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'lista')
        ->assertDontSeeHtml('data-testid="layout-block"')
        ->assertSee($content->title);
});

test('covers use the same layout', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'occupancy_twelfths' => 4]);
    Advertisement::factory()->create(['content_id' => $content->id, 'format' => AdFormat::Piedino]);
    $issue->covers()->where('cover_slot', 'IV')->first()->contents()->attach($content->id, ['occupied_twelfths' => 4]);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    expect(blockStyle($html, $content->id))->toBe('left: 0%; top: 66.6667%; width: 100%; height: 33.3333%;');
});
