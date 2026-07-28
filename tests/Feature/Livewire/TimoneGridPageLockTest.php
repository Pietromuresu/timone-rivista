<?php

use App\Enums\ContentType;
use App\Enums\PageStatus;
use App\Enums\UserRole;
use App\Events\ContentAssigned;
use App\Events\ContentUnassigned;
use App\Events\IssuePageCountUpdated;
use App\Events\PageLocked;
use App\Events\PageMoved;
use App\Events\PagesSwapped;
use App\Events\PageStatusUpdated;
use App\Events\PageUnlocked;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use App\Models\PageFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php). reorderableIssue()
// crea un'issue con total_pages = 6.

test('an editor can lock a page', function () {
    Event::fake([PageLocked::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id);

    $page->refresh();
    expect($page->isLocked())->toBeTrue()
        ->and($page->locked_by)->toBe($user->id);

    $this->assertDatabaseHas('activity_logs', [
        'issue_id' => $issue->id,
        'action' => 'page.locked',
    ]);

    Event::assertDispatched(PageLocked::class, fn (PageLocked $e) => $e->pageId === $page->id
        && $e->lockedByUserId === $user->id);
});

test('toggling a locked page unlocks it again', function () {
    Event::fake([PageLocked::class, PageUnlocked::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id)
        ->call('togglePageLock', $page->id);

    $page->refresh();
    expect($page->isLocked())->toBeFalse()
        ->and($page->locked_by)->toBeNull();

    $this->assertDatabaseHas('activity_logs', [
        'issue_id' => $issue->id,
        'action' => 'page.unlocked',
    ]);

    Event::assertDispatched(PageUnlocked::class, fn (PageUnlocked $e) => $e->pageId === $page->id);
});

test('a guest cannot toggle a page lock', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id);

    expect($page->fresh()->isLocked())->toBeFalse();
});

test('a sola lettura user cannot toggle a page lock', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id);

    expect($page->fresh()->isLocked())->toBeFalse();
});

test('locking a page belonging to a different issue does nothing', function () {
    $issue = reorderableIssue();
    $otherIssue = reorderableIssue();
    $user = editorFor($issue);
    $otherPage = $otherIssue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $otherPage->id);

    expect($otherPage->fresh()->isLocked())->toBeFalse();
});

test('a locked page cannot be moved', function () {
    Event::fake([PageLocked::class, PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id);

    $component->call('movePage', $page->id, 5)
        ->assertHasErrors('locked');

    expect($page->fresh()->position)->toBe(2);
    Event::assertNotDispatched(PageMoved::class);
});

test('a locked page cannot be selected for swap', function () {
    Event::fake([PageLocked::class, PagesSwapped::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $lockedPage = $issue->pages()->where('position', 2)->first();
    $otherPage = $issue->pages()->where('position', 4)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $lockedPage->id)
        ->call('toggleSwapMode');

    $component->call('selectForSwap', $lockedPage->id)
        ->assertHasErrors('locked')
        ->assertSet('swapSelectedPageId', null);

    expect($lockedPage->fresh()->position)->toBe(2)
        ->and($otherPage->fresh()->position)->toBe(4);
    Event::assertNotDispatched(PagesSwapped::class);
});

test('a locked page status cannot be changed', function () {
    Event::fake([PageLocked::class, PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id);

    $component->call('changePageStatus', $page->id, PageStatus::Revisionata->value)
        ->assertHasErrors('locked');

    expect($page->fresh()->status)->toBe(PageStatus::DaAssegnare);
    Event::assertNotDispatched(PageStatusUpdated::class);
});

test('a content cannot be assigned to a locked page', function () {
    Event::fake([PageLocked::class, ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id);

    $component->call('assignContent', $content->id, $page->id)
        ->assertHasErrors('locked');

    expect($page->fresh()->contents()->count())->toBe(0);
    Event::assertNotDispatched(ContentAssigned::class);
});

test('a content percentage cannot be updated on a locked page', function () {
    Event::fake([PageLocked::class, ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($content->id, ['occupied_percentage' => 50]);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id);

    $component->call('updateContentPercentage', $page->id, $content->id, 80)
        ->assertHasErrors('locked');

    expect((float) $page->contents()->where('content_id', $content->id)->first()->pivot->occupied_percentage)->toBe(50.0);
});

test('a content cannot be unassigned from a locked page', function () {
    Event::fake([PageLocked::class, ContentUnassigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'type' => ContentType::Articolo]);
    $page->contents()->attach($content->id, ['occupied_percentage' => 100]);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id);

    $component->call('unassignContent', $content->id, $page->id)
        ->assertHasErrors('locked');

    expect($page->fresh()->contents()->count())->toBe(1);
    Event::assertNotDispatched(ContentUnassigned::class);
});

test('a file cannot be uploaded to a locked page', function () {
    Event::fake([PageLocked::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $page->id);

    $component->set("pendingUploads.{$page->id}", UploadedFile::fake()->create('sample.pdf', 100, 'application/pdf'))
        ->assertHasErrors('locked');

    expect(PageFile::where('page_id', $page->id)->count())->toBe(0);
});

test('reducing the total page count is rejected while a page in the removed range is locked', function () {
    Event::fake([PageLocked::class, IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $lastPage = $issue->pages()->where('position', 6)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageLock', $lastPage->id);

    $component->set('newTotalPages', 4)
        ->call('resizePages', true)
        ->assertHasErrors('locked');

    expect($issue->fresh()->total_pages)->toBe(6)
        ->and($issue->pages()->count())->toBe(6);
    Event::assertNotDispatched(IssuePageCountUpdated::class);
});
