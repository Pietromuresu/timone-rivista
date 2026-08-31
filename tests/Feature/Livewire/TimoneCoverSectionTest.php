<?php

use App\Enums\PageRole;
use App\Enums\UserRole;
use App\Events\IssuePageCountUpdated;
use App\Events\PageMoved;
use App\Livewire\Timone\CoverSection;
use App\Livewire\Timone\Grid;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui — vedi la nota già presente in TimoneGridPageCountTest.php.
// reorderableIssue() crea un'issue con total_pages = 6.

test('an editor can assign copertina2 to an internal page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 3)->first();

    Livewire::actingAs($user)->test(CoverSection::class, ['issue' => $issue])
        ->call('assignRole', $page->id, 'copertina2');

    expect($page->fresh()->role)->toBe(PageRole::Copertina2);
});

test('assigning copertina1 or copertina4 manually is rejected, those are position-derived', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 3)->first();

    Livewire::actingAs($user)->test(CoverSection::class, ['issue' => $issue])
        ->call('assignRole', $page->id, 'copertina1');

    expect($page->fresh()->role)->toBe(PageRole::Interna);
});

test('a locked page cannot have its role changed', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 3)->first();
    $page->update(['locked_at' => now(), 'locked_by' => $user->id]);

    Livewire::actingAs($user)->test(CoverSection::class, ['issue' => $issue])
        ->call('assignRole', $page->id, 'battente');

    expect($page->fresh()->role)->toBe(PageRole::Interna);
});

test('a role can be removed by re-assigning interna', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 3)->first();
    $page->update(['role' => PageRole::Battente->value]);

    Livewire::actingAs($user)->test(CoverSection::class, ['issue' => $issue])
        ->call('assignRole', $page->id, 'interna');

    expect($page->fresh()->role)->toBe(PageRole::Interna);
});

test('a guest cannot assign a role', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 3)->first();

    Livewire::test(CoverSection::class, ['issue' => $issue])
        ->call('assignRole', $page->id, 'battente');

    expect($page->fresh()->role)->toBe(PageRole::Interna);
});

test('a sola lettura user cannot assign a role', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $page = $issue->pages()->where('position', 3)->first();

    Livewire::actingAs($user)->test(CoverSection::class, ['issue' => $issue])
        ->call('assignRole', $page->id, 'battente');

    expect($page->fresh()->role)->toBe(PageRole::Interna);
});

test('dragging page 1 elsewhere via movePage resyncs copertina1 onto the new first page', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $oldFirst = $issue->pages()->where('position', 1)->first();
    $oldSecond = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $oldFirst->id, 3);

    expect($oldSecond->fresh()->role)->toBe(PageRole::Copertina1)
        ->and($oldFirst->fresh()->role)->toBe(PageRole::Interna);
});

test('increasing total pages in coda keeps copertina1 in place and moves copertina4 to the new last page', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $oldLast = $issue->pages()->where('position', 6)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 8)
        ->call('resizePages');

    $issue->refresh();
    expect($issue->pages()->where('position', 1)->first()->role)->toBe(PageRole::Copertina1)
        ->and($oldLast->fresh()->role)->toBe(PageRole::Interna)
        ->and($issue->pages()->where('position', 8)->first()->role)->toBe(PageRole::Copertina4);
});

test('displayedTotalPages excludes a battente page by default and includes it when the magazine opts in', function () {
    $issue = reorderableIssue();
    $battente = $issue->pages()->where('position', 3)->first();
    $battente->update(['role' => PageRole::Battente->value]);

    expect($issue->fresh()->displayedTotalPages())->toBe(5);

    $issue->magazine->update(['battente_counted_in_total' => true]);

    expect($issue->fresh()->displayedTotalPages())->toBe(6);
});
