<?php

use App\Enums\AdFormat;
use App\Enums\UserRole;
use App\Events\ContentAssigned;
use App\Events\PageGroupsChanged;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\Issue;
use App\Models\User;
use App\Support\ContentPanelFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// 2026-10-28: pannello contenuti sempre raggiungibile (grid.blade.php,
// partials/content-panel-item.blade.php, App\Support\ContentPanelFilter,
// Grid::assignContentToSelection()/assignContentToTarget()).
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class, PageGroupsChanged::class]));

function panelContent(Issue $issue, string $title, string $type = 'articolo', int $twelfths = 6, bool $placeholder = false, ?string $createdAt = null): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => $type, 'title' => $title, 'occupancy_twelfths' => $twelfths, 'is_placeholder' => $placeholder]);
    $type === 'articolo'
        ? Article::factory()->create(['content_id' => $content->id])
        : Advertisement::factory()->create(['content_id' => $content->id, 'format' => AdFormat::MezzaPaginaOrizzontale, 'client' => $placeholder ? null : $title]);

    if ($createdAt) {
        $content->forceFill(['created_at' => $createdAt])->save();
    }

    return $content;
}

/** Righe page_content di un contenuto: pagina, occupazione e rettangolo sulla griglia. */
function placementRows(Content $content): array
{
    return DB::table('page_content')->join('pages', 'pages.id', '=', 'page_content.page_id')
        ->where('content_id', $content->id)->orderBy('pages.position')
        ->get(['pages.position', 'occupied_twelfths', 'grid_x', 'grid_y', 'grid_w', 'grid_h'])
        ->map(fn ($row) => (array) $row)->all();
}

