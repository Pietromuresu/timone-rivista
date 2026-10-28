<?php

use App\Enums\UserRole;
use App\Livewire\Timone\Grid;
use App\Models\Page;
use App\Models\User;
use App\Support\PageGroupLinker;
use App\Support\PageSelectionActions;
use Livewire\Livewire;

// 2026-10-28: azioni sulla selezione di pagine nella fascia sticky
// (partials/selection-bar.blade.php, App\Support\PageSelectionActions).
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

test('positions are compressed into ranges and long lists are shortened', function () {
    expect(PageSelectionActions::ranges([14, 12, 13]))->toBe('12–14')
        ->and(PageSelectionActions::ranges([2, 5, 6, 7, 9]))->toBe('2, 5–7, 9')
        ->and(PageSelectionActions::ranges([1, 3, 5, 7, 9, 11, 13]))->toBe('1, 3, 5, 7, … e altre 3')
        ->and(PageSelectionActions::ranges([]))->toBe('');
});

test('each action says why it is not available', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $none = PageSelectionActions::describe(collect());
    $one = PageSelectionActions::describe($pages->take(1));

    PageGroupLinker::link($issue, $pages->take(2));
    $pages[2]->update(['locked_at' => now()]);
    $grouped = PageSelectionActions::describe($issue->pages()->orderBy('pages.position')->get()->take(2));
    $withLocked = PageSelectionActions::describe($issue->pages()->orderBy('pages.position')->get()->slice(2, 2));

    expect($none['actions'])->toBe(['status' => 'Seleziona almeno una pagina', 'lock' => 'Seleziona almeno una pagina', 'unlock' => 'Seleziona almeno una pagina', 'link' => 'Seleziona almeno 2 pagine da collegare', 'unlink' => 'Seleziona almeno una pagina'])
        ->and($one['actions']['link'])->toBe('Seleziona almeno 2 pagine da collegare')
        ->and($one['actions']['unlink'])->toBe('Nessuna pagina selezionata fa parte di un gruppo')
        ->and($one['actions']['unlock'])->toBe('Nessuna pagina selezionata è bloccata')
        ->and($one['actions']['lock'])->toBeNull()
        ->and($grouped['actions']['link'])->toBe('Sono già collegate in un gruppo')
        ->and($grouped['actions']['unlink'])->toBeNull()
        ->and($withLocked['actions']['link'])->toBe('Una o più pagine selezionate sono bloccate: sbloccale prima')
        ->and($withLocked['actions']['unlock'])->toBeNull();
});

test('the selection bar is inside the sticky band, with label, positions and the action buttons in fixed places', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $component = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->assertSee('Seleziona una o più pagine');

    foreach ([$pages[3], $pages[4], $pages[5]] as $page) {
        $component->call('togglePageSelection', $page->id);
    }

    $html = $component->html();
    $band = strpos($html, 'data-testid="timone-sticky-band"');
    $bar = strpos($html, 'data-testid="selection-bar"');
    $bandEnd = strpos($html, '/timone-sticky-band') ?: strpos($html, '</template>', $bar);

    expect($bar)->toBeGreaterThan($band)->toBeLessThan($bandEnd)
        ->and($html)->toContain('3 pagine selezionate: 4–6')
        ->toContain('wire:click="linkSelectedPages"')
        ->toContain('data-action="unlink" data-disabled="1"')
        ->toContain('title="Deseleziona tutte (Esc)"')
        ->toContain('(Ctrl/⌘+Shift+G)');
});

test('a commerciale only sees select and deselect in the selection bar', function () {
    $issue = reorderableIssue();
    $commerciale = User::factory()->create(['role' => UserRole::Commerciale]);
    $commerciale->magazines()->attach($issue->magazine);

    $html = Livewire::actingAs($commerciale)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $issue->pages()->first()->id)
        ->html();

    expect($html)->toContain('data-action="clear"')->toContain('data-action="all"')
        ->not->toContain('data-action="link"')
        ->not->toContain('data-action="lock"')
        ->not->toContain('data-action="status"');
});

test('pages deleted by someone else disappear from the selection at the next render', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $component = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[4]->id)
        ->call('togglePageSelection', $pages[5]->id);

    // Un collega riduce il numero: le ultime pagine spariscono.
    Page::whereKey($pages[5]->id)->delete();
    $issue->markPagesUpdated();

    $component->call('checkForUpdates')
        ->assertSet('selectedPageIds', [$pages[4]->id])
        ->assertSee('1 pagina selezionata: 5');
});

test('the band has the collapse button and the compact summary', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('data-testid="sticky-collapse"')
        ->assertSeeHtml('data-testid="summary-compact"')
        ->assertSeeHtml("localStorage.setItem('timone.stickyCollapsed'");
});
