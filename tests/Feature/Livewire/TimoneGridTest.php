<?php

use App\Enums\ContentType;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use App\Models\Issue;
use Livewire\Livewire;

test('the grid defaults to the griglia view and shows every page', function () {
    $issue = Issue::factory()->create(['total_pages' => 6]);

    // Fase 2 (2026-10-26): pagine interne e copertine contate a parte.
    Livewire::test(Grid::class, ['issue' => $issue])
        ->assertSet('viewMode', 'griglia')
        ->assertSee('6 pagine interne')
        ->assertSee('copertine 4');
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
        // Bug reale trovato il 2026-09-02 (segnalato dall'utente: il drag
        // in doppia non si avviava affatto): questo test asserisce ora
        // l'INVARIANTE CORRETTO, prima asserisce esattamente il difetto
        // ("data-page-id due volte, wrapper + page-card annidata"). Un
        // div-wrapper esterno con un proprio data-page-id fa sì che
        // SortableJS (closest('[data-page-id]') da resources/js/app.js)
        // risalga dal target del click e si fermi al PRIMO match — il
        // root di page-card, non il wrapper — il cui parentNode non è più
        // il contenitore Sortable (Sortable.js:566): il drag abortisce in
        // silenzio. page-card resta quindi l'unico elemento con
        // data-page-id, esattamente come in griglia/lista, e va trovato
        // una sola volta.
        expect(substr_count($html, 'data-page-id="'.$page->id.'"'))->toBe(1);

        // Stesso wire:key già usato (e mai stato il problema) in
        // griglia/lista — nessun wrapper "page-wrap-" separato: page-card
        // è di nuovo l'unico root, figlio diretto del contenitore Sortable.
        expect($html)->toContain('wire:key="page-'.$page->id.'"')
            ->not->toContain('wire:key="page-wrap-'.$page->id.'"');
    }
});

test('in doppia view mode only the second page of a spread pair gets the visual separator border', function () {
    $issue = Issue::factory()->create(['total_pages' => 4]);
    $pages = $issue->pages()->orderBy('position')->get()->values();

    $html = Livewire::test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'doppia')
        ->html();

    $wrapperMarkup = function (string $html, int $pageId): string {
        // Il wrapper ora ha anche wire:key PRIMA di data-page-id (bugfix
        // drag&drop doppia pagina, vedi HANDOFF.md) — il pattern non deve
        // più assumere data-page-id come primo attributo dopo <div.
        preg_match('/<div\s+[^>]*data-page-id="'.$pageId.'"[^>]*>/', $html, $matches);

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
    $page->contents()->attach($content->id, ['occupied_twelfths' => 12]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->assertSee('Articolo di prova unico');
});

test('an unassigned blank page shows the "pagina bianca" placeholder', function () {
    $issue = Issue::factory()->create(['total_pages' => 2]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->assertSeeInOrder(['1', 'Pagina bianca']);
});

test('internal pages are numbered from 1 and the four covers are shown apart with their names', function () {
    // Fase 2 (2026-10-26): 4 pagine interne (1-4) + copertine I-IV in una
    // fascia separata prima della pagina 1.
    $issue = Issue::factory()->create(['total_pages' => 4]);
    $pages = $issue->pages()->get();

    $html = Livewire::test(Grid::class, ['issue' => $issue])->html();

    // Isola il blocco di ogni card via il suo wire:key univoco.
    $cardBlock = function (string $html, int $pageId): string {
        $markerPos = mb_strpos($html, 'wire:key="page-'.$pageId.'"');
        expect($markerPos)->not->toBeFalse();

        return mb_substr($html, $markerPos, 4000);
    };

    expect($pages->pluck('position')->all())->toBe([1, 2, 3, 4])
        ->and($cardBlock($html, $pages[0]->id))->toContain('⠿ 1')
        ->and($cardBlock($html, $pages[3]->id))->toContain('⠿ 4');

    // Tutte le copertine in un'unica fascia PRIMA della pagina 1, in ordine di sfoglio.
    $bandStart = mb_strpos($html, 'data-testid="cover-band"');
    $firstPage = mb_strpos($html, 'wire:key="page-'.$pages[0]->id.'"');
    $band = mb_substr($html, $bandStart, $firstPage - $bandStart);

    expect($bandStart)->toBeLessThan($firstPage)
        ->and(substr_count($html, 'data-testid="cover-band"'))->toBe(1)
        ->and($band)->toContain('📘 I di copertina')->toContain('📘 IV di copertina')
        ->and(mb_strpos($band, 'I di copertina'))->toBeLessThan(mb_strpos($band, 'II di copertina'))
        ->and(mb_strpos($band, 'III di copertina'))->toBeLessThan(mb_strpos($band, 'IV di copertina'));
});