test('spec: assigning from the panel to a selected page gives the same occupancy and position as the classic drag', function () {
    $classicIssue = reorderableIssue();
    $panelIssue = reorderableIssue();
    $classic = panelContent($classicIssue, 'Servizio', twelfths: 18); // 1 e 1/2 pagine
    $fromPanel = panelContent($panelIssue, 'Servizio', twelfths: 18);

    Livewire::actingAs(editorFor($classicIssue))->test(Grid::class, ['issue' => $classicIssue])
        ->call('assignContent', $classic->id, $classicIssue->pages()->where('position', 3)->first()->id)
        ->assertHasNoErrors();

    Livewire::actingAs(editorFor($panelIssue))->test(Grid::class, ['issue' => $panelIssue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $panelIssue->pages()->where('position', 5)->first()->id)
        ->call('togglePageSelection', $panelIssue->pages()->where('position', 3)->first()->id)
        ->call('assignContentToSelection', $fromPanel->id)
        ->assertHasNoErrors();

    expect(placementRows($fromPanel))->toBe(placementRows($classic))
        ->and(array_column(placementRows($fromPanel), 'position'))->toBe([3, 4]);
});

test('"vai a pagina" accepts a page number or a cover, and says when the page does not exist', function () {
    $issue = reorderableIssue();
    $a = panelContent($issue, 'Alfa');
    $b = panelContent($issue, 'Beta', 'pubblicita');
    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    $grid->call('assignContentToTarget', $a->id, ' 4 ')->assertHasNoErrors();
    $grid->call('assignContentToTarget', $b->id, 'ii')->assertHasNoErrors();
    $grid->call('assignContentToTarget', $b->id, '99')->assertHasErrors('percentage');

    expect($a->fresh()->pages->pluck('position')->all())->toBe([4])
        ->and($b->fresh()->pages->first()->cover_slot->value)->toBe('II');
});

test('no space: the panel shows what occupies the page and how much is missing', function () {
    $issue = reorderableIssue();
    $full = panelContent($issue, 'Pagina piena', twelfths: 12);
    $half = panelContent($issue, 'Mezza');
    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    $grid->call('assignContentToTarget', $full->id, '2');
    $grid->call('assignContentToTarget', $half->id, '2')
        ->assertHasErrors('percentage')
        ->assertSee('Pagina piena');

    expect($half->fresh()->pages)->toBeEmpty();
});

test('spec: a commerciale assigning an article from the panel gets 403; an ad is fine', function () {
    $issue = reorderableIssue();
    $article = panelContent($issue, 'Articolo');
    $ad = panelContent($issue, 'Acme', 'pubblicita');
    $commerciale = User::factory()->create(['role' => UserRole::Commerciale]);
    $commerciale->magazines()->attach($issue->magazine);

    Livewire::actingAs($commerciale)->test(Grid::class, ['issue' => $issue])
        ->call('assignContentToTarget', $article->id, '2')->assertForbidden();
    Livewire::actingAs($commerciale)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')->call('togglePageSelection', $issue->pages()->first()->id)
        ->call('assignContentToSelection', $article->id)->assertForbidden();
    Livewire::actingAs($commerciale)->test(Grid::class, ['issue' => $issue])
        ->call('assignContentToTarget', $ad->id, '2')->assertOk();

    expect($article->fresh()->pages)->toBeEmpty()
        ->and($ad->fresh()->pages)->toHaveCount(1);
});

test('spec: the "da assegnare" counter is the same list as the panel tab (and as Issue::unassignedContents())', function () {
    $issue = reorderableIssue();
    panelContent($issue, 'Uno');
    panelContent($issue, 'Due', 'pubblicita');
    $assigned = panelContent($issue, 'Tre');
    $issue->pages()->first()->contents()->attach($assigned->id, ['occupied_twelfths' => 6]);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    expect($issue->unassignedContents()->count())->toBe(2)
        ->and($html)->toContain('Contenuti · 2 da assegnare')
        ->toContain('Da assegnare (2)')
        ->and(substr_count($html, 'data-testid="content-panel-item"'))->toBe(2);
});

test('a drop of a content already assigned by someone else is refused with a clear message', function () {
    $issue = reorderableIssue();
    $content = panelContent($issue, 'Conteso');
    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    // Un collega lo assegna alla pagina 1 mentre il pannello di questo utente è aperto.
    $issue->pages()->where('position', 1)->first()->contents()->attach($content->id, ['occupied_twelfths' => 6]);

    $grid->call('assignContent', $content->id, $issue->pages()->where('position', 4)->first()->id)
        ->assertHasErrors('percentage')
        ->assertSee('è già stato assegnato');

    expect($content->fresh()->pages->pluck('position')->all())->toBe([1]);
});

test('tabs, type filter, search and sort', function () {
    $issue = reorderableIssue();
    panelContent($issue, 'Zeta', createdAt: '2026-10-01 10:00:00');
    panelContent($issue, 'Alfa', createdAt: '2026-10-02 10:00:00');
    panelContent($issue, 'Pubblicità 1/2 — da assegnare', 'pubblicita', placeholder: true, createdAt: '2026-10-03 10:00:00');
    $placed = panelContent($issue, 'Già in pagina', createdAt: '2026-10-04 10:00:00');
    $issue->pages()->whereIn('position', [2, 3])->get()->each(fn ($page) => $page->contents()->attach($placed->id, ['occupied_twelfths' => 6]));

    $titles = fn ($component) => collect(preg_match_all('/data-testid="content-panel-item"[^>]*>.*?<span class="truncate font-medium[^"]*">([^<]+)</s', $component->html(), $m) ? $m[1] : [])->map(fn ($t) => trim(html_entity_decode($t)))->all();
    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    expect($titles($grid))->toBe(['Pubblicità 1/2 — da assegnare', 'Alfa', 'Zeta']);                 // ultimi creati in alto
    expect($titles($grid->set('contentSort', 'alpha')))->toBe(['Alfa', 'Pubblicità 1/2 — da assegnare', 'Zeta']);
    expect($titles($grid->set('contentType', 'articolo')))->toBe(['Alfa', 'Zeta']);
    expect($titles($grid->set('contentType', '')->set('contentSearch', 'zet')))->toBe(['Zeta']);
    expect($titles($grid->set('contentSearch', '')->set('contentTab', 'placeholders')))->toBe(['Pubblicità 1/2 — da assegnare']);

    $all = $grid->set('contentTab', 'all')->html();
    expect($titles($grid))->toContain('Già in pagina')
        ->and($all)->toContain('p. 2–3');
    // Nella scheda «Tutti» un contenuto già in pagina non si trascina né si assegna dal pannello.
    preg_match('/<div[^>]*data-content-id="'.$placed->id.'"[^>]*>/s', $all, $tag);
    expect($tag[0])->not->toContain('draggable="true"');
});

test('placement label lists covers and compressed page ranges', function () {
    $issue = reorderableIssue();
    $content = panelContent($issue, 'Ovunque');
    $issue->covers()->where('cover_slot', 'II')->first()->contents()->attach($content->id, ['occupied_twelfths' => 6]);
    $issue->pages()->whereIn('position', [1, 2, 3, 5])->get()->each(fn ($page) => $page->contents()->attach($content->id, ['occupied_twelfths' => 6]));

    expect(ContentPanelFilter::placementLabel($content->fresh('pages')))->toBe('II di copertina, p. 1–3, 5');
});

test('"nuovo contenuto" and bulk upload live inside the panel (one instance each)', function () {
    $issue = reorderableIssue();
    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    $panel = strpos($html, 'data-testid="content-panel"');
    $panelEnd = strpos($html, '</aside>', $panel);
    // Nei test i figli sono segnaposto con il loro wire:id: lo si ricava dalla
    // mappa dei figli nello snapshot (chiave => [tag, id]).
    $childPosition = function (string $key) use ($html) {
        preg_match('/&quot;'.preg_quote($key, '/').'&quot;:\[&quot;div&quot;,&quot;([A-Za-z0-9]+)&quot;\]/', $html, $m);

        return strpos($html, 'wire:id="'.$m[1].'"');
    };

    expect(substr_count($html, '&quot;content-create-'.$issue->id.'&quot;'))->toBe(1)
        ->and($childPosition('content-create-'.$issue->id))->toBeGreaterThan($panel)->toBeLessThan($panelEnd)
        ->and($childPosition('bulk-upload-'.$issue->id))->toBeGreaterThan($panel)->toBeLessThan($panelEnd);
});
