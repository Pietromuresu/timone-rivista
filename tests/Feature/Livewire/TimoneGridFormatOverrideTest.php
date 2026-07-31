<?php

use App\Livewire\Timone\Grid;
use App\Models\PageFile;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.
// Nessun ext-imagick richiesto: si parte già da un PageFile con
// format_check_status precalcolato (factory state), senza passare dal Job.

test('an editor can force-accept a mismatching format', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $pageFile = PageFile::factory()->for($page)->formatMismatch()->create();

    expect($pageFile->hasUnresolvedFormatMismatch())->toBeTrue();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $pageFile->id)
        ->assertHasNoErrors();

    $pageFile->refresh();
    expect($pageFile->format_override_confirmed_at)->not->toBeNull()
        ->and($pageFile->format_override_confirmed_by)->toBe($user->id)
        ->and($pageFile->hasUnresolvedFormatMismatch())->toBeFalse();
});

test('confirming a format that already matches does nothing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $pageFile = PageFile::factory()->for($page)->formatMatching()->create();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $pageFile->id);

    expect($pageFile->fresh()->format_override_confirmed_at)->toBeNull();
});

test('a locked page cannot have its format override confirmed', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $page->update(['locked_at' => now(), 'locked_by' => $user->id]);
    $pageFile = PageFile::factory()->for($page)->formatMismatch()->create();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $pageFile->id)
        ->assertHasErrors('locked');

    expect($pageFile->fresh()->format_override_confirmed_at)->toBeNull();
});

test('a page file from a different issue cannot have its format override confirmed through this grid', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $otherIssue = reorderableIssue();
    $otherPage = $otherIssue->pages()->where('position', 1)->first();
    $pageFile = PageFile::factory()->for($otherPage)->formatMismatch()->create();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $pageFile->id);

    expect($pageFile->fresh()->format_override_confirmed_at)->toBeNull();
});

test('a guest cannot confirm a format override', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $pageFile = PageFile::factory()->for($page)->formatMismatch()->create();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $pageFile->id);

    expect($pageFile->fresh()->format_override_confirmed_at)->toBeNull();
});
