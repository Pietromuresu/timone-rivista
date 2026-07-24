<?php

use App\Enums\UserRole;
use App\Events\PageMoved;
use App\Livewire\Timone\Grid;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\PageReorderLog;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function reorderableIssue(): Issue
{
    $magazine = Magazine::factory()->create();

    return Issue::factory()->create(['magazine_id' => $magazine->id, 'total_pages' => 6]);
}

function editorFor(Issue $issue): User
{
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($issue->magazine);

    return $user;
}

test('an editor with access can move a page and the final positions have no duplicates', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 5);

    $positions = $issue->pages()->pluck('position')->all();

    expect($positions)->toHaveCount(6)
        ->and(array_unique($positions))->toHaveCount(6)
        ->and($page->fresh()->position)->toBe(5);
});

test('moving a page writes exactly one reorder log entry for the moved page', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 5);

    expect(PageReorderLog::count())->toBe(1);

    $log = PageReorderLog::first();
    expect($log->page_id)->toBe($page->id)
        ->and($log->issue_id)->toBe($issue->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->from_position)->toBe(2)
        ->and($log->to_position)->toBe(5);
});

test('an admin can move a page on any issue', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($admin)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 3);

    expect($page->fresh()->position)->toBe(3);
});

test('a redattore without access to the magazine cannot move a page', function () {
    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($outsider)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 3);

    expect($page->fresh()->position)->toBe(1)
        ->and(PageReorderLog::count())->toBe(0);
});

test('a sola lettura user cannot move a page', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 3);

    expect($page->fresh()->position)->toBe(1)
        ->and(PageReorderLog::count())->toBe(0);
});

test('a guest cannot move a page', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 3);

    expect($page->fresh()->position)->toBe(1)
        ->and(PageReorderLog::count())->toBe(0);
});

test('moving a page dispatches a PageMoved broadcast event', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 5);

    Event::assertDispatched(PageMoved::class, function (PageMoved $event) use ($issue, $page) {
        return $event->issueId === $issue->id
            && $event->pageId === $page->id
            && $event->fromPosition === 2
            && $event->toPosition === 5;
    });
});

test('moving a page to its current position is a no-op with no log and no event', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 2);

    expect($page->fresh()->position)->toBe(2)
        ->and(PageReorderLog::count())->toBe(0);

    Event::assertNotDispatched(PageMoved::class);
});
