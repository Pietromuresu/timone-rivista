<?php

use App\Livewire\Timone\Grid;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

test('the list view shows DX for odd positions and SX for even positions', function () {
    // Stessa convenzione di App\Support\PageSpreadBuilder (pagina 1 sempre
    // a destra): pagina dispari = destra (DX), pagina pari = sinistra (SX).
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $html = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('viewMode', 'lista')
        ->html();

    $page1 = $issue->pages()->where('position', 1)->first();
    $page2 = $issue->pages()->where('position', 2)->first();
    $page3 = $issue->pages()->where('position', 3)->first();

    foreach ([[$page1, 'DX'], [$page2, 'SX'], [$page3, 'DX']] as [$page, $expectedSide]) {
        $marker = 'data-page-id="'.$page->id.'"';
        $markerPos = mb_strpos($html, $marker);
        expect($markerPos)->not->toBeFalse();

        $rowBlock = mb_substr($html, $markerPos, 2500);

        expect($rowBlock)->toContain('>'.$expectedSide.'<')
            ->and(mb_substr_count($rowBlock, '>'.$expectedSide.'<'))->toBe(1);
    }
});
