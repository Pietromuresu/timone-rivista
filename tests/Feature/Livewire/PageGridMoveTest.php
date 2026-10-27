<?php

use App\Enums\AdFormat;
use App\Events\ContentAssigned;
use App\Events\PageContentsReordered;
use App\Livewire\Timone\Grid;
use App\Models\ActivityLog;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;
use App\Models\User;
use App\Support\ContentPlacer;
use App\Support\PageGrid\PageGridPositions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Revisione 2, Fase 2.3 (2026-10-27): griglia 6×6, frecce nelle 4 direzioni
// e rotazione (sostituisce l'ordine con ↑/↓ della Fase 7b).
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class, PageContentsReordered::class, \App\Events\ContentListChanged::class]));

function gridAd(Issue $issue, AdFormat $format, string $client): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'occupancy_twelfths' => $format->twelfths()]);
    Advertisement::factory()->create(['content_id' => $content->id, 'format' => $format, 'client' => $client]);

    return $content;
}

function cellsOf(Page $page, Content $content): ?string
{
    $pivot = DB::table('page_content')->where('page_id', $page->id)->where('content_id', $content->id)->first();

    return $pivot->grid_w === null ? null : "{$pivot->grid_w}×{$pivot->grid_h} in ({$pivot->grid_x},{$pivot->grid_y})";
}

test('an assigned content gets its rectangle in the first free place and the others do not move', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $vertical = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    $vetrina = gridAd($issue, AdFormat::UnQuartoPagina, 'Vetrina');
    $footer = gridAd($issue, AdFormat::Piedino, 'Piedino');

    ContentPlacer::place($vertical, $page);
    ContentPlacer::place($vetrina, $page);

    expect(cellsOf($page, $vertical))->toBe('3×6 in (0,0)')
        ->and(cellsOf($page, $vetrina))->toBe('3×3 in (3,0)');

    // Il piedino (6×2) non ci sta più: 1/2 + 1/4 + 1/3 > 1 pagina.
    expect(ContentPlacer::place($footer, $page))->toBeString()
        ->and(cellsOf($page, $vertical))->toBe('3×6 in (0,0)');
});

test('an arrow moves the content on the grid, is saved, logged and told to the other users', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $vetrina = gridAd($issue, AdFormat::UnQuartoPagina, 'Vetrina');
    ContentPlacer::place($vetrina, $page);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('moveContentOnGrid', $page->id, $vetrina->id, 'right')
        ->assertHasNoErrors()
        ->call('moveContentOnGrid', $page->id, $vetrina->id, 'down')
        ->assertHasNoErrors();

    expect(cellsOf($page, $vetrina))->toBe('3×3 in (3,3)')
        ->and(ActivityLog::where('action', 'page.content_moved')->count())->toBe(2);
    Event::assertDispatched(PageContentsReordered::class, fn ($e) => $e->pageId === $page->id);
});

test('two contents that form a rectangle swap places; an impossible move is refused on the server', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $a = gridAd($issue, AdFormat::UnQuartoPagina, 'A');
    $b = gridAd($issue, AdFormat::UnQuartoPagina, 'B');
    ContentPlacer::place($a, $page);
    ContentPlacer::place($b, $page);

    $component = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('moveContentOnGrid', $page->id, $a->id, 'right');

    expect(cellsOf($page, $a))->toBe('3×3 in (3,0)')
        ->and(cellsOf($page, $b))->toBe('3×3 in (0,0)');

    // Fuori pagina: errore, niente scritto.
    $component->call('moveContentOnGrid', $page->id, $a->id, 'right')->assertHasErrors('grid');
    expect(cellsOf($page, $a))->toBe('3×3 in (3,0)');
});

test('the menu shows the four arrows enabled only where the move is possible, and the rotation', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $content = gridAd($issue, AdFormat::MezzaPaginaOrizzontale, 'Mezza');
    ContentPlacer::place($content, $page);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    // 6×3 in alto: giù sì, su/sinistra/destra no; si può ruotare.
    expect($html)->toMatch('/moveContentOnGrid\('.$page->id.', '.$content->id.", 'up'\)\"\s+disabled/")
        ->and($html)->toMatch('/moveContentOnGrid\('.$page->id.', '.$content->id.", 'left'\)\"\s+disabled/")
        ->and($html)->not->toMatch('/moveContentOnGrid\('.$page->id.', '.$content->id.", 'down'\)\"\s+disabled/")
        ->and($html)->toContain('data-testid="rotate-content"')
        ->and($html)->not->toMatch('/rotateContentOnGrid\('.$page->id.', '.$content->id.'\)"\s+disabled/');
});

