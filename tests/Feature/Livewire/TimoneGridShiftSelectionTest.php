<?php

use App\Enums\CoverSlot;
use App\Livewire\Timone\Grid;
use App\Models\Issue;
use Livewire\Livewire;

/*
 * Shift+clic (2026-10-30): seleziona l'intervallo dall'ultima pagina
 * cliccata, in Griglia, Doppia pagina e Lista (stesso metodo).
 * reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.
 */

function idsAt(Issue $issue, array $positions): array
{
    return $issue->pages()->whereIn('position', $positions)->orderBy('position')->pluck('id')->all();
}

test('shift+click selects the range from the last clicked page, in both directions', function () {
    $issue = reorderableIssue();
    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    $grid->call('ctrlClickPage', idsAt($issue, [2])[0])
        ->call('shiftClickPage', idsAt($issue, [5])[0]);
    expect(collect($grid->get('selectedPageIds'))->sort()->values()->all())->toBe(idsAt($issue, [2, 3, 4, 5]));

    // Nuovo punto di partenza con un clic, poi Shift verso l'alto.
    $grid->call('clickPage', idsAt($issue, [4])[0])
        ->call('shiftClickPage', idsAt($issue, [1])[0]);
    expect(collect($grid->get('selectedPageIds'))->sort()->values()->all())->toBe(idsAt($issue, [1, 2, 3, 4]));
});

test('shift+click without a starting page starts the selection like ctrl+click; covers stay out', function () {
    $issue = reorderableIssue();
    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    $grid->call('shiftClickPage', idsAt($issue, [3])[0])
        ->assertSet('selectionMode', true)
        ->assertSet('selectedPageIds', idsAt($issue, [3]))
        ->call('shiftClickPage', $issue->covers()->where('cover_slot', CoverSlot::II->value)->value('id'))
        ->assertSet('selectedPageIds', idsAt($issue, [3]));
});

test('the cards and rows send shift+click to the server in every view', function (string $view) {
    $issue = reorderableIssue();
    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->call('setViewMode', $view)->html();

    expect($html)->toContain('$event.shiftKey ? $wire.shiftClickPage(');
})->with(['griglia', 'doppia', 'lista']);
