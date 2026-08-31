<?php

use App\Events\PagesBlockMoved;
use App\Livewire\Timone\Grid;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.

// Fase 2.2 del batch di modifiche (2026-08-31): il drag&drop in vista
// doppia era già stato corretto il 2026-07-30 (vedi HANDOFF.md, "Bugfix:
// drag&drop della singola pagina in modalità doppia"), ma nessun test
// asserisce esplicitamente il markup di wiring — chiude quel buco.
test('the doppia view mode wires up both single-page and block move listeners on its sortable container', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $html = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'doppia')
        ->html();

    expect($html)
        ->toContain('@page-dropped="$wire.movePage(')
        ->toContain('@block-dropped="$wire.moveSelectedBlock(');
});

// movePage()/moveSelectedBlock() sono agnostiche rispetto a viewMode (il
// backend non lo controlla mai, vedi Grid.php) — questo test lo dimostra
// esplicitamente per la vista doppia, invece di presumerlo dal fatto che
// griglia/lista lo passano: chiude il punto lasciato esplicitamente aperto
// dalla Fase 0 ("da controllare esplicitamente in Fase 2, non presumere
// che erediti automaticamente il fix").
test('a block move works correctly while the component is set to doppia view mode', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'doppia')
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[1]->id)
        ->call('togglePageSelection', $pages[2]->id);

    $component->call('moveSelectedBlock', $pages[1]->id, 6)
        ->assertHasNoErrors();

    $finalOrder = $issue->fresh()->pages()->orderBy('position')->pluck('id')->all();
    $indexOfFirst = array_search($pages[1]->id, $finalOrder, true);
    $indexOfSecond = array_search($pages[2]->id, $finalOrder, true);

    expect($indexOfSecond)->toBe($indexOfFirst + 1);

    Event::assertDispatched(PagesBlockMoved::class);
});

/*
 * Non automatizzabile in questo progetto (nessun Dusk/browser tooling, vedi
 * HANDOFF.md — debito già segnalato altrove): il vero trascinamento del
 * mouse su un contenitore con larghezza fissa (Fase 1.4, w-[20.125rem]/
 * sm:w-[24.125rem], ml-auto sulla prima apertura singola) resta verificato
 * solo per lettura di codice — resources/js/app.js:147-210, direttiva
 * x-sortable, non distingue in alcun modo tra viewMode ("griglia"/"doppia"/
 * "lista" sono lo stesso identico Sortable.create() applicato a contenitori
 * diversi, draggable: '[data-page-id]' filtra correttamente gli spacer
 * "basis-full h-0" di grid.blade.php:667 senza toccare la logica di
 * riordino) — nessuna interazione negativa individuata con le classi
 * ml-auto/w-fisso introdotte in Fase 1.4, che riguardano solo il
 * posizionamento visivo del contenitore, non l'elenco degli elementi
 * trascinabili al suo interno.
 */
