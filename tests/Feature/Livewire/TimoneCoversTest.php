<?php

use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\CoverSlot;
use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\CoversChanged;
use App\Events\IssuePageCountUpdated;
use App\Events\PageMoved;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Magazine;
use App\Support\ContentPlacer;
use App\Support\IssueCovers;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/*
 * Fase 2 (2026-10-26): copertine separate dalle pagine interne.
 * reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php
 * (6 pagine interne), weightEditorFor() in IssueWeightTest.php.
 */

beforeEach(fn () => Event::fake([CoversChanged::class, PageMoved::class, ContentAssigned::class]));

function coverOf(Issue $issue, CoverSlot $slot)
{
    return $issue->covers()->where('cover_slot', $slot->value)->firstOrFail();
}

// Fase 3: $pages = pagine intere (occupazione in dodicesimi = 12 × pagine).
function adWithOccupancy(Issue $issue, string $pages): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita, 'occupancy_twelfths' => 12 * (int) $pages]);
    Advertisement::factory()->create(['content_id' => $content->id, 'format' => AdFormat::PaginaIntera]);

    return $content->fresh();
}

test('a new issue always gets the four covers I-IV and internal pages from 1, covers not counted', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('title', 'Numero con copertine')
        ->set('total_pages', 8)
        ->call('save')
        ->assertHasNoErrors();

    $issue = Issue::where('title', 'Numero con copertine')->firstOrFail();

    expect($issue->total_pages)->toBe(8)
        ->and($issue->pages()->pluck('position')->all())->toBe(range(1, 8))
        ->and($issue->covers()->pluck('cover_slot')->map->value->sort()->values()->all())->toBe(['I', 'II', 'III', 'IV'])
        ->and($issue->covers()->whereNotNull('position')->count())->toBe(0)
        ->and($issue->allPages()->count())->toBe(12)
        ->and($issue->pagine_copertina)->toBe(4);
});

test('a magazine with 6 cover pages creates the issue with the bandella', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create(['pagine_copertina' => 6]);

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('title', 'Numero con bandella')
        ->set('total_pages', 8)
        ->call('save')
        ->assertHasNoErrors();

    $issue = Issue::where('title', 'Numero con bandella')->firstOrFail();

    expect($issue->covers()->where('cover_slot', 'bandella')->exists())->toBeTrue()
        ->and($issue->coverPageCount())->toBe(6)
        ->and($issue->weightInput()->pagineCopertina)->toBe(6)
        ->and($issue->weightInput()->pagineInterno)->toBe(8);
});

test('a cover cannot be moved among the internal pages', function () {
    $issue = reorderableIssue();
    $cover = coverOf($issue, CoverSlot::II);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $cover->id, 3)
        ->assertHasErrors('reorder')
        ->call('nudgePage', $cover->id, 1);

    expect($cover->fresh()->position)->toBeNull()
        ->and($issue->pages()->pluck('position')->all())->toBe(range(1, 6));
    Event::assertNotDispatched(PageMoved::class);
});

