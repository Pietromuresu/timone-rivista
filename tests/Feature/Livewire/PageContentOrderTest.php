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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Fase 7b (2026-10-27): ordine dei contenuti nella pagina (frecce sul blocco).
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class, PageContentsReordered::class]));

function orderedPage(Issue $issue): array
{
    $page = $issue->pages()->where('position', 1)->first();
    $a = Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Primo', 'occupancy_twelfths' => 4]);
    $b = Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Secondo', 'occupancy_twelfths' => 4]);
    $c = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'title' => 'Terzo', 'occupancy_twelfths' => 4]);
    Advertisement::factory()->create(['content_id' => $c->id, 'format' => AdFormat::UnTerzoPaginaOrizzontale, 'client' => 'Terzo']);

    foreach ([$a, $b, $c] as $content) {
        ContentPlacer::place($content, $page);
    }

    return [$page, $a, $b, $c];
}

function orderOf(Page $page): array
{
    return $page->contents()->get()->pluck('id')->all();
}

test('new contents go to the end of the page order', function () {
    [$page, $a, $b, $c] = orderedPage(reorderableIssue());

    expect(orderOf($page))->toBe([$a->id, $b->id, $c->id])
        ->and($page->contents()->get()->map(fn ($x) => $x->pivot->position)->all())->toBe([1, 2, 3]);
});

test('moving a content changes the order, the layout and tells the other users', function () {
    $issue = reorderableIssue();
    [$page, $a, $b, $c] = orderedPage($issue);

    $component = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('moveContentInPage', $page->id, $c->id, -1)
        ->assertHasNoErrors();

    expect(orderOf($page))->toBe([$a->id, $c->id, $b->id]);

    // Il blocco spostato è ora la seconda riga dall'alto (1/3 → top 33.3333%).
    expect($component->html())->toMatch('/style="left: 0%; top: 33.3333%; width: 100%; height: 33.3333%;"\s+title="[^"]*"\s+data-testid="layout-block"\s+data-content-id="'.$c->id.'"/');

    Event::assertDispatched(PageContentsReordered::class, fn ($e) => $e->pageId === $page->id && $e->issueId === $issue->id);
    expect(ActivityLog::where('action', 'page.contents_reordered')->first()->description)->toContain('«Terzo» spostato prima');
});

test('moving past the edge changes nothing', function () {
    $issue = reorderableIssue();
    [$page, $a, $b, $c] = orderedPage($issue);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('moveContentInPage', $page->id, $a->id, -1);

    expect(orderOf($page))->toBe([$a->id, $b->id, $c->id]);
    Event::assertNotDispatched(PageContentsReordered::class);
});

test('contents without a stored position (attached before 7b) go last, and a move numbers them all', function () {
    $issue = reorderableIssue();
    [$page, $a, $b, $c] = orderedPage($issue);
    DB::table('page_content')->where('page_id', $page->id)->where('content_id', $a->id)->update(['position' => null]);

    expect(orderOf($page))->toBe([$b->id, $c->id, $a->id]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('moveContentInPage', $page->id, $a->id, -1);

    expect(orderOf($page))->toBe([$b->id, $a->id, $c->id])
        ->and($page->contents()->get()->map(fn ($x) => $x->pivot->position)->all())->toBe([1, 2, 3]);
});

test('the order of a locked page cannot be changed', function () {
    $issue = reorderableIssue();
    [$page, $a, $b, $c] = orderedPage($issue);
    $page->update(['locked_at' => now()]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('moveContentInPage', $page->id, $b->id, -1)
        ->assertHasErrors('locked');

    expect(orderOf($page))->toBe([$a->id, $b->id, $c->id]);
});

test('a read-only user cannot change the order', function () {
    $issue = reorderableIssue();
    [$page, $a, $b] = orderedPage($issue);
    $viewer = User::factory()->create(['role' => 'sola_lettura']);
    $viewer->magazines()->attach($issue->magazine_id);

    Livewire::actingAs($viewer)->test(Grid::class, ['issue' => $issue])
        ->call('moveContentInPage', $page->id, $b->id, -1)
        ->assertForbidden();
});

test('arrows appear only with more than one content, disabled at the edges', function () {
    $issue = reorderableIssue();
    [$page, $a] = orderedPage($issue);
    $single = Content::factory()->article()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => 12]);
    ContentPlacer::place($single, $issue->pages()->where('position', 2)->first());

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    expect(substr_count($html, 'data-testid="move-content-earlier"'))->toBe(3)
        ->and($html)->toContain("\$wire.moveContentInPage({$page->id}, {$a->id}, -1)\"\n                disabled");
});
