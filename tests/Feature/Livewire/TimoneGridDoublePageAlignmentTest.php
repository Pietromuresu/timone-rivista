<?php

use App\Livewire\Timone\Grid;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.
// reorderableIssue() crea un'issue con total_pages=6 (pari): copre in un
// solo fixture sia il caso "prima pagina sola" (dispari, sempre) sia
// "ultima pagina sola" (solo quando il totale è pari, qui il caso).

test('in double-page view the first page is right-aligned and the last page is not pushed right', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $html = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('viewMode', 'doppia')
        ->html();

    $page1 = $issue->pages()->where('position', 1)->first();
    $page6 = $issue->pages()->where('position', 6)->first();
    $page3 = $issue->pages()->where('position', 3)->first();

    // Griglia a doppia pagina (2026-10-29): non più ml-auto sulla pagina 1.
    // La 1 è a destra perché la precede lo slot vuoto «II di copertina»;
    // l'ultima (totale pari) è a sinistra perché la segue «III di copertina».
    // Le colonne le assegna app.css (.timone-spreads) dall'ordine dei figli.
    $grid = substr($html, strpos($html, 'data-testid="spread-grid"'));
    $at = fn (string $needle) => strpos($grid, $needle);

    expect($at('data-spread-empty="left"'))->toBeLessThan($at('data-page-id="'.$page1->id.'"'))
        ->and($at('data-page-id="'.$page6->id.'"'))->toBeLessThan($at('data-spread-empty="right"'))
        ->and(substr_count($grid, 'data-testid="spread-empty"'))->toBe(2)
        ->and(preg_match('/data-page-id="'.$page3->id.'"[^>]*data-spread-side="right"/s', $grid))->toBe(1)
        ->and($grid)->not->toContain('ml-auto');
});
