<?php

use App\Livewire\Timone\Grid;
use Livewire\Livewire;

use function Livewire\store;

/*
 * 2026-10-30: un clic spedito da Livewire nella stessa richiesta del
 * polling (stesso istante) non deve perdere il ridisegno. Il polling da
 * solo continua a non ridisegnare la griglia. reorderableIssue()/editorFor()
 * sono in TimoneGridReorderTest.php.
 */

function pollingRequest(Grid $grid, array $methods): bool
{
    request()->merge(['components' => [[
        'snapshot' => json_encode(['memo' => ['id' => $grid->getId()]]),
        'updates' => [],
        'calls' => array_map(fn ($method) => ['method' => $method, 'params' => []], $methods),
    ]]]);
    store($grid)->unset('skipRender');
    $grid->checkForCommentUpdates();

    return (bool) store($grid)->get('skipRender');
}

test('the comment polling alone skips the render; together with a click it does not', function () {
    $issue = reorderableIssue();
    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->instance();

    expect(pollingRequest($grid, ['checkForCommentUpdates']))->toBeTrue()
        ->and(pollingRequest($grid, ['checkForUpdates', 'checkForCommentUpdates']))->toBeTrue()
        ->and(pollingRequest($grid, ['toggleSelectionMode', 'checkForCommentUpdates']))->toBeFalse();
});
