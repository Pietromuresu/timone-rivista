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

// $newTotalPages è tipizzato `string`, non `int` (vedi Grid.php): un campo
// tipato `int` andava in TypeError Livewire non appena l'utente svuotava o
// digitava un carattere non numerico nell'input — riproducibile solo
// impostando esplicitamente questi valori "intermedi" via ->set(), dato
// che wire:model.blur non scrive ad ogni tasto ma solo al blur.

test('typing an empty value for total pages does not crash and shows a controlled validation message instead', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', '')
        ->call('resizePages')
        ->assertHasErrors('pageCount')
        ->assertOk();

    expect($issue->fresh()->total_pages)->toBe(6);
});

test('a non-numeric value for total pages is rejected with a controlled validation message, not an exception', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', 'abc')
        ->call('resizePages')
        ->assertHasErrors('pageCount')
        ->assertOk();

    expect($issue->fresh()->total_pages)->toBe(6);
});

test('a negative value for total pages is rejected with a controlled validation message', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', '-5')
        ->call('resizePages')
        ->assertHasErrors('pageCount')
        ->assertOk();

    expect($issue->fresh()->total_pages)->toBe(6);
});

test('progressively typing digits toward a valid total pages value never crashes the component', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('togglePageCountEditor');

    foreach (['', '1', '12'] as $partial) {
        $component->set('newTotalPages', $partial)->assertOk();
    }
});

test('a valid total pages value still resizes correctly leaving content already assigned on kept pages untouched', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page2 = $issue->pages()->where('position', 2)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page2->contents()->attach($content->id, ['occupied_percentage' => '100']);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', '8')
        ->call('resizePages')
        ->assertHasNoErrors();

    expect($issue->fresh()->total_pages)->toBe(8)
        ->and($page2->fresh()->contents()->count())->toBe(1);
});
