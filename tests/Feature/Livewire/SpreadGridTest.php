<?php

use App\Enums\UserRole;
use App\Events\IssuePageCountUpdated;
use App\Events\PageMoved;
use App\Events\PagesBlockMoved;
use App\Livewire\Timone\Grid;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Griglia a doppia pagina (2026-10-29): accoppiamento dalla posizione
// (PageSpreadBuilder::layout(), test di unità in tests/Unit/PageSpreadBuilderTest.php),
// qui il markup della vista «Doppia pagina» e il suo comportamento dopo spostamenti.

beforeEach(fn () => Event::fake([PageMoved::class, PagesBlockMoved::class, IssuePageCountUpdated::class]));

function spreadIssue(int $pages): Issue
{
    return Issue::factory()->create(['magazine_id' => Magazine::factory()->create()->id, 'total_pages' => $pages]);
}

function spreadEditor(Issue $issue): User
{
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($issue->magazine);

    return $user;
}

/**
 * Figli del contenitore della griglia a doppia pagina, in ordine: la
 * posizione della pagina, «—L»/«—R» per gli slot vuoti. Le coppie sono
 * gli elementi a due a due (lato in data-spread-side, vedi il test sotto).
 */
function spreadChildren(string $html, Issue $issue): array
{
    preg_match('/data-testid="spread-grid".*?>(.*)<\/div>\s*<\/div>/s', $html, $grid);
    preg_match_all('/<div\s+wire:key="(?:page-(\d+)|spread-empty-\d+-(\d))"/', $grid[1], $matches, PREG_SET_ORDER);
    $positions = $issue->allPages()->pluck('position', 'id');

    return array_map(fn ($m) => ($m[1] ?? '') !== '' ? (int) $positions[(int) $m[1]] : ($m[2] === '0' ? '—L' : '—R'), $matches);
}

test('odd total: [II cover slot | 1], [2 | 3], [4 | 5]; empty slot marked', function () {
    $issue = spreadIssue(5);
    $html = Livewire::actingAs(spreadEditor($issue))->test(Grid::class, ['issue' => $issue])->call('setViewMode', 'doppia')->html();

    expect(spreadChildren($html, $issue))->toBe(['—L', 1, 2, 3, 4, 5])
        ->and(substr_count($html, 'data-testid="spread-empty"'))->toBe(1)
        ->and($html)->toContain('data-spread-empty="left"')->toContain('II di copertina');
});

test('even total: the last page on the left facing the III cover slot', function () {
    $issue = spreadIssue(6);
    $html = Livewire::actingAs(spreadEditor($issue))->test(Grid::class, ['issue' => $issue])->call('setViewMode', 'doppia')->html();

    expect(spreadChildren($html, $issue))->toBe(['—L', 1, 2, 3, 4, 5, 6, '—R'])
        ->and($html)->toContain('III di copertina');
});

test('after moving a page the pairing is recomputed: the following pages change side', function () {
    $issue = spreadIssue(6);
    $first = $issue->pages()->where('position', 1)->first();

    $grid = Livewire::actingAs(spreadEditor($issue))->test(Grid::class, ['issue' => $issue])->call('setViewMode', 'doppia')
        ->call('movePage', $first->id, 6);

    $html = $grid->html();
    expect(spreadChildren($html, $issue->fresh()))->toBe(['—L', 1, 2, 3, 4, 5, 6, '—R'])
        // La vecchia pagina 1 (destra) ora è la 6, a sinistra davanti alla III.
        ->and(preg_match('/wire:key="page-'.$first->id.'".*?wire:key="spread-empty-\d+-1"/s', $html))->toBe(1)
        ->and($first->fresh()->position)->toBe(6);
});

test('after inserting and deleting pages the pairs follow the new total', function () {
    $issue = spreadIssue(6);
    $grid = Livewire::actingAs(spreadEditor($issue))->test(Grid::class, ['issue' => $issue])->call('setViewMode', 'doppia');

    $grid->set('newTotalPages', '7')->call('resizePages');
    expect(spreadChildren($grid->html(), $issue->fresh()))->toBe(['—L', 1, 2, 3, 4, 5, 6, 7]);

    $grid->set('newTotalPages', '4')->call('resizePages', true);
    expect(spreadChildren($grid->html(), $issue->fresh()))->toBe(['—L', 1, 2, 3, 4, '—R']);
});

test('a multi-page selection dropped in the double-page grid moves all the selected pages', function () {
    $issue = spreadIssue(8);
    $pages = $issue->pages()->orderBy('position')->get();
    $selected = [$pages[1]->id, $pages[2]->id, $pages[3]->id];

    $grid = Livewire::actingAs(spreadEditor($issue))->test(Grid::class, ['issue' => $issue])->call('setViewMode', 'doppia')
        ->set('selectionMode', true)->set('selectedPageIds', $selected)
        ->call('moveSelectedBlock', $pages[1]->id, 5)->assertHasNoErrors();

    // Il blocco 2–4 parte dalla posizione 5: 1, 5, 6, 7, poi 2, 3, 4, infine 8.
    expect($issue->pages()->orderBy('position')->pluck('id')->all())
        ->toBe([$pages[0]->id, $pages[4]->id, $pages[5]->id, $pages[6]->id, $pages[1]->id, $pages[2]->id, $pages[3]->id, $pages[7]->id])
        ->and(spreadChildren($grid->html(), $issue->fresh()))->toBe(['—L', 1, 2, 3, 4, 5, 6, 7, 8, '—R']);
});

test('every page in the double-page grid is a direct child of the Sortable container (no wrappers)', function () {
    $issue = spreadIssue(4);
    $html = Livewire::actingAs(spreadEditor($issue))->test(Grid::class, ['issue' => $issue])->call('setViewMode', 'doppia')->html();

    preg_match('/data-testid="spread-grid"[^>]*>(.*)$/s', $html, $after);
    // Il primo figlio è lo slot vuoto, poi subito la prima page-card: nessun div attorno.
    expect(preg_match('/^\s*(<!--.*?-->\s*)*<div\s+wire:key="spread-empty-0-0"/s', $after[1]))->toBe(1)
        ->and($html)->toContain('x-sortable="$wire.swapMode"')
        ->and($html)->not->toContain('basis-full');
});

test('each page and empty slot carries its side from the server: even left, odd right', function () {
    $issue = spreadIssue(5);
    $html = Livewire::actingAs(spreadEditor($issue))->test(Grid::class, ['issue' => $issue])->call('setViewMode', 'doppia')->html();

    // Solo dentro la griglia: le copertine (fascia in alto) non hanno il lato.
    $spreadGrid = substr($html, strpos($html, 'data-testid="spread-grid"'));
    preg_match_all('/<div\s+wire:key="(?:page-(\d+)|spread-empty-\d+-\d)"[^>]*?data-spread-side="(left|right)"/s', $spreadGrid, $matches, PREG_SET_ORDER);
    $positions = $issue->pages()->pluck('position', 'id');

    expect(array_map(fn ($m) => (($m[1] ?? '') !== '' ? $positions[(int) $m[1]] : '—').':'.$m[2], $matches))
        ->toBe(['—:left', '1:right', '2:left', '3:right', '4:left', '5:right']);

    // Nelle altre viste la card non ha il lato.
    $griglia = Livewire::actingAs(spreadEditor($issue))->test(Grid::class, ['issue' => $issue])->html();
    expect($griglia)->not->toContain('data-spread-side');
});
