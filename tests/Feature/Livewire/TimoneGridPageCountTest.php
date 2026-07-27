<?php

use App\Enums\PageContentType;
use App\Events\IssuePageCountUpdated;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php). reorderableIssue()
// crea un'issue con total_pages = 6.

test('increasing the page count in coda appends blank pages at the end', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 8)
        ->call('resizePages');

    expect($issue->fresh()->total_pages)->toBe(8)
        ->and($issue->pages()->count())->toBe(8);

    $newPages = $issue->pages()->whereIn('position', [7, 8])->get();
    expect($newPages)->toHaveCount(2)
        ->and($newPages->pluck('content_type')->unique()->all())->toBe([PageContentType::Bianca]);

    Event::assertDispatched(IssuePageCountUpdated::class, fn (IssuePageCountUpdated $e) => $e->oldTotalPages === 6 && $e->newTotalPages === 8);
});

test('increasing at a specific position shifts existing pages and keeps positions unique', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageAtThree = $issue->pages()->where('position', 3)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 8)
        ->set('insertMode', 'position')
        ->set('insertAtPosition', 3)
        ->call('resizePages');

    $positions = $issue->pages()->pluck('position')->sort()->values()->all();

    expect($positions)->toBe([1, 2, 3, 4, 5, 6, 7, 8])
        ->and($pageAtThree->fresh()->position)->toBe(5); // 3 -> shifted by 2 nuove pagine inserite prima
});

test('an issue with no ads shows a zero percent ad load after increase does not crash the ad dashboard', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 10)
        ->call('resizePages')
        ->assertOk();
});

test('decreasing without confirmation does not change anything', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 3)
        ->call('resizePages'); // confirmed di default è false

    expect($issue->fresh()->total_pages)->toBe(6)
        ->and($issue->pages()->count())->toBe(6);

    Event::assertNotDispatched(IssuePageCountUpdated::class);
});

test('decreasing with confirmation removes trailing pages and keeps the rest untouched', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $keptPage = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 3)
        ->call('resizePages', true);

    expect($issue->fresh()->total_pages)->toBe(3)
        ->and($issue->pages()->count())->toBe(3)
        ->and($issue->pages()->pluck('position')->sort()->values()->all())->toBe([1, 2, 3])
        ->and($keptPage->fresh()->position)->toBe(2);

    Event::assertDispatched(IssuePageCountUpdated::class, fn (IssuePageCountUpdated $e) => $e->oldTotalPages === 6 && $e->newTotalPages === 3);
});

test('decreasing unassigns contents from removed pages instead of deleting them', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page5 = $issue->pages()->where('position', 5)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page5->contents()->attach($content->id, ['occupied_percentage' => '100']);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 3)
        ->call('resizePages', true);

    expect($issue->pages()->whereKey($page5->id)->exists())->toBeFalse()
        ->and(Content::whereKey($content->id)->exists())->toBeTrue()
        ->and($content->fresh()->isAssigned())->toBeFalse()
        ->and($issue->fresh()->unassignedContents->pluck('id'))->toContain($content->id);
});

test('a redattore without access to the magazine cannot resize pages', function () {
    $issue = reorderableIssue();
    $outsider = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Redattore]);

    Livewire::actingAs($outsider)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 10)
        ->call('resizePages');

    expect($issue->fresh()->total_pages)->toBe(6);
});

test('a sola lettura user cannot resize pages', function () {
    $issue = reorderableIssue();
    $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 10)
        ->call('resizePages');

    expect($issue->fresh()->total_pages)->toBe(6);
});

test('a guest cannot resize pages', function () {
    $issue = reorderableIssue();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 10)
        ->call('resizePages');

    expect($issue->fresh()->total_pages)->toBe(6);
});
