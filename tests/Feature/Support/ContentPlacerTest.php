<?php

use App\Enums\ContentType;
use App\Events\ContentAssigned;
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

beforeEach(fn () => Event::fake([ContentAssigned::class, \App\Events\PageGroupsChanged::class]));

function articleWithOccupancy($issue, string $occupancy): Content
{
    return Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Articolo, 'page_occupancy' => $occupancy]);
}

test('a half-page article takes 50% of the page, so two of them share one page', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    expect(ContentPlacer::place(articleWithOccupancy($issue, '0.50'), $page))->toBeArray()
        ->and(ContentPlacer::place(articleWithOccupancy($issue, '0.50'), $page))->toBeArray();

    expect($page->contents()->pluck('occupied_percentage')->map(fn ($p) => (float) $p)->all())->toBe([50.0, 50.0]);
});

test('a 2-page article without file occupies 2 consecutive full pages and links them', function () {
    $issue = reorderableIssue();
    $start = $issue->pages()->where('position', 3)->first();

    $result = ContentPlacer::place($content = articleWithOccupancy($issue, '2'), $start);

    expect($result['pages']->pluck('position')->all())->toBe([3, 4])
        ->and($result['group'])->toBeInstanceOf(PageGroup::class)
        ->and($content->pages()->pluck('occupied_percentage')->map(fn ($p) => (float) $p)->all())->toBe([100.0, 100.0]);
});

test('placement is refused, with nothing written, when the issue ends before the content does', function () {
    $issue = reorderableIssue();
    $start = $issue->pages()->where('position', 5)->first();

    $result = ContentPlacer::place($content = articleWithOccupancy($issue, '3'), $start);

    expect($result)->toBeString()->toContain('occupa 3 pagine consecutive')
        ->and($content->pages()->count())->toBe(0);
});

test('placement is all-or-nothing: a full page in the middle of the range blocks the whole drop', function () {
    $issue = reorderableIssue();
    $start = $issue->pages()->where('position', 1)->first();
    $issue->pages()->where('position', 2)->first()->contents()->attach(articleWithOccupancy($issue, '1')->id, ['occupied_percentage' => 100]);

    $result = ContentPlacer::place($content = articleWithOccupancy($issue, '3'), $start);

    expect($result)->toBeString()->toContain('pagina 2')
        ->and($content->pages()->count())->toBe(0)
        ->and(PageGroup::count())->toBe(0);
});

test('a content without occupancy (legacy row) is never silently placed on one page', function () {
    $issue = reorderableIssue();
    $content = articleWithOccupancy($issue, '1');
    $content->page_occupancy = null; // come un record pre-esistente non ancora valorizzato

    $result = ContentPlacer::place($content, $issue->pages()->first());

    expect($result)->toBeString()->toContain("non ha un'occupazione")
        ->and($content->pages()->count())->toBe(0);
});

test('dropping on the grid surfaces the refusal as an error and assigns nothing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = articleWithOccupancy($issue, '3');

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

    expect($content->page_occupancy)->toBe('2.00');
});

test('the database itself refuses a content without occupancy (NOT NULL)', function () {
    $issue = reorderableIssue();

    expect(fn () => Content::factory()->create(['issue_id' => $issue->id, 'page_occupancy' => null]))
        ->toThrow(QueryException::class);
});

test('the missing-occupancy report says there is nothing to fix on a clean database', function () {
    $this->artisan('timone:missing-occupancy')
        ->expectsOutputToContain('Nessun contenuto senza occupazione')
        ->assertSuccessful();
});
