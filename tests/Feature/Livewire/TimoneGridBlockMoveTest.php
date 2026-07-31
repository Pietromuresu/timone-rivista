<?php

use App\Enums\UserRole;
use App\Events\PagesBlockMoved;
use App\Livewire\Timone\Grid;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// (issue con total_pages = 6).

function selectPages($component, array $pageIds)
{
    $component->call('toggleSelectionMode');
    foreach ($pageIds as $id) {
        $component->call('togglePageSelection', $id);
    }

    return $component;
}

test('dragging a contiguous selection moves it as a single block, preserving internal order', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();
    // Seleziona le pagine in posizione 2 e 3 (contigue), le trascina a destinazione 6.
    $selectedIds = [$pages[1]->id, $pages[2]->id];

    $component = selectPages(Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]), $selectedIds);

    $component->call('moveSelectedBlock', $pages[1]->id, 6)
        ->assertHasNoErrors();

    $finalOrder = $issue->fresh()->pages()->orderBy('position')->pluck('id')->all();

    // Le due pagine spostate restano adiacenti tra loro, nello stesso
    // ordine relativo originale (pages[1] prima di pages[2]).
    $indexOfFirst = array_search($pages[1]->id, $finalOrder, true);
    $indexOfSecond = array_search($pages[2]->id, $finalOrder, true);

    expect($indexOfSecond)->toBe($indexOfFirst + 1)
        ->and($finalOrder)->toHaveCount(6);

    Event::assertDispatched(PagesBlockMoved::class, fn (PagesBlockMoved $e) => $e->issueId === $issue->id
        && count($e->pageIds) === 2
        && in_array($pages[1]->id, $e->pageIds, true)
        && in_array($pages[2]->id, $e->pageIds, true));
});

test('dragging a non-contiguous selection compacts it into a single block at the destination', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get(); // posizioni 1..6

    // Seleziona posizione 1 e posizione 4 (non contigue).
    $selectedIds = [$pages[0]->id, $pages[3]->id];

    $component = selectPages(Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]), $selectedIds);
    $component->call('moveSelectedBlock', $pages[0]->id, 2)->assertHasNoErrors();

    $finalOrder = $issue->fresh()->pages()->orderBy('position')->pluck('id')->all();

    $indexOfFirst = array_search($pages[0]->id, $finalOrder, true);
    $indexOfSecond = array_search($pages[3]->id, $finalOrder, true);

    // Ora adiacenti (compattate), nell'ordine originale (pages[0] prima di pages[3]).
    expect($indexOfSecond)->toBe($indexOfFirst + 1);
});

test('a block move targeting past the end of the issue is clamped instead of failing', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();
    $selectedIds = [$pages[0]->id, $pages[1]->id];

    $component = selectPages(Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]), $selectedIds);
    $component->call('moveSelectedBlock', $pages[0]->id, 999)->assertHasNoErrors();

    $finalOrder = $issue->fresh()->pages()->orderBy('position')->pluck('id')->all();

    expect(array_slice($finalOrder, -2))->toBe([$pages[0]->id, $pages[1]->id])
        ->and($finalOrder)->toHaveCount(6);
});

test('a block move is rejected entirely if any selected page is locked, nothing is applied', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();
    $pages[1]->update(['locked_at' => now(), 'locked_by' => $user->id]);
    $selectedIds = [$pages[0]->id, $pages[1]->id];

    $originalOrder = $pages->pluck('id')->all();

    $component = selectPages(Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]), $selectedIds);
    $component->call('moveSelectedBlock', $pages[0]->id, 5)
        ->assertHasErrors('locked');

    $finalOrder = $issue->fresh()->pages()->orderBy('position')->pluck('id')->all();

    expect($finalOrder)->toBe($originalOrder);

    Event::assertNotDispatched(PagesBlockMoved::class);
});

test('the whole selected block is broadcast as a single atomic event, not one per page', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();
    $selectedIds = [$pages[0]->id, $pages[1]->id, $pages[2]->id];

    $component = selectPages(Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]), $selectedIds);
    $component->call('moveSelectedBlock', $pages[0]->id, 6);

    Event::assertDispatchedTimes(PagesBlockMoved::class, 1);
});

test('a successful block move is recorded once in the activity log', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();
    $selectedIds = [$pages[0]->id, $pages[1]->id];

    $component = selectPages(Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]), $selectedIds);
    $component->call('moveSelectedBlock', $pages[0]->id, 5);

    expect(ActivityLog::where('issue_id', $issue->id)->where('action', 'page.block_moved')->count())->toBe(1);
});

test('a block move rejected by the optimistic lock changes nothing and realigns the client version', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();
    $selectedIds = [$pages[0]->id, $pages[1]->id];
    $originalOrder = $pages->pluck('id')->all();

    $component = selectPages(Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]), $selectedIds);

    // Un altro utente avanza la versione nel frattempo.
    $issue->increment('reorder_version');

    $component->call('moveSelectedBlock', $pages[0]->id, 5)
        ->assertHasErrors('reorder');

    expect($issue->fresh()->pages()->orderBy('position')->pluck('id')->all())->toBe($originalOrder);
});

test('dragging a page that is not part of the current selection falls back to a normal single-page move', function () {
    Event::fake([PagesBlockMoved::class, \App\Events\PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();
    // Seleziona pages[0] e pages[1], ma trascina pages[3] (non selezionata).
    $selectedIds = [$pages[0]->id, $pages[1]->id];

    $component = selectPages(Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]), $selectedIds);
    $component->call('moveSelectedBlock', $pages[3]->id, 1);

    Event::assertNotDispatched(PagesBlockMoved::class);
    Event::assertDispatched(\App\Events\PageMoved::class);
});

test('calling moveSelectedBlock with selection mode off falls back to a normal single-page move', function () {
    Event::fake([PagesBlockMoved::class, \App\Events\PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('moveSelectedBlock', $page->id, 3);

    Event::assertNotDispatched(PagesBlockMoved::class);
    Event::assertDispatched(\App\Events\PageMoved::class);
});

test('a guest cannot move a selected block', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('position')->get();
    $originalOrder = $pages->pluck('id')->all();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->set('selectionMode', true)
        ->set('selectedPageIds', [$pages[0]->id, $pages[1]->id])
        ->call('moveSelectedBlock', $pages[0]->id, 5);

    expect($issue->fresh()->pages()->orderBy('position')->pluck('id')->all())->toBe($originalOrder);
});

test('a sola lettura user cannot move a selected block', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $pages = $issue->pages()->orderBy('position')->get();
    $originalOrder = $pages->pluck('id')->all();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('selectionMode', true)
        ->set('selectedPageIds', [$pages[0]->id, $pages[1]->id])
        ->call('moveSelectedBlock', $pages[0]->id, 5);

    expect($issue->fresh()->pages()->orderBy('position')->pluck('id')->all())->toBe($originalOrder);
});

test('a page id from a different issue injected into the selection is ignored', function () {
    Event::fake([PagesBlockMoved::class, \App\Events\PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $otherIssue = reorderableIssue();
    $otherPage = $otherIssue->pages()->first();
    $pages = $issue->pages()->orderBy('position')->get();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('selectionMode', true)
        ->set('selectedPageIds', [$pages[0]->id, $otherPage->id])
        ->call('moveSelectedBlock', $pages[0]->id, 3);

    // Solo pages[0] appartiene davvero alla selezione filtrata per questa
    // issue: con un solo id valido, degrada a movePage() normale — l'altra
    // issue non viene toccata.
    expect($otherPage->fresh()->position)->toBe(1);
});
