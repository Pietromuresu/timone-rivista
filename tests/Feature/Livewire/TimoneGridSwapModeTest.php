<?php

use App\Events\PagesSwapped;
use App\Livewire\Timone\Grid;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php). reorderableIssue()
// crea un'issue con total_pages = 6.

test('toggling swap mode flips the flag and clears any pending selection', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSet('swapMode', false)
        ->call('toggleSwapMode')
        ->assertSet('swapMode', true)
        ->assertSet('swapSelectedPageId', null)
        ->call('toggleSwapMode')
        ->assertSet('swapMode', false);
});

test('selecting a page while swap mode is off does nothing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('selectForSwap', $page->id)
        ->assertSet('swapSelectedPageId', null);
});

test('a first click selects a page without changing any position', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSwapMode')
        ->call('selectForSwap', $page->id)
        ->assertSet('swapSelectedPageId', $page->id);

    expect($issue->fresh()->pages()->pluck('position', 'id')->all())
        ->toBe($issue->pages()->pluck('position', 'id')->all());
});

test('clicking the same page twice cancels the selection', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSwapMode')
        ->call('selectForSwap', $page->id)
        ->call('selectForSwap', $page->id)
        ->assertSet('swapSelectedPageId', null);
});

test('clicking a second different page swaps their positions and clears the selection', function () {
    Event::fake([PagesSwapped::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageA = $issue->pages()->where('position', 2)->first();
    $pageB = $issue->pages()->where('position', 5)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSwapMode')
        ->call('selectForSwap', $pageA->id)
        ->call('selectForSwap', $pageB->id)
        ->assertSet('swapSelectedPageId', null);

    expect($pageA->fresh()->position)->toBe(5)
        ->and($pageB->fresh()->position)->toBe(2);

    $positions = $issue->pages()->pluck('position')->all();
    expect($positions)->toHaveCount(6)
        ->and(collect($positions)->unique()->count())->toBe(6);

    Event::assertDispatched(PagesSwapped::class, fn ($event) => $event->pageIdA === $pageA->id
        && $event->pageIdB === $pageB->id);
});

test('swapping bumps the reorder version and logs an activity entry', function () {
    Event::fake([PagesSwapped::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageA = $issue->pages()->where('position', 1)->first();
    $pageB = $issue->pages()->where('position', 3)->first();

    expect($issue->fresh()->reorder_version)->toBe(0);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSwapMode')
        ->call('selectForSwap', $pageA->id)
        ->call('selectForSwap', $pageB->id);

    expect($issue->fresh()->reorder_version)->toBe(1);

    $this->assertDatabaseHas('activity_logs', [
        'issue_id' => $issue->id,
        'action' => 'page.swapped',
    ]);
});

test('a swap based on a stale reorder version is rejected without touching positions', function () {
    Event::fake([PagesSwapped::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageA = $issue->pages()->where('position', 2)->first();
    $pageB = $issue->pages()->where('position', 4)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSwapMode');

    // Simula un altro utente che ha già riordinato nel frattempo, stesso
    // scenario già coperto per movePage() in TimoneGridReorderLockTest.php.
    $issue->increment('reorder_version');

    $component->set('reorderVersion', 0)
        ->call('selectForSwap', $pageA->id)
        ->call('selectForSwap', $pageB->id)
        ->assertHasErrors('reorder');

    expect($pageA->fresh()->position)->toBe(2)
        ->and($pageB->fresh()->position)->toBe(4);

    Event::assertNotDispatched(PagesSwapped::class);
});

test('a guest cannot select a page for swap', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('selectForSwap', $page->id);

    expect($page->fresh()->position)->toBe(1);
});

test('a read-only user cannot select a page for swap', function () {
    $issue = reorderableIssue();
    $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('selectForSwap', $page->id);

    expect($page->fresh()->position)->toBe(1);
    expect(ActivityLog::where('issue_id', $issue->id)->count())->toBe(0);
});
