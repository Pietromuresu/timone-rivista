<?php

use App\Enums\AdFormat;
use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\ContentListChanged;
use App\Events\PageGroupsChanged;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\ContentEdit;
use App\Models\Content;
use App\Support\ContentPlacer;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Revisione 2, Fase 2.5 (2026-10-27): «Posizione nel timone» già dalla
// creazione e dalla modifica. gridAd()/cellsOf() sono in PageGridMoveTest.php,
// reorderableIssue()/editorFor() in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class, ContentCreated::class, ContentListChanged::class, PageGroupsChanged::class]));

function createAd(object $component, string $client, AdFormat $format, string $placement): object
{
    return $component->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('title', $client)
        ->set('client', $client)
        ->set('format', $format->value)
        ->set('placement', $placement)
        ->call('save');
}

test('the form offers «da assegnare», the covers and every internal page', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->assertSeeHtml('<option value="">Da assegnare (nessuna pagina)</option>')
        ->assertSeeHtml('<option value="c:II">II di copertina</option>')
        ->assertSeeHtml('<option value="p:6">Pagina 6</option>');
});

test('a content created with a page goes there, in the first free place of the grid', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 3)->first();
    ContentPlacer::place(gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna'), $page);

    createAd(Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue]), 'Vetrina', AdFormat::UnQuartoPagina, 'p:3')
        ->assertHasNoErrors();

    $content = Content::whereHas('advertisement', fn ($q) => $q->where('client', 'Vetrina'))->firstOrFail();

    expect($content->pages()->pluck('pages.id')->all())->toBe([$page->id])
        ->and(cellsOf($page, $content))->toBe('3×3 in (3,0)');
    Event::assertDispatched(ContentAssigned::class, fn ($e) => $e->pageId === $page->id);
});

test('a multi-page content is placed from the chosen page onwards; a cover works too', function () {
    $issue = reorderableIssue();

    createAd(Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue]), 'Doppia', AdFormat::DoppiaPagina, 'p:2')
        ->assertHasNoErrors();
    createAd(Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue]), 'Copertina', AdFormat::PaginaIntera, 'c:IV')
        ->assertHasNoErrors();

    $double = Content::whereHas('advertisement', fn ($q) => $q->where('client', 'Doppia'))->firstOrFail();
    $cover = Content::whereHas('advertisement', fn ($q) => $q->where('client', 'Copertina'))->firstOrFail();

    expect($double->pages()->orderBy('pages.position')->pluck('pages.position')->all())->toBe([2, 3])
        ->and($cover->pages()->first()->cover_slot->value)->toBe('IV');
});

test('without space: an error that says what is on the page and how much is missing, and nothing is saved', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 4)->first();
    ContentPlacer::place(gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Acme'), $page);
    ContentPlacer::place(gridAd($issue, AdFormat::UnQuartoPagina, 'Beta'), $page);
    $before = Content::count();

    createAd(Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue]), 'Nuova', AdFormat::MezzaPaginaOrizzontale, 'p:4')
        ->assertHasErrors('placement')
        ->assertSee('Sulla pagina ci sono già «Acme» (1/2) e «Beta» (1/4): manca 1/4 di pagina.');

    expect(Content::count())->toBe($before);
});

test('empty placement keeps today\'s behaviour: the content is created unassigned', function () {
    $issue = reorderableIssue();

    createAd(Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue]), 'Libera', AdFormat::UnQuartoPagina, '')
        ->assertHasNoErrors();

    expect($issue->unassignedContents()->count())->toBe(1);
});

test('the edit modal shows the current position and moves the content from the new page', function () {
    $issue = reorderableIssue();
    $content = gridAd($issue, AdFormat::UnQuartoPagina, 'Spostami');
    ContentPlacer::place($content, $issue->pages()->where('position', 1)->first());

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->assertSet('placement', 'p:1')
        ->set('placement', 'p:5')
        ->call('save')
        ->assertHasNoErrors();

    expect($content->pages()->pluck('pages.position')->all())->toBe([5]);
});

test('the edit modal can put a content back among the unassigned, and refuses a full page without changing anything', function () {
    $issue = reorderableIssue();
    $content = gridAd($issue, AdFormat::MezzaPaginaOrizzontale, 'Mezza');
    ContentPlacer::place($content, $issue->pages()->where('position', 1)->first());
    ContentPlacer::place(gridAd($issue, AdFormat::PaginaIntera, 'Piena'), $issue->pages()->where('position', 2)->first());

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('placement', 'p:2')
        ->call('save')
        ->assertHasErrors('placement');

    expect($content->pages()->pluck('pages.position')->all())->toBe([1]);

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('placement', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($content->pages()->count())->toBe(0);
});