test('rotating an ad changes its format to match the new shape', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $content = gridAd($issue, AdFormat::MezzaPaginaOrizzontale, 'Mezza');
    ContentPlacer::place($content, $page);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('rotateContentOnGrid', $page->id, $content->id)
        ->assertHasNoErrors();

    expect(cellsOf($page, $content))->toBe('3×6 in (0,0)')
        ->and($content->advertisement->fresh()->format)->toBe(AdFormat::MezzaPaginaVerticale)
        ->and(ActivityLog::where('action', 'page.content_rotated')->first()->description)->toContain('Mezza pagina verticale');
});

test('rotating a 2/3 or an article changes only the shape; a square does not rotate', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $twoThirds = gridAd($issue, AdFormat::DueTerziPagina, 'Due terzi');
    ContentPlacer::place($twoThirds, $page);
    $vetrina = gridAd($issue, AdFormat::UnQuartoPagina, 'Vetrina');
    $page2 = $issue->pages()->where('position', 2)->first();
    ContentPlacer::place($vetrina, $page2);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('rotateContentOnGrid', $page->id, $twoThirds->id)
        ->assertHasNoErrors()
        ->call('rotateContentOnGrid', $page2->id, $vetrina->id)
        ->assertHasErrors('grid');

    expect(cellsOf($page, $twoThirds))->toBe('6×4 in (0,0)')
        ->and($twoThirds->advertisement->fresh()->format)->toBe(AdFormat::DueTerziPagina);
});

test('a 3/4 next to a 1/4 is an L shape without arrows', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $vetrina = gridAd($issue, AdFormat::UnQuartoPagina, 'Vetrina');
    $article = Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Tre quarti', 'occupancy_twelfths' => 9]);
    ContentPlacer::place($vetrina, $page);
    ContentPlacer::place($article, $page);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    expect(cellsOf($page, $article))->toBeNull()
        ->and(substr_count($html, 'data-testid="layout-piece"'))->toBe(1)
        ->and($html)->toContain('Forma a L');
});

test('changing the space of a content on the page keeps its corner when it fits', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $article = Content::factory()->article()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => 3]);
    ContentPlacer::place($article, $page);
    DB::table('page_content')->where('content_id', $article->id)->update(['grid_x' => 3, 'grid_y' => 3]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('updateContentOccupancy', $page->id, $article->id, 1);

    expect(cellsOf($page, $article))->toBe('3×1 in (3,3)');
});

test('the layout of a locked page cannot be changed and a read-only user gets 403', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $vetrina = gridAd($issue, AdFormat::UnQuartoPagina, 'Vetrina');
    ContentPlacer::place($vetrina, $page);

    $viewer = User::factory()->create(['role' => 'sola_lettura']);
    $viewer->magazines()->attach($issue->magazine_id);
    Livewire::actingAs($viewer)->test(Grid::class, ['issue' => $issue])
        ->call('moveContentOnGrid', $page->id, $vetrina->id, 'right')
        ->assertForbidden();

    $page->update(['locked_at' => now()]);
    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('moveContentOnGrid', $page->id, $vetrina->id, 'right')
        ->assertHasErrors('locked');

    expect(cellsOf($page, $vetrina))->toBe('3×3 in (0,0)');
});

test('contents attached without a rectangle are still drawn on the grid (deterministic placement)', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => 6]);
    $page->contents()->attach($content->id, ['occupied_twelfths' => 6]);

    $layout = $page->fresh()->load('contents.advertisement')->layout();

    expect($layout['blocks'][0]['cells']->label())->toBe('6×3 in (0,0)')
        ->and($layout['free'][0]['h'])->toBe(50.0);

    PageGridPositions::persist($page);
    expect(cellsOf($page, $content))->toBe('6×3 in (0,0)');
});

test('changing the format from horizontal to vertical in the edit modal turns the shape on the page', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $content = gridAd($issue, AdFormat::MezzaPaginaOrizzontale, 'Mezza');
    ContentPlacer::place($content, $page);

    Livewire::actingAs(editorFor($issue))->test(\App\Livewire\Timone\ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('format', AdFormat::MezzaPaginaVerticale->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(cellsOf($page, $content))->toBe('3×6 in (0,0)');
});
