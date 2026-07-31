<?php

use App\Enums\ContentType;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use App\Models\Issue;
use Livewire\Livewire;

test('the grid defaults to the griglia view and shows every page', function () {
    $issue = Issue::factory()->create(['total_pages' => 6]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->assertSet('viewMode', 'griglia')
        ->assertSee('6 pagine');
});

test('switching view mode updates what is rendered', function () {
    $issue = Issue::factory()->create(['total_pages' => 4]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'lista')
        ->assertSet('viewMode', 'lista')
        ->call('setViewMode', 'doppia')
        ->assertSet('viewMode', 'doppia');
});

test('an invalid view mode is ignored', function () {
    $issue = Issue::factory()->create(['total_pages' => 4]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'not-a-real-mode')
        ->assertSet('viewMode', 'griglia');
});

test('the doppia view mode renders editable status selects and content drop targets, not the static read-only markup', function () {
    $issue = Issue::factory()->create(['total_pages' => 4]);

    $html = Livewire::test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'doppia')
        ->html();

    expect($html)->toContain('wire:change="changePageStatus')
        ->toContain('text/content-id');
});

test('the doppia view mode wraps every page in a single flat sortable container, not one per spread', function () {
    $issue = Issue::factory()->create(['total_pages' => 4]);

    $html = Livewire::test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'doppia')
        ->html();

    // Bug originale: un x-sortable per apertura (coppia di pagine)
    // rendeva trascinabile solo la coppia intera, non la singola pagina —
    // ora è un unico contenitore per l'intera vista, come in griglia/lista.
    expect(substr_count($html, 'x-sortable='))->toBe(1);

    foreach ($issue->pages()->orderBy('position')->get() as $page) {
        // Ogni pagina ha data-page-id due volte: sul wrapper che Sortable
        // trascina davvero e sulla page-card interna (invariata).
        expect(substr_count($html, 'data-page-id="'.$page->id.'"'))->toBe(2);
    }
});

test('in doppia view mode only the second page of a spread pair gets the visual separator border', function () {
    $issue = Issue::factory()->create(['total_pages' => 4]);
    $pages = $issue->pages()->orderBy('position')->get()->values();

    $html = Livewire::test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'doppia')
        ->html();

    $wrapperMarkup = function (string $html, int $pageId): string {
        preg_match('/<div\s+data-page-id="'.$pageId.'"[^>]*>/', $html, $matches);

        return $matches[0] ?? '';
    };

    // Aperture: [pagina 1] da sola (copertina), poi [pagina 2, pagina 3],
    // poi [pagina 4] — solo la pagina 3 (seconda della coppia centrale)
    // deve avere il bordo di separazione.
    expect($wrapperMarkup($html, $pages[1]->id))->not->toContain('border-l-2')
        ->and($wrapperMarkup($html, $pages[2]->id))->toContain('border-l-2');
});

test('the swap mode banner and page highlight appear only while swap mode is active', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertDontSee('Modalità scambio attiva')
        ->call('toggleSwapMode')
        ->assertSee('Modalità scambio attiva')
        ->call('selectForSwap', $page->id)
        ->assertSee('pagina selezionata');
});

test('a page shows the assigned content display label', function () {
    $issue = Issue::factory()->create(['total_pages' => 3]);
    $page = $issue->pages()->where('position', 2)->first();

    $content = Content::factory()->create([
        'issue_id' => $issue->id,
        'type' => ContentType::Articolo,
        'title' => 'Articolo di prova unico',
    ]);
    $page->contents()->attach($content->id, ['occupied_percentage' => 100]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->assertSee('Articolo di prova unico');
});

test('an unassigned blank page shows the "pagina bianca" placeholder', function () {
    $issue = Issue::factory()->create(['total_pages' => 2]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->assertSeeInOrder(['1', 'Pagina bianca']);
});
