<?php

use App\Events\PageMoved;
use App\Livewire\Timone\Grid;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php).

test('checkForUpdates resyncs the reorder version when pages_updated_at has advanced', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    // Simula una mutazione avvenuta altrove (un altro utente, o questo
    // stesso client su un'altra scheda) mentre i websocket erano giù:
    // Issue::markPagesUpdated() è quello che ogni mutazione reale chiama
    // già insieme al proprio broadcast (vedi HANDOFF.md) — qui lo
    // richiamiamo a mano per simulare "qualcosa è cambiato" senza dover
    // rieseguire un intero movePage().
    $issue->increment('reorder_version');
    $issue->markPagesUpdated();

    expect($component->get('reorderVersion'))->toBe(0);

    $component->call('checkForUpdates');

    expect($component->get('reorderVersion'))->toBe(1);
});

test('checkForUpdates does nothing when pages_updated_at has not changed since the last check', function () {
    // Chiude esplicitamente il punto "non ricaricare sempre tutto" della
    // richiesta: un poll che non trova nulla di nuovo non deve toccare lo
    // stato del componente (né riallineare reorderVersion, che qui
    // resterebbe comunque "corretto", ma è la prova più semplice che il
    // ramo "nessun cambiamento" è stato preso davvero, non un giro a vuoto
    // che ha comunque rifatto tutto il lavoro).
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    // Nessuna mutazione, nessun markPagesUpdated(): pages_updated_at resta
    // esattamente quello visto al mount (verosimilmente null su un'issue
    // appena creata dalla factory, mai toccata).
    $component->call('checkForUpdates');

    expect($component->get('lastKnownPagesUpdatedAt'))->toBe($issue->fresh()->getRawOriginal('pages_updated_at'));
});

test('after a checkForUpdates resync, a move using the resynced version succeeds normally', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    $issue->increment('reorder_version');
    $issue->markPagesUpdated();

    $component->call('checkForUpdates')
        ->call('movePage', $page->id, 5)
        ->assertHasNoErrors();

    expect($page->fresh()->position)->toBe(5);

    Event::assertDispatched(PageMoved::class);
});
