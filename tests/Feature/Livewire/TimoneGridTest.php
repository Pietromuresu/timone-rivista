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
