<?php

use App\Enums\UserRole;
use App\Events\ContentAssigned;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php). reorderableIssue()
// crea un'issue con total_pages = 6.

test('an editor can extend an already assigned content to a second page', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $firstPage = $issue->pages()->where('position', 1)->first();
    $secondPage = $issue->pages()->where('position', 2)->first();

    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $firstPage->contents()->attach($content->id, ['occupied_percentage' => 100]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('extendToPage', $content->id, 2)
        ->assertHasNoErrors();

    expect($firstPage->contents()->where('content_id', $content->id)->exists())->toBeTrue()
        ->and($secondPage->contents()->where('content_id', $content->id)->exists())->toBeTrue()
        ->and($content->fresh()->pages)->toHaveCount(2);

    Event::assertDispatched(ContentAssigned::class, fn (ContentAssigned $e) => $e->pageId === $secondPage->id && $e->contentId === $content->id);
});

test('extending to a page that already has the content fails with an error', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($content->id, ['occupied_percentage' => 100]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('extendToPage', $content->id, 1)
        ->assertHasErrors(['extend']);
});

test('extending to a nonexistent position fails with an error', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($content->id, ['occupied_percentage' => 100]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('extendToPage', $content->id, 999)
        ->assertHasErrors(['extend']);
});

test('extending to a page without enough free space is rejected', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $firstPage = $issue->pages()->where('position', 1)->first();
    $secondPage = $issue->pages()->where('position', 2)->first();

    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $firstPage->contents()->attach($content->id, ['occupied_percentage' => 100]);

    $blocker = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $secondPage->contents()->attach($blocker->id, ['occupied_percentage' => 100]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('extendToPage', $content->id, 2)
        ->assertHasErrors(['extend']);

    expect($secondPage->contents()->where('content_id', $content->id)->exists())->toBeFalse();
});

test('a redattore without access to the magazine cannot extend a content', function () {
    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($content->id, ['occupied_percentage' => 100]);

    Livewire::actingAs($outsider)->test(Grid::class, ['issue' => $issue])
        ->call('extendToPage', $content->id, 2);

    $secondPage = $issue->pages()->where('position', 2)->first();
    expect($secondPage->contents()->where('content_id', $content->id)->exists())->toBeFalse();
});

test('the content search filter narrows the unassigned contents panel', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Prova su strada elettrica']);
    Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Intervista al direttore']);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSee('Prova su strada elettrica')
        ->assertSee('Intervista al direttore')
        ->set('contentSearch', 'strada')
        ->assertSee('Prova su strada elettrica')
        ->assertDontSee('Intervista al direttore');
});

test('an empty search result shows a dedicated message', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Prova su strada']);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('contentSearch', 'nessun-titolo-corrisponde')
        ->assertSee('Nessun contenuto trovato');
});
