<?php

use App\Events\PageMoved;
use App\Livewire\Timone\Grid;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php).

test('pollRefresh resyncs the reorder version to the current database value', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    // Simula un riordino avvenuto altrove (un altro utente, o questo
    // stesso client su un'altra scheda) mentre i websocket erano giù: la
    // versione tenuta in memoria dal componente resta quella vecchia
    // finché non arriva un refresh — via broadcast normalmente, via
    // polling qui.
    $issue->increment('reorder_version');

    expect($component->get('reorderVersion'))->toBe(0);

    $component->call('pollRefresh');

    expect($component->get('reorderVersion'))->toBe(1);
});

test('after a pollRefresh a move using the resynced version succeeds normally', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    $issue->increment('reorder_version');

    $component->call('pollRefresh')
        ->call('movePage', $page->id, 5)
        ->assertHasNoErrors();

    expect($page->fresh()->position)->toBe(5);

    Event::assertDispatched(PageMoved::class);
});
