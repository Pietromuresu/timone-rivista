<?php

use App\Livewire\Timone\ActivityLogPanel;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\Grid;
use App\Models\ActivityLog;
use App\Models\Content;
use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\ContentUnassigned;
use App\Events\IssuePageCountUpdated;
use App\Events\PageMoved;
use App\Events\PageStatusUpdated;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php). reorderableIssue()
// crea un'issue con total_pages = 6.

test('moving a page logs an activity entry', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 5);

    $log = ActivityLog::where('action', 'page.moved')->first();

    expect($log)->not->toBeNull()
        ->and($log->issue_id)->toBe($issue->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->entity_type)->toBe('Page')
        ->and($log->entity_id)->toBe($page->id)
        ->and($log->description)->toContain('posizione 2')
        ->and($log->description)->toContain('5')
        ->and($log->old_values)->toBe(['position' => 2])
        ->and($log->new_values)->toBe(['position' => 5]);
});

test('a no-op move does not log anything', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 2);

    expect(ActivityLog::count())->toBe(0);
});

test('changing a page status logs an activity entry', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, 'in_bozza');

    $log = ActivityLog::where('action', 'page.status_changed')->first();

    expect($log)->not->toBeNull()
        ->and($log->entity_type)->toBe('Page')
        ->and($log->new_values)->toBe(['status' => 'in_bozza']);
});

test('assigning a content logs an activity entry', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    $log = ActivityLog::where('action', 'content.assigned')->first();

    expect($log)->not->toBeNull()
        ->and($log->entity_type)->toBe('Content')
        ->and($log->entity_id)->toBe($content->id);
});

test('unassigning a content logs an activity entry', function () {
    Event::fake([ContentUnassigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($content->id, ['occupied_percentage' => 100]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('unassignContent', $content->id, $page->id);

    $log = ActivityLog::where('action', 'content.unassigned')->first();

    expect($log)->not->toBeNull()
        ->and($log->entity_id)->toBe($content->id);
});

test('changing the total page count logs an activity entry', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 8)
        ->call('resizePages');

    $log = ActivityLog::where('action', 'issue.page_count_changed')->first();

    expect($log)->not->toBeNull()
        ->and($log->entity_type)->toBe('Issue')
        ->and($log->old_values)->toBe(['total_pages' => 6])
        ->and($log->new_values)->toBe(['total_pages' => 8]);
});

test('changing the ad threshold logs an activity entry', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateAdThreshold', '25');

    $log = ActivityLog::where('action', 'magazine.ad_threshold_changed')->first();

    expect($log)->not->toBeNull()
        ->and($log->entity_type)->toBe('Magazine');
});

test('creating a content logs an activity entry', function () {
    Event::fake([ContentCreated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Nuovo articolo di prova')
        ->call('save');

    $log = ActivityLog::where('action', 'content.created')->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('Nuovo articolo di prova');
});

test('a guest action does not log anything (authorization blocks it first)', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, 'in_bozza');

    expect(ActivityLog::count())->toBe(0);
});

test('the activity log panel is closed by default and shows entries once opened', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, 'in_bozza');

    Livewire::actingAs($user)->test(ActivityLogPanel::class, ['issue' => $issue])
        ->assertSet('show', false)
        ->assertDontSee('Cronologia (ultime')
        ->call('toggle')
        ->assertSet('show', true)
        ->assertSee('Cronologia (ultime')
        ->assertSee($user->name);
});

test('an empty history shows the empty state once opened', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ActivityLogPanel::class, ['issue' => $issue])
        ->call('toggle')
        ->assertSee('Nessuna azione registrata');
});

test('activity log entries are scoped to their own issue', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $otherIssue = reorderableIssue();
    $user = editorFor($issue);
    $otherIssue->magazine->users()->attach($user);
    $page = $issue->pages()->where('position', 1)->first();
    $otherPage = $otherIssue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, 'in_bozza');
    Livewire::actingAs($user)->test(Grid::class, ['issue' => $otherIssue])
        ->call('changePageStatus', $otherPage->id, 'revisionata');

    expect(ActivityLog::where('issue_id', $issue->id)->count())->toBe(1)
        ->and(ActivityLog::where('issue_id', $otherIssue->id)->count())->toBe(1);

    Livewire::actingAs($user)->test(ActivityLogPanel::class, ['issue' => $issue])
        ->call('toggle')
        ->assertSee('In bozza')
        ->assertDontSee('Revisionata');
});