test('two covers can be swapped and their contents follow them', function () {
    $issue = reorderableIssue();
    $ii = coverOf($issue, CoverSlot::II);
    $iii = coverOf($issue, CoverSlot::III);
    $ad = adWithOccupancy($issue, '1');
    $ii->contents()->attach($ad->id, ['occupied_twelfths' => 12]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('swapCovers', $ii->id, $iii->id)
        ->assertHasNoErrors();

    expect($ii->fresh()->cover_slot)->toBe(CoverSlot::III)
        ->and($iii->fresh()->cover_slot)->toBe(CoverSlot::II)
        ->and(coverOf($issue, CoverSlot::III)->contents->pluck('id')->all())->toBe([$ad->id]);
    Event::assertDispatched(CoversChanged::class, fn ($e) => $e->action === 'swapped');
});

test('a locked cover cannot be swapped', function () {
    $issue = reorderableIssue();
    $ii = coverOf($issue, CoverSlot::II);
    $ii->update(['locked_at' => now()]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('swapCovers', $ii->id, coverOf($issue, CoverSlot::III)->id)
        ->assertHasErrors('covers');

    expect($ii->fresh()->cover_slot)->toBe(CoverSlot::II);
});

test('covers stay out of the multi-selection and of "select all"', function () {
    $issue = reorderableIssue();

    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', coverOf($issue, CoverSlot::I)->id)
        ->assertSet('selectedPageIds', [])
        ->call('selectAllPages');

    expect($grid->get('selectedPageIds'))->toHaveCount(6);
});

test('a content can be assigned to a cover like to a page', function () {
    $issue = reorderableIssue();
    $iv = coverOf($issue, CoverSlot::IV);
    $ad = adWithOccupancy($issue, '1');

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $ad->id, $iv->id)
        ->assertHasNoErrors();

    expect($iv->contents()->pluck('contents.id')->all())->toBe([$ad->id]);
});

test('a 2-page ad dropped on the II cover continues on page 1', function () {
    $issue = reorderableIssue();
    $ad = adWithOccupancy($issue, '2');

    $result = ContentPlacer::place($ad, coverOf($issue, CoverSlot::II));

    expect($result)->toBeArray()
        ->and($result['pages']->map->label()->all())->toBe(['II di copertina', 'pagina 1'])
        ->and($result['group'])->toBeNull();
});

test('a 2-page ad dropped on the III cover takes the last internal page and the III', function () {
    $issue = reorderableIssue();

    $result = ContentPlacer::place(adWithOccupancy($issue, '2'), coverOf($issue, CoverSlot::III));

    expect($result['pages']->map->label()->all())->toBe(['pagina 6', 'III di copertina']);
});

test('a multi-page ad cannot start on the I or IV cover', function () {
    $issue = reorderableIssue();

    expect(ContentPlacer::place(adWithOccupancy($issue, '2'), coverOf($issue, CoverSlot::I)))->toBeString()->toContain('ne accoglie una sola')
        ->and(ContentPlacer::place(adWithOccupancy($issue, '2'), coverOf($issue, CoverSlot::IV)))->toBeString();
});

test('the bandella can be added and removed, and the cover pages of the weight follow', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('addBandella')
        ->assertHasNoErrors()
        ->assertSee('Bandella');

    expect($issue->fresh()->pagine_copertina)->toBe(6);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('removeBandella')
        ->assertHasNoErrors();

    expect($issue->fresh()->pagine_copertina)->toBe(4)
        ->and($issue->covers()->count())->toBe(4);
});

test('a bandella with contents cannot be removed', function () {
    $issue = reorderableIssue();
    IssueCovers::addBandella($issue);
    coverOf($issue, CoverSlot::Bandella)->contents()->attach(adWithOccupancy($issue, '1')->id, ['occupied_twelfths' => 12]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('removeBandella')
        ->assertHasErrors('covers');

    expect($issue->covers()->count())->toBe(5);
});

test('resizing the internal pages never touches the covers', function () {
    Event::fake([IssuePageCountUpdated::class]);
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', '10')
        ->call('resizePages');

    expect($issue->fresh()->total_pages)->toBe(10)
        ->and($issue->pages()->pluck('position')->all())->toBe(range(1, 10))
        ->and($issue->covers()->count())->toBe(4);
});

test('cover cards are draggable only onto other covers, never through the internal pages sortable', function (string $viewMode) {
    $issue = reorderableIssue();
    $ii = coverOf($issue, CoverSlot::II);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', $viewMode)
        ->html();

    $start = mb_strpos($html, 'wire:key="page-'.$ii->id.'"');
    $card = mb_substr($html, $start, 3000);

    expect($card)->toContain('text/cover-id')
        ->and($card)->toContain('data-cover-slot="II"')
        ->and($card)->not->toContain('drag-handle')
        ->and($card)->not->toContain('nudgePage');

    // Fuori dal contenitore x-sortable delle pagine interne.
    $sortableStart = mb_strpos($html, 'x-sortable=');
    expect($start)->toBeLessThan($sortableStart);
})->with(['griglia', 'doppia', 'lista']);

test('material uploaded from a cover is assigned to that cover', function () {
    Event::fake([ContentCreated::class, ContentAssigned::class]);
    $issue = reorderableIssue();
    $iv = coverOf($issue, CoverSlot::IV);

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('openForPage', $iv->id)
        ->set('type', 'pubblicita')
        ->set('title', 'Quarta di copertina')
        ->set('client', 'Cliente quarta')
        ->set('format', AdFormat::PaginaIntera->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($iv->contents()->pluck('title')->all())->toBe(['Quarta di copertina']);
});
