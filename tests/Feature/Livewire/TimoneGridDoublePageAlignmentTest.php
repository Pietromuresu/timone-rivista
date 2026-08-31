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

    $blockFor = function (int $pageId) use ($html) {
        $markerPos = mb_strpos($html, 'data-page-id="'.$pageId.'"');
        expect($markerPos)->not->toBeFalse();

        return mb_substr($html, $markerPos, 400);
    };

    // Pagina 1: apertura singola iniziale, spinta a destra (ml-auto) —
    // nessuna pagina sinistra la precede, convenzione di stampa.
    expect($blockFor($page1->id))->toContain('ml-auto');

    // Pagina 6 (ultima, totale pari): apertura singola finale, resta a
    // sinistra per comportamento flex-start di default, NON "ml-auto".
    expect($blockFor($page6->id))->not->toContain('ml-auto');

    // Pagina 3: fa parte di un'apertura da due pagine (2-3), non va
    // spinta né a destra né altrove.
    expect($blockFor($page3->id))->not->toContain('ml-auto');
});
