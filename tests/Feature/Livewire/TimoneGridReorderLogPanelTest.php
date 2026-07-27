<?php

use App\Events\PageMoved;
use App\Livewire\Timone\Grid;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php).

test('the reorder log panel is closed by default and toggles on click', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSet('showReorderLog', false)
        ->assertDontSee('Storico spostamenti (ultimi')
        ->call('toggleReorderLog')
        ->assertSet('showReorderLog', true)
        ->assertSee('Storico spostamenti (ultimi')
        ->call('toggleReorderLog')
        ->assertSet('showReorderLog', false)
        ->assertDontSee('Storico spostamenti (ultimi');
});

test('an empty history shows the empty state message once opened', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleReorderLog')
        ->assertSee('Nessuno spostamento registrato');
});

test('a completed move appears in the opened history panel with the moving user and positions', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $page->id, 5)
        ->call('toggleReorderLog')
        ->assertSee($user->name)
        ->assertSee('dalla posizione 2 alla 5');
});
