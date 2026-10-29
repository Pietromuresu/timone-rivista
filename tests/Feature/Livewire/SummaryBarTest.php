<?php

use App\Enums\AdFormat;
use App\Enums\CoverSlot;
use App\Events\ContentAssigned;
use App\Events\ContentListChanged;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\Issue;
use App\Support\IssueSummary;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Fase 6 (2026-10-27): barra di riepilogo stile dashboard.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php (6 pagine interne).

beforeEach(fn () => Event::fake([ContentAssigned::class, ContentListChanged::class]));

function summaryContent(Issue $issue, int $twelfths, bool $ad = false, bool $placeholder = false, ?AdFormat $format = null): Content
{
    $content = Content::factory()->create([
        'issue_id' => $issue->id,
        'type' => $ad ? 'pubblicita' : 'articolo',
        'occupancy_twelfths' => $twelfths,
        'is_placeholder' => $placeholder,
    ]);

    if ($ad) {
        Advertisement::factory()->create(['content_id' => $content->id, 'format' => $format ?? AdFormat::PaginaIntera, 'client' => $placeholder ? null : 'Cliente']);
    }

    return $content;
}

function putOn(Issue $issue, Content $content, int $twelfths, ?int $position = null, ?CoverSlot $cover = null): void
{
    $page = $cover !== null
        ? $issue->covers()->where('cover_slot', $cover->value)->first()
        : $issue->pages()->where('position', $position)->first();
    $page->contents()->attach($content->id, ['occupied_twelfths' => $twelfths]);
}

/**
 * Numero ad hoc, 6 pagine interne:
 * 1 piena (articolo 12), 2 piena (6 + 3 + 3), 3 parziale (segnaposto 1/2),
 * 4 parziale (articolo 2/3), 5 e 6 bianche; II di copertina con un
 * segnaposto a pagina intera; un segnaposto 1/4 non assegnato.
 */
function adHocIssue(): Issue
{
    $issue = reorderableIssue();
    putOn($issue, summaryContent($issue, 12), 12, 1);
    putOn($issue, summaryContent($issue, 6), 6, 2);
    putOn($issue, summaryContent($issue, 3, ad: true, format: AdFormat::UnQuartoPagina), 3, 2);
    putOn($issue, summaryContent($issue, 3), 3, 2);
    putOn($issue, summaryContent($issue, 6, ad: true, placeholder: true, format: AdFormat::MezzaPaginaOrizzontale), 6, 3);
    putOn($issue, summaryContent($issue, 8), 8, 4);
    putOn($issue, summaryContent($issue, 12, ad: true, placeholder: true), 12, cover: CoverSlot::II);
    summaryContent($issue, 3, ad: true, placeholder: true, format: AdFormat::UnQuartoPagina);

    return $issue;
}

// Test richiesto dalla specifica: conteggi bianche/parziali/da assegnare.
test('the summary counts full, partial and blank pages, free space and placeholders on an ad-hoc issue', function () {
    $issue = adHocIssue();
    $pages = $issue->pages()->with('contents.advertisement')->get();
    $covers = $issue->covers()->with('contents.advertisement')->get();
    $unassigned = $issue->unassignedContents()->with('advertisement')->get();

    $summary = IssueSummary::summarize($pages, $covers, $unassigned, ['adLoadPercentage' => 0.0], null);

    expect($summary['internalPages'])->toBe(6)
        ->and($summary['fullPages'])->toBe(2)
        ->and($summary['partialPages'])->toBe(2)
        ->and($summary['blankPages'])->toBe(2)
        // liberi: 0 + 0 + 6 + 4 + 12 + 12 = 34 dodicesimi
        ->and($summary['freeTwelfths'])->toBe(34)
        ->and($summary['freeLabel'])->toBe('2 e 5/6 pagine')
        ->and($summary['placeholderCount'])->toBe(3)
        ->and($summary['placeholderByFormat'])->toBe(['mezza_pagina_orizzontale' => 1, 'pagina_intera' => 1, 'un_quarto_pagina' => 1])
        ->and($summary['coversTotal'])->toBe(4)
        ->and($summary['coversAssigned'])->toBe(1)
        ->and($summary['blankPageIds'])->toBe($issue->pages()->whereIn('position', [5, 6])->orderBy('position')->pluck('id')->all())
        ->and($summary['placeholderPageIds'])->toHaveCount(2);
});

test('the summary bar shows the counts, the ad percentage and the cover status', function () {
    $issue = adHocIssue();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('data-testid="summary-bar"')
        ->assertSee('6 pagine interne')
        ->assertSee('2 piene')
        ->assertSee('2 parziali')
        ->assertSeeHtml('<strong>2</strong> bianche')
        ->assertSeeHtml('Spazio libero: <strong>2 e 5/6 pagine</strong>')
        // pubblicità sulle interne: 3 (vetrina) + 6 (segnaposto) + 3 prenotata = 12 su 72 = 16.67%
        ->assertSee('16.67%')
        ->assertSeeHtml('Segnaposto: <strong>3</strong>')
        ->assertSeeHtml('Copertine: <strong>1</strong>/4 assegnate')
        ->assertSee('(3 libere)');
});

test('clicking "bianche" highlights the blank pages and dims the others; clicking again turns it off', function () {
    $issue = adHocIssue();
    $blankIds = $issue->pages()->whereIn('position', [5, 6])->pluck('id');

    $component = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('toggleSummaryHighlight', 'blank')
        ->assertSet('summaryHighlight', 'blank');

    $html = $component->html();
    expect(substr_count($html, 'data-highlighted="1"'))->toBe(2);
    foreach ($blankIds as $id) {
        expect($html)->toMatch('/data-page-id="'.$id.'"[^>]*?\s*[\s\S]{0,4000}?data-highlighted="1"/');
    }

    $component->call('toggleSummaryHighlight', 'blank')->assertSet('summaryHighlight', null);
    expect(substr_count($component->html(), 'data-highlighted="1"'))->toBe(0);
});

test('clicking "da assegnare" highlights the pages and covers with placeholders, in every view', function (string $viewMode) {
    $issue = adHocIssue();

    $component = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', $viewMode)
        ->call('toggleSummaryHighlight', 'placeholders');

    expect(substr_count($component->html(), 'data-highlighted="1"'))->toBe(2);
})->with(['griglia', 'doppia', 'lista']);

test('an unknown highlight is ignored', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('toggleSummaryHighlight', 'tutto')
        ->assertSet('summaryHighlight', null);
});

// Bug preesistente corretto nella Fase 6: la ricerca nel pannello cambiava
// il carico pubblicitario (le prenotate venivano dalla lista filtrata).
test('searching the unassigned panel does not change the ad percentage', function () {
    $issue = reorderableIssue();
    summaryContent($issue, 12, ad: true); // prenotata, 1 pagina su 6

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSee('16.67%')
        ->set('contentSearch', 'nessun titolo corrisponde')
        ->assertSee('16.67%')
        // 2026-10-28: pannello contenuti — la ricerca filtra l'elenco, non il contatore.
        ->assertSee('Nessun contenuto trovato per «nessun titolo corrisponde»', false)
        ->assertSee('Contenuti · 1 da assegnare');
});
