<?php

use App\Enums\UserRole;
use App\Events\ContentListChanged;
use App\Livewire\Timone\Grid;
use App\Models\Article;
use App\Models\Content;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Fase 1, bug 3: archivia/ripristina/elimina ora trasmettono
// ContentListChanged — finto qui come ogni altro broadcast nei test (vedi
// HANDOFF.md, BROADCAST_CONNECTION e Event::fake()).
beforeEach(fn () => Event::fake([ContentListChanged::class]));

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.
// Fase 4.3 — archiviazione/eliminazione di contenuti NON assegnati. Nessuna
// delle azioni qui sotto tocca broadcasting/eventi (a differenza di
// assignContent/movePage ecc.), quindi non serve Event::fake().

test('archiving an unassigned content removes it from the assignable panel but keeps it in the database', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('archiveContent', $content->id);

    expect($issue->fresh()->unassignedContents->pluck('id'))->not->toContain($content->id)
        ->and(Content::find($content->id))->not->toBeNull()
        ->and($content->fresh()->isArchived())->toBeTrue();
});

test('an archived content can be unarchived and reappears among assignable contents', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'archived_at' => now()]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('unarchiveContent', $content->id);

    expect($content->fresh()->isArchived())->toBeFalse()
        ->and($issue->fresh()->unassignedContents->pluck('id'))->toContain($content->id);
});

test('the archived contents panel only loads its query when opened', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    Content::factory()->article()->create(['issue_id' => $issue->id, 'archived_at' => now()]);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    // archivedContents è dato passato alla vista da render(), non una
    // property pubblica del componente — viewData(), non get().
    expect($component->viewData('archivedContents'))->toBeNull();

    $component->set('showArchivedContents', true);

    expect($component->viewData('archivedContents'))->toHaveCount(1);
});

test('deleting an unassigned content requires the confirmed flag, a first call does nothing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('deleteUnassignedContent', $content->id, false);

    expect(Content::find($content->id))->not->toBeNull();
});

test('deleting an unassigned content with confirmation removes it permanently, including its article', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $articleId = $content->article->id;

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('deleteUnassignedContent', $content->id, true);

    expect(Content::find($content->id))->toBeNull()
        ->and(Article::find($articleId))->toBeNull();
});

test('a content already assigned to a page cannot be deleted through this method', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();
    $page->contents()->attach($content->id, ['occupied_twelfths' => 12]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('deleteUnassignedContent', $content->id, true);

    expect(Content::find($content->id))->not->toBeNull();
});

test('a content already assigned to a page cannot be archived through this method', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();
    $page->contents()->attach($content->id, ['occupied_twelfths' => 12]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('archiveContent', $content->id);

    expect($content->fresh()->isArchived())->toBeFalse();
});

test('a redattore without access to the magazine cannot archive or delete a content', function () {
    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::actingAs($outsider)->test(Grid::class, ['issue' => $issue])
        ->call('archiveContent', $content->id);

    expect($content->fresh()->isArchived())->toBeFalse();

    Livewire::actingAs($outsider)->test(Grid::class, ['issue' => $issue])
        ->call('deleteUnassignedContent', $content->id, true);

    expect(Content::find($content->id))->not->toBeNull();
});

test('a sola lettura user cannot archive or delete a content', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('deleteUnassignedContent', $content->id, true);

    expect(Content::find($content->id))->not->toBeNull();
});

test('a guest cannot archive or delete a content', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('deleteUnassignedContent', $content->id, true);

    expect(Content::find($content->id))->not->toBeNull();
});
