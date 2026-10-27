<?php

use App\Enums\AdFormat;
use App\Events\ContentAssigned;
use App\Livewire\Timone\Grid;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Revisione 2, Fase 1, bug 3 (2026-10-27): con più contenuti frazionari
// sulla stessa pagina i comandi dentro i blocchi venivano tagliati
// dall'overflow e la select dello spazio non era cliccabile. Ora ogni
// blocco ha un bottone «⋯» e i comandi stanno in un menu teleportato nel
// <body>. La raggiungibilità vera (clic a ogni zoom) è verificata nel
// browser; qui il markup che la rende possibile.
// layoutContent() è in PageLayoutTest.php, reorderableIssue()/editorFor()
// in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class]));

test('every block of a fractional page has its menu button and a teleported menu with all commands', function (array $parts) {
    $issue = reorderableIssue();

    foreach ($parts as $i => $twelfths) {
        layoutContent($issue, 1, $twelfths, $i % 2 === 0 ? AdFormat::UnQuartoPagina : null, placeholder: $i % 2 === 0);
    }

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    expect(substr_count($html, 'data-testid="layout-block"'))->toBe(count($parts))
        ->and(substr_count($html, 'data-testid="content-menu-button"'))->toBe(count($parts))
        ->and(substr_count($html, '<template x-teleport="body">'))->toBe(count($parts))
        ->and(substr_count($html, 'data-testid="content-menu"'))->toBe(count($parts))
        // Nel menu: spazio, frecce e rotazione (Fase 2.3), occhio, modifica, estendi, rimuovi.
        ->and(substr_count($html, 'wire:change="updateContentOccupancy('))->toBe(count($parts))
        ->and(substr_count($html, 'data-testid="grid-arrows"'))->toBe(count($parts))
        ->and($html)->toContain('<span>Ruota (verticale ↔ orizzontale)</span>')
        ->and($html)->toContain('<span>Modifica</span>')
        ->and($html)->toContain('<span>Rimuovi dalla pagina</span>')
        // I comandi non sono più dentro i blocchi (dove l'overflow li tagliava).
        ->and($html)->not->toContain('group-hover/block:inline-flex')
        ->and($html)->not->toContain('data-testid="content-controls"');
})->with([
    '1/2 + 1/2' => [[6, 6]],
    '1/3 + 2/3' => [[4, 8]],
    '1/4 × 4' => [[3, 3, 3, 3]],
    '1/4 + 1/4 + 1/2' => [[3, 3, 6]],
]);

test('the list view keeps the commands inline next to the name', function () {
    $issue = reorderableIssue();
    layoutContent($issue, 1, 6);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'lista')
        ->assertSeeHtml('data-testid="content-controls"')
        ->assertDontSeeHtml('data-testid="content-menu-button"');
});

test('a locked page has no menu (nothing can be changed there)', function () {
    $issue = reorderableIssue();
    layoutContent($issue, 1, 6);
    $issue->pages()->where('position', 1)->first()->update(['locked_at' => now()]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('data-testid="layout-block"')
        ->assertDontSeeHtml('data-testid="content-menu-button"');
});
