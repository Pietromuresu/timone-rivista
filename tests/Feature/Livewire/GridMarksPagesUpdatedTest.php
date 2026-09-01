<?php

use App\Enums\ContentType;
use App\Events\ContentAssigned;
use App\Events\PageMoved;
use App\Events\PageStatusUpdated;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.

/**
 * Prova end-to-end (non solo lettura di codice) che le mutazioni reali di
 * Grid.php avanzano davvero issues.pages_updated_at — il meccanismo di
 * polling (checkForUpdates(), vedi TimoneGridPollingFallbackTest.php) non
 * ha alcun senso se il "mark" non viene chiamato per davvero da ogni punto
 * che già trasmette un broadcast (Issue::markPagesUpdated(), vedi
 * HANDOFF.md). Copre un campione rappresentativo dei ~14 punti di chiamata
 * (movePage, changePageStatus, assignContent), non tutti — gli altri
 * seguono lo stesso identico pattern a una riga, verificato a mano in
 * HANDOFF.md.
 */
test('movePage advances issues.pages_updated_at', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    expect($issue->fresh()->getRawOriginal('pages_updated_at'))->toBeNull();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 4);

    expect($issue->fresh()->getRawOriginal('pages_updated_at'))->not->toBeNull();
});

test('changePageStatus advances issues.pages_updated_at', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, 'assegnata');

    expect($issue->fresh()->getRawOriginal('pages_updated_at'))->not->toBeNull();
});

test('assignContent advances issues.pages_updated_at', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Articolo]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    expect($issue->fresh()->getRawOriginal('pages_updated_at'))->not->toBeNull();
});

test('markPagesUpdated only touches pages_updated_at, not the model updated_at', function () {
    $issue = reorderableIssue();
    $originalUpdatedAt = $issue->updated_at;

    $issue->markPagesUpdated();

    expect($issue->fresh()->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});
