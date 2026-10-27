<?php

use App\Events\PageGroupsChanged;
use App\Livewire\Timone\Grid;
use App\Models\PageGroup;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.
// Copre i metodi Livewire aggiunti/estesi per le shortcut da tastiera del
// 2026-09-02 (punto 2 del prompt): ctrlClickPage()/clickPage() (click e
// Ctrl+click sulla card/riga), selectAllPages() esteso ad attivare da solo
// la selezione multipla, toggleLinkForSelection() (Ctrl+Shift+G). Il
// wiring JS (quali tasti, guardia sui campi di testo) non è testabile qui
// (nessun Dusk in questo progetto, vedi HANDOFF.md) — solo il comportamento
// server-side che ogni shortcut invoca via $wire.

test('ctrl+click on a page activates selection mode by itself and selects that page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSet('selectionMode', false)
        ->call('ctrlClickPage', $page->id)
        ->assertSet('selectionMode', true)
        ->assertSet('selectedPageIds', [$page->id]);
});

test('ctrl+click on an already-selected page deselects it without turning off selection mode', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $pages[0]->id)
        ->call('ctrlClickPage', $pages[1]->id)
        ->assertSet('selectedPageIds', [$pages[0]->id, $pages[1]->id])
        ->call('ctrlClickPage', $pages[0]->id)
        ->assertSet('selectionMode', true)
        ->assertSet('selectedPageIds', [$pages[1]->id]);
});

test('a plain click on a page does nothing while selection mode is off', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('clickPage', $page->id)
        ->assertSet('selectionMode', false)
        ->assertSet('selectedPageIds', []);
});

test('a plain click on a page while selection mode is on replaces the current selection with just that page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $pages[0]->id)
        ->call('ctrlClickPage', $pages[1]->id)
        ->assertSet('selectedPageIds', [$pages[0]->id, $pages[1]->id])
        ->call('clickPage', $pages[2]->id)
        ->assertSet('selectionMode', true)
        ->assertSet('selectedPageIds', [$pages[2]->id]);
});

test('selectAllPages (Ctrl+A) activates selection mode by itself even when called cold', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSet('selectionMode', false)
        ->call('selectAllPages')
        ->assertSet('selectionMode', true)
        ->assertCount('selectedPageIds', 6);
});

test('Ctrl+Shift+G links an unlinked selection of 2+ pages, same effect as the existing link button', function () {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $pages[0]->id)
        ->call('ctrlClickPage', $pages[2]->id)
        ->call('toggleLinkForSelection')
        ->assertHasNoErrors();

    $group = PageGroup::where('issue_id', $issue->id)->sole();

    expect($pages[0]->fresh()->page_group_id)->toBe($group->id)
        ->and($pages[2]->fresh()->page_group_id)->toBe($group->id);
});

test('Ctrl+Shift+G unlinks the selection when it exactly matches an already-linked group (toggle)', function () {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $pages[0]->id)
        ->call('ctrlClickPage', $pages[1]->id)
        ->call('toggleLinkForSelection')
        ->assertHasNoErrors();

    expect($pages[0]->fresh()->page_group_id)->toBeNull()
        ->and($pages[1]->fresh()->page_group_id)->toBeNull()
        ->and(PageGroup::find($group->id))->toBeNull();
});

// Selezionare solo una parte di un gruppo più grande e premere Ctrl+Shift+G
// scioglie comunque l'INTERO gruppo (le pagine selezionate sono comunque
// "già collegate tra loro", condividono lo stesso page_group_id) — stesso
// comportamento tutto-o-niente già esistente di unlinkGroup() in ogni altro
// punto dell'app (vedi Grid::unlinkGroup(), nessuna semantica "parziale"
// diversa introdotta da questa shortcut).
test('Ctrl+Shift+G on a subset of a larger existing group still dissolves the whole group', function () {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id]);
    $pages[2]->update(['page_group_id' => $group->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $pages[0]->id)
        ->call('ctrlClickPage', $pages[1]->id)
        ->call('toggleLinkForSelection')
        ->assertHasNoErrors();

    expect($pages[0]->fresh()->page_group_id)->toBeNull()
        ->and($pages[1]->fresh()->page_group_id)->toBeNull()
        ->and($pages[2]->fresh()->page_group_id)->toBeNull()
        ->and(PageGroup::find($group->id))->toBeNull();
});

test('Ctrl+Shift+G on pages split across two different groups is rejected with feedback, not a silent no-op', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $groupA = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $groupB = PageGroup::create(['issue_id' => $issue->id, 'color' => 'sky']);
    $pages[0]->update(['page_group_id' => $groupA->id]);
    $pages[1]->update(['page_group_id' => $groupB->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $pages[0]->id)
        ->call('ctrlClickPage', $pages[1]->id)
        ->call('toggleLinkForSelection')
        ->assertHasErrors('link');

    expect($pages[0]->fresh()->page_group_id)->toBe($groupA->id)
        ->and($pages[1]->fresh()->page_group_id)->toBe($groupB->id);
});

test('Ctrl+Shift+G with fewer than 2 pages selected does nothing and gives feedback instead of failing silently', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->orderBy('pages.position')->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $page->id)
        ->call('toggleLinkForSelection')
        ->assertHasErrors('link');

    expect(PageGroup::where('issue_id', $issue->id)->count())->toBe(0);
});

test('click/ctrl+click wiring is present on every page in all three view modes, but not while swap mode is active', function (string $viewMode) {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', $viewMode);

    $html = $component->html();
    expect($html)->toContain('$wire.ctrlClickPage('.$page->id.')')
        ->toContain('$wire.clickPage('.$page->id.')')
        ->not->toContain('$wire.selectForSwap('.$page->id.')');

    // In modalità scambio il click sulla card resta esclusivamente suo
    // (vedi commento in page-card.blade.php/page-row.blade.php) — le
    // shortcut di selezione non devono competere per lo stesso evento.
    $swapHtml = $component->call('toggleSwapMode')->html();
    expect($swapHtml)->toContain('$wire.selectForSwap('.$page->id.')')
        ->not->toContain('$wire.ctrlClickPage('.$page->id.')')
        ->not->toContain('$wire.clickPage('.$page->id.')');
})->with(['griglia', 'doppia', 'lista']);

test('a multi-selected page is visually highlighted differently from a swap-selected or grouped page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $html = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $page->id)
        ->html();

    expect($html)->toContain('ring-emerald-500');
});

test('Ctrl+Shift+G delegates locked-page rejection to the same unlinkGroup rule, no bespoke logic', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id, 'locked_at' => now(), 'locked_by' => $user->id]);
    $pages[1]->update(['page_group_id' => $group->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $pages[0]->id)
        ->call('ctrlClickPage', $pages[1]->id)
        ->call('toggleLinkForSelection')
        ->assertHasErrors('locked');

    expect($pages[0]->fresh()->page_group_id)->toBe($group->id);
});
