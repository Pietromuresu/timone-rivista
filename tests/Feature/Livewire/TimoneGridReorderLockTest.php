<?php

use App\Events\PageMoved;
use App\Livewire\Timone\Grid;
use App\Models\PageReorderLog;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui: Pest carica tutti i file di test nello stesso processo
// prima di eseguirli, quindi le funzioni globali dichiarate in un file
// sono già disponibili in tutti gli altri.

test('a successful move increments the issue reorder version', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    expect($issue->fresh()->reorder_version)->toBe(0);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 5);

    expect($issue->fresh()->reorder_version)->toBe(1);
});

test('moving a page to its current position is a no-op that does not bump the reorder version', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 2);

    expect($issue->fresh()->reorder_version)->toBe(0);
});

test('a move based on a stale reorder version is rejected without touching positions', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    // Simula un altro utente che ha già riordinato nel frattempo: la
    // versione in DB avanza, ma il componente sotto test continua a
    // tenere in memoria quella vecchia (0) — esattamente come accadrebbe
    // nel browser di un utente che non ha ancora ricevuto l'aggiornamento
    // via broadcast.
    $issue->increment('reorder_version');

    $component->set('reorderVersion', 0)
        ->call('movePage', $page->id, 5)
        ->assertHasErrors('reorder');

    expect($page->fresh()->position)->toBe(2)
        ->and(PageReorderLog::count())->toBe(0);

    Event::assertNotDispatched(PageMoved::class);
});

test('after a rejected move the component resyncs to the current version and can move again', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    $issue->increment('reorder_version');

    $component->set('reorderVersion', 0)
        ->call('movePage', $page->id, 5)
        ->assertHasErrors('reorder');

    expect($component->get('reorderVersion'))->toBe(1);

    $component->call('movePage', $page->id, 5);

    expect($page->fresh()->position)->toBe(5)
        ->and($issue->fresh()->reorder_version)->toBe(2);

    Event::assertDispatched(PageMoved::class);
});

test('a guest cannot bypass the lock check', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 3);

    expect($page->fresh()->position)->toBe(1)
        ->and($issue->fresh()->reorder_version)->toBe(0);
});
