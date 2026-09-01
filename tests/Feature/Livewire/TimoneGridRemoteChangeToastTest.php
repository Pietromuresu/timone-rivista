<?php

use App\Livewire\Timone\Grid;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.

/**
 * Indicatore visivo leggero (richiesta: "toast... quando una modifica
 * arriva da un altro utente") — ogni listener broadcast già esistente
 * (#[On('echo-presence:...')]) ora chiama anche Grid::notifyRemoteChange(),
 * che dispatcha un evento browser 'remote-change' ascoltato da Alpine in
 * grid.blade.php. Qui si verifica solo che il dispatch avvenga (il
 * comportamento client-side, mostrare/nascondere il toast, non è
 * testabile senza un browser reale — stesso limite generale già
 * documentato più volte in HANDOFF.md).
 */
test('receiving a remote PageMoved broadcast dispatches a toast event', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('onPageMoved')
        ->assertDispatched('remote-change');
});

test('receiving a remote ContentAssigned broadcast dispatches a toast event', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('onContentAssigned')
        ->assertDispatched('remote-change');
});

test('a checkForUpdates poll tick with nothing new does NOT dispatch a toast event', function () {
    // Un tick di wire:poll.1s che non trova nessun cambiamento
    // (pages_updated_at invariato) prende il ramo skipRender() di
    // checkForUpdates() — un toast lì sarebbe un falso positivo ad ogni
    // singolo secondo di polling, vedi il commento su Grid::checkForUpdates().
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('checkForUpdates')
        ->assertNotDispatched('remote-change');
});

test('a checkForUpdates poll tick DOES dispatch a toast event once a real change is detected', function () {
    // A differenza del vecchio pollRefresh() (mai un toast, sempre un
    // re-render pieno), checkForUpdates() è il meccanismo di polling vero
    // e proprio ora (2026-09-01) — quando rileva un cambiamento reale
    // (pages_updated_at avanzato, vedi Issue::markPagesUpdated()) deve
    // avvisare l'utente esattamente come farebbe un broadcast websocket.
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    // Il "mark" avviene DOPO il mount, per simulare una mutazione
    // arrivata mentre questo client era già montato — se avvenisse prima,
    // mount() leggerebbe già il nuovo valore e checkForUpdates() non
    // rileverebbe alcun cambiamento.
    $issue->markPagesUpdated();

    $component->call('checkForUpdates')
        ->assertDispatched('remote-change');
});
