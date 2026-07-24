<?php

use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\UserRole;
use App\Events\ContentAssigned;
use App\Events\ContentUnassigned;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

test('an editor assigns an article to an empty page at 100 percent', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $content->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(100.0)
        ->and($issue->fresh()->unassignedContents->pluck('id'))->not->toContain($content->id);

    Event::assertDispatched(ContentAssigned::class, fn (ContentAssigned $event) => $event->issueId === $issue->id
        && $event->pageId === $page->id
        && $event->contentId === $content->id
        && $event->percentage === 100.0);
});

test('assigning an advertisement uses its format default percentage', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create([
        'content_id' => $content->id,
        'format' => AdFormat::UnQuartoPagina,
        'occupied_percentage_override' => null,
    ]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $content->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(25.0);
});

test('assigning an advertisement with a manual override uses the override', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create([
        'content_id' => $content->id,
        'format' => AdFormat::UnQuartoPagina,
        'occupied_percentage_override' => 40,
    ]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $content->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(40.0);
});

test('assigning a content to a page without enough free space is rejected', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $existing = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($existing->id, ['occupied_percentage' => 100]);

    $newContent = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $newContent->id, $page->id);

    expect($newContent->fresh()->isAssigned())->toBeFalse();

    Event::assertNotDispatched(ContentAssigned::class);
});

test('updating a content percentage beyond the page free space is rejected', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $a = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $b = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($a->id, ['occupied_percentage' => 50]);
    $page->contents()->attach($b->id, ['occupied_percentage' => 50]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateContentPercentage', $page->id, $a->id, 60);

    $pivot = $page->contents()->where('content_id', $a->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(50.0);
});

test('updating a content percentage within the page free space is applied', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $a = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $b = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($a->id, ['occupied_percentage' => 50]);
    $page->contents()->attach($b->id, ['occupied_percentage' => 50]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateContentPercentage', $page->id, $a->id, 30)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $a->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(30.0);

    Event::assertDispatched(ContentAssigned::class, fn (ContentAssigned $event) => $event->contentId === $a->id && $event->percentage === 30.0);
});

test('unassigning a content removes it from the page and returns it to the unassigned pool', function () {
    Event::fake([ContentUnassigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($content->id, ['occupied_percentage' => 100]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('unassignContent', $content->id, $page->id);

    expect($page->contents()->where('content_id', $content->id)->exists())->toBeFalse()
        ->and($issue->fresh()->unassignedContents->pluck('id'))->toContain($content->id);

    Event::assertDispatched(ContentUnassigned::class, fn (ContentUnassigned $event) => $event->contentId === $content->id && $event->pageId === $page->id);
});

test('a sola lettura user cannot assign a content', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    expect($content->fresh()->isAssigned())->toBeFalse();

    Event::assertNotDispatched(ContentAssigned::class);
});

test('a redattore without access to the magazine cannot assign a content', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($outsider)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    expect($content->fresh()->isAssigned())->toBeFalse();
});

test('an admin can assign a content on any issue', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($admin)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    expect($content->fresh()->isAssigned())->toBeTrue();
});

test('a guest cannot assign a content', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    expect($content->fresh()->isAssigned())->toBeFalse();
});

test('a content belonging to a different issue cannot be assigned', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $otherIssue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $otherIssue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    expect($content->fresh()->isAssigned())->toBeFalse();

    Event::assertNotDispatched(ContentAssigned::class);
});
