<?php

use App\Events\ContentCreated;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Fase 3 (2026-10-27): riempimento delle pagine in dodicesimi, visibile in
// tutte le viste, e modulo "Pagine intere" + "Frazione".
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentCreated::class]));

function fillContent($issue, int $position, int $twelfths): void
{
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => $twelfths]);
    $issue->pages()->where('position', $position)->first()->contents()->attach($content->id, ['occupied_twelfths' => $twelfths]);
}

test('empty, partial and full pages are told apart with icon and text, in every view', function (string $viewMode) {
    $issue = reorderableIssue();
    fillContent($issue, 2, 8);           // 2/3 → libero 1/3
    fillContent($issue, 3, 6);           // 6 + 3 + 3 = piena
    fillContent($issue, 3, 3);
    fillContent($issue, 3, 3);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', $viewMode)
        ->assertSeeHtml('data-fill="empty"')
        ->assertSee('○ Vuota')
        ->assertSeeHtml('data-fill="partial"')
        ->assertSee('◐ Libero: 1/3')
        ->assertSeeHtml('data-fill="full"')
        ->assertSee('● Piena');
})->with(['griglia', 'doppia', 'lista']);

test('the space of each content on the page is a twelfths select, not a free percentage', function () {
    $issue = reorderableIssue();
    fillContent($issue, 1, 6);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('wire:change="updateContentOccupancy(')
        ->assertSeeHtml('<option value="6" selected>1/2</option>')
        ->assertDontSeeHtml('updateContentPercentage');
});

test('the form shows the total occupancy from whole pages and fraction, and saves it in twelfths', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Due pagine e mezza')
        ->assertSee('Indica almeno una pagina intera o una frazione')
        ->set('whole_pages', 2)
        ->set('fraction_twelfths', '6')
        ->assertSee('Occupa 2 e 1/2 pagine')
        ->call('save')
        ->assertHasNoErrors();

    expect(Content::where('title', 'Due pagine e mezza')->value('occupancy_twelfths'))->toBe(30);
});

test('a fraction alone is enough, but a fraction outside the list is refused', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Frazione non ammessa')
        ->set('fraction_twelfths', '5')
        ->call('save')
        ->assertHasErrors('fraction_twelfths')
        ->set('fraction_twelfths', '4')
        ->call('save')
        ->assertHasNoErrors();

    expect(Content::where('title', 'Frazione non ammessa')->value('occupancy_twelfths'))->toBe(4);
});
