<?php

use App\Enums\PageStatus;
use App\Enums\UserRole;
use App\Events\PageLocked;
use App\Events\PageStatusUpdated;
use App\Events\PageUnlocked;
use App\Livewire\Timone\Grid;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php). reorderableIssue()
// crea un'issue con total_pages = 6.

test('toggling selection mode clears any pending selection', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $page->id)
        ->assertSet('selectedPageIds', [$page->id])
        ->call('toggleSelectionMode')
        ->assertSet('selectionMode', false)
        ->assertSet('selectedPageIds', []);
});

test('selecting a page while selection mode is off does nothing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageSelection', $page->id)
        ->assertSet('selectedPageIds', []);
});

test('clicking a selected page again deselects it', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $page->id)
        ->call('togglePageSelection', $page->id)
        ->assertSet('selectedPageIds', []);
});

test('selectAllPages selects every page of the issue', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('selectAllPages')
        ->assertCount('selectedPageIds', 6);
});

test('bulk changing status applies the new status to every selected unlocked page', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageA = $issue->pages()->where('position', 1)->first();
    $pageB = $issue->pages()->where('position', 2)->first();
    $pageC = $issue->pages()->where('position', 3)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pageA->id)
        ->call('togglePageSelection', $pageB->id)
        ->call('bulkChangeStatus', PageStatus::Revisionata->value)
        ->assertSet('bulkResultMessage', '2 pagine aggiornate a «Revisionata».');

    expect($pageA->fresh()->status)->toBe(PageStatus::Revisionata)
        ->and($pageB->fresh()->status)->toBe(PageStatus::Revisionata)
        ->and($pageC->fresh()->status)->toBe(PageStatus::DaAssegnare);

    Event::assertDispatchedTimes(PageStatusUpdated::class, 2);

    $this->assertDatabaseHas('activity_logs', [
        'issue_id' => $issue->id,
        'entity_id' => $pageA->id,
        'action' => 'page.status_changed',
    ]);
});

test('bulk changing status skips locked pages and reports how many were skipped', function () {
    Event::fake([PageStatusUpdated::class, PageLocked::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageA = $issue->pages()->where('position', 1)->first();
    $lockedPage = $issue->pages()->where('position', 2)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $lockedPage->id)
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pageA->id)
        ->call('togglePageSelection', $lockedPage->id);

    $component->call('bulkChangeStatus', PageStatus::Revisionata->value)
        ->assertSet('bulkResultMessage', '1 pagina aggiornata a «Revisionata». 1 pagina bloccata ignorata.');

    expect($pageA->fresh()->status)->toBe(PageStatus::Revisionata)
        ->and($lockedPage->fresh()->status)->toBe(PageStatus::DaAssegnare);

    Event::assertDispatchedTimes(PageStatusUpdated::class, 1);
});

test('an empty selection does nothing when a bulk status change is requested', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('bulkChangeStatus', PageStatus::Revisionata->value)
        ->assertSet('bulkResultMessage', null);

    Event::assertNotDispatched(PageStatusUpdated::class);
});

test('bulk locking applies to every selected unlocked page and skips already-locked ones', function () {
    Event::fake([PageLocked::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageA = $issue->pages()->where('position', 1)->first();
    $pageB = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $pageB->id)
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pageA->id)
        ->call('togglePageSelection', $pageB->id)
        ->call('bulkToggleLock', true)
        ->assertSet('bulkResultMessage', '1 pagina bloccata.');

    expect($pageA->fresh()->isLocked())->toBeTrue()
        ->and($pageB->fresh()->isLocked())->toBeTrue();

    Event::assertDispatchedTimes(PageLocked::class, 2);
});

test('bulk unlocking releases every selected locked page', function () {
    Event::fake([PageLocked::class, PageUnlocked::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageA = $issue->pages()->where('position', 1)->first();
    $pageB = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $pageA->id)
        ->call('togglePageLock', $pageB->id)
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pageA->id)
        ->call('togglePageSelection', $pageB->id)
        ->call('bulkToggleLock', false)
        ->assertSet('bulkResultMessage', '2 pagine sbloccate.');

    expect($pageA->fresh()->isLocked())->toBeFalse()
        ->and($pageB->fresh()->isLocked())->toBeFalse();

    Event::assertDispatchedTimes(PageUnlocked::class, 2);
});

test('a page belonging to a different issue is ignored by bulk actions even if injected into the selection', function () {
    Event::fake([PageStatusUpdated::class, PageLocked::class]);

    $issue = reorderableIssue();
    $otherIssue = reorderableIssue();
    $user = editorFor($issue);
    $otherPage = $otherIssue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->set('selectedPageIds', [$otherPage->id])
        ->call('bulkChangeStatus', PageStatus::Revisionata->value)
        ->call('bulkToggleLock', true);

    expect($otherPage->fresh()->status)->toBe(PageStatus::DaAssegnare)
        ->and($otherPage->fresh()->isLocked())->toBeFalse();

    Event::assertNotDispatched(PageStatusUpdated::class);
    Event::assertNotDispatched(PageLocked::class);
});

test('a guest cannot perform bulk actions', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->set('selectedPageIds', [$page->id])
        ->call('bulkChangeStatus', PageStatus::Revisionata->value);

    expect($page->fresh()->status)->toBe(PageStatus::DaAssegnare);
});

test('a sola lettura user cannot perform bulk actions', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('selectedPageIds', [$page->id])
        ->call('bulkChangeStatus', PageStatus::Revisionata->value);

    expect($page->fresh()->status)->toBe(PageStatus::DaAssegnare);
});
