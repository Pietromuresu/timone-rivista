<?php

use App\Enums\PageStatus;
use App\Livewire\Timone\Grid;
use App\Models\Issue;
use Livewire\Livewire;

// Fase 4 (§4): verifica che il bordo di stato e lo sfondo di tipo pagina
// arrivino davvero nel markup, non solo che gli enum restituiscano le
// classi giuste (già coperto da PageColorPaletteTest, puro/senza render).

test('the griglia view applies the status border and content type background to each card', function () {
    $issue = Issue::factory()->create(['total_pages' => 2]);
    $page = $issue->pages()->where('position', 1)->first();
    $page->update(['status' => PageStatus::Revisionata]);

    $html = Livewire::test(Grid::class, ['issue' => $issue])->html();

    expect($html)->toContain('border-orange-500') // PageStatus::Revisionata->borderClasses()
        ->toContain('bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'); // PageContentType::Bianca->colorClasses() (pagine bianche di default)
});

test('the lista view applies a left status border to each row', function () {
    $issue = Issue::factory()->create(['total_pages' => 2]);
    $page = $issue->pages()->where('position', 1)->first();
    $page->update(['status' => PageStatus::OkStampa]);

    $html = Livewire::test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'lista')
        ->html();

    expect($html)->toContain('border-l-4')
        ->toContain('border-green-500'); // PageStatus::OkStampa->borderClasses()
});

test('a pubblicità page is visually distinct from an editorial one via its background', function () {
    $issue = Issue::factory()->create(['total_pages' => 2]);
    $adPage = $issue->pages()->where('position', 1)->first();
    $adPage->update(['content_type' => \App\Enums\PageContentType::Pubblicita]);
    $editorialPage = $issue->pages()->where('position', 2)->first();
    $editorialPage->update(['content_type' => \App\Enums\PageContentType::Editoriale]);

    $html = Livewire::test(Grid::class, ['issue' => $issue])->html();

    expect($html)->toContain('bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200')
        ->toContain('bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200');
});
