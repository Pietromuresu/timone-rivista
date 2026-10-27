<?php

use App\Events\PageMoved;
use App\Events\PagesBlockMoved;
use App\Livewire\Timone\Grid;
use App\Models\PageGroup;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// (issue con 6 pagine).

/*
 * Fase 1, bug 4 (2026-10-26): le frecce chiamavano movePage(id, posizione ± 1),
 * ma per una pagina di un gruppo collegato movePage() interpreta la
 * destinazione come INIZIO del blocco — con il focus sulla seconda pagina
 * del gruppo, freccia sinistra spostava il gruppo a destra. Ora le frecce
 * chiamano nudgePage(), che calcola la destinazione dal blocco intero.
 */

test('arrow left on the second page of a linked group moves the whole group one step left', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();
    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[2]->update(['page_group_id' => $group->id]); // posizione 3
    $pages[3]->update(['page_group_id' => $group->id]); // posizione 4

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('nudgePage', $pages[3]->id, -1);

    expect($pages[2]->fresh()->position)->toBe(2)
        ->and($pages[3]->fresh()->position)->toBe(3)
        ->and($pages[1]->fresh()->position)->toBe(4);
});

test('arrow right on the first page of a linked group moves the whole group one step right', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();
    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[2]->update(['page_group_id' => $group->id]);
    $pages[3]->update(['page_group_id' => $group->id]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('nudgePage', $pages[2]->id, 1);

    expect($pages[2]->fresh()->position)->toBe(4)
        ->and($pages[3]->fresh()->position)->toBe(5)
        ->and($pages[4]->fresh()->position)->toBe(3);
});

test('a single page moves by one and nothing happens at the edge', function () {
    Event::fake([PageMoved::class]);

    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('nudgePage', $pages[1]->id, 1);

    expect($pages[1]->fresh()->position)->toBe(3);

    $grid->call('nudgePage', $pages[0]->id, -1);

    expect($pages[0]->fresh()->position)->toBe(1);
    Event::assertDispatchedTimes(PageMoved::class, 1);
});

test('the page cards expose the group id and use the keyboard nudge', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();
    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    expect($html)->toContain('data-group-id="'.$group->id.'"')
        ->and($html)->toContain("\$animatedMove('nudgePage', ".$pages[0]->id.', -1)')
        ->and($html)->not->toContain('$wire.movePage('.$pages[0]->id.', ');
});
