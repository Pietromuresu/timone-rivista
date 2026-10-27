<?php

use App\Enums\ContentType;
use App\Events\ContentAssigned;
use App\Events\PageGroupsChanged;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use App\Models\PageGroup;
use App\Support\ContentCreator;
use App\Support\ContentPlacer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// Bug 1 + bug 5 (2026-10-12): posizionamento "tutto o niente" in base
// all'occupazione, vincolo di servizio e vincolo NOT NULL a DB. Nessun PDF
// qui (niente imagick richiesto): la distribuzione dei file per pagina è
// coperta da ContentFreeFormPdfDistributionTest.php.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php (6 pagine).

beforeEach(fn () => Event::fake([ContentAssigned::class, PageGroupsChanged::class]));

// Fase 3 (2026-10-27): occupazione in dodicesimi (12 = pagina intera).
function articleWithOccupancy($issue, int $twelfths): Content
{
    return Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Articolo, 'occupancy_twelfths' => $twelfths]);
}

test('a half-page article takes 6/12 of the page, so two of them share one page', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    expect(ContentPlacer::place(articleWithOccupancy($issue, 6), $page))->toBeArray()
        ->and(ContentPlacer::place(articleWithOccupancy($issue, 6), $page))->toBeArray();

    expect($page->contents()->pluck('occupied_twelfths')->map(fn ($t) => (int) $t)->all())->toBe([6, 6]);
});

// Fase 3: test richiesto dalla specifica — 2 intere + 1/2 distribuite 12/12/6.
test('a 2 and a half page article is placed as 12, 12, 6 on three consecutive pages', function () {
    $issue = reorderableIssue();
    $start = $issue->pages()->where('position', 2)->first();

    $result = ContentPlacer::place($content = articleWithOccupancy($issue, 30), $start);

    expect($result['pages']->pluck('position')->all())->toBe([2, 3, 4])
        ->and($result['twelfths'])->toBe([12, 12, 6])
        ->and($content->pages()->orderBy('pages.position')->pluck('occupied_twelfths')->map(fn ($t) => (int) $t)->all())->toBe([12, 12, 6]);
});

test('a page with half left accepts a 1/2 content but refuses a 2/3 one, saying what is left', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    ContentPlacer::place(articleWithOccupancy($issue, 6), $page);

    $refused = ContentPlacer::place(articleWithOccupancy($issue, 8), $page);

    expect($refused)->toBeString()->toContain('resta libero solo 1/2 di pagina')
        ->and(ContentPlacer::place(articleWithOccupancy($issue, 6), $page))->toBeArray()
        ->and($page->fresh()->occupiedTwelfths())->toBe(12);
});

test('a 2-page article without file occupies 2 consecutive full pages and links them', function () {
    $issue = reorderableIssue();
    $start = $issue->pages()->where('position', 3)->first();

    $result = ContentPlacer::place($content = articleWithOccupancy($issue, 24), $start);

    expect($result['pages']->pluck('position')->all())->toBe([3, 4])
        ->and($result['group'])->toBeInstanceOf(PageGroup::class)
        ->and($content->pages()->pluck('occupied_twelfths')->map(fn ($t) => (int) $t)->all())->toBe([12, 12]);
});

test('placement is refused, with nothing written, when the issue ends before the content does', function () {
    $issue = reorderableIssue();
    $start = $issue->pages()->where('position', 5)->first();

    $result = ContentPlacer::place($content = articleWithOccupancy($issue, 36), $start);

    expect($result)->toBeString()->toContain('occupa 3 pagine consecutive')
        ->and($content->pages()->count())->toBe(0);
});

test('placement is all-or-nothing: a full page in the middle of the range blocks the whole drop', function () {
    $issue = reorderableIssue();
    $start = $issue->pages()->where('position', 1)->first();
    $issue->pages()->where('position', 2)->first()->contents()->attach(articleWithOccupancy($issue, 12)->id, ['occupied_twelfths' => 12]);

    $result = ContentPlacer::place($content = articleWithOccupancy($issue, 36), $start);

    expect($result)->toBeString()->toContain('pagina 2')
        ->and($content->pages()->count())->toBe(0)
        ->and(PageGroup::count())->toBe(0);
});

test('a content without occupancy (legacy row) is never silently placed on one page', function () {
    $issue = reorderableIssue();
    $content = articleWithOccupancy($issue, 12);
    $content->occupancy_twelfths = null; // dato mancante: mai un default silenzioso

    $result = ContentPlacer::place($content, $issue->pages()->first());

    expect($result)->toBeString()->toContain("non ha un'occupazione")
        ->and($content->pages()->count())->toBe(0);
});

test('dropping on the grid surfaces the refusal as an error and assigns nothing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = articleWithOccupancy($issue, 36);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $issue->pages()->where('position', 6)->first()->id)
        ->assertHasErrors('percentage');

    expect($content->pages()->count())->toBe(0);
});

test('the service refuses to create an article without occupancy, whatever the caller validated', function () {
    $issue = reorderableIssue();

    expect(fn () => ContentCreator::create($issue, ['type' => 'articolo', 'title' => 'x', 'editorial_status' => 'da_scrivere'], null, null))
        ->toThrow(ValidationException::class);

    expect(Content::count())->toBe(0);
});

test('an advertisement gets its occupancy from the format', function () {
    $issue = reorderableIssue();

    $content = ContentCreator::create($issue, [
        'type' => 'pubblicita', 'title' => 'Doppia', 'client' => 'Acme', 'format' => 'doppia_pagina', 'confirmation_status' => 'confermata',
    ], null, null);

    expect($content->occupancy_twelfths)->toBe(24);
});

test('an advertisement occupancy proposed by the format can be changed in the form fields', function () {
    $issue = reorderableIssue();

    $content = ContentCreator::create($issue, [
        'type' => 'pubblicita', 'title' => 'Mezza allargata', 'client' => 'Acme', 'format' => 'mezza_pagina_orizzontale', 'confirmation_status' => 'confermata',
        'whole_pages' => 0, 'fraction_twelfths' => '8',
    ], null, null);

    expect($content->occupancy_twelfths)->toBe(8);
});

test('the database itself refuses a content without occupancy (NOT NULL)', function () {
    $issue = reorderableIssue();

    expect(fn () => Content::factory()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => null]))
        ->toThrow(QueryException::class);
});

test('the missing-occupancy report says it is no longer needed once occupancy is in twelfths', function () {
    $this->artisan('timone:missing-occupancy')
        ->expectsOutputToContain('Comando non più necessario')
        ->assertSuccessful();
});
