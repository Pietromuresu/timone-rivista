<?php

use App\Enums\UserRole;
use App\Events\PageGroupsChanged;
use App\Events\PagesBlockMoved;
use App\Livewire\Timone\Grid;
use App\Models\PageGroup;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.

// Punto 3 del prompt "modifiche timone" (2026-09-02) chiedeva di aggiungere
// lo scollegamento anche in griglia e doppia pagina, presumendo esistesse
// solo in lista: verificato che page-card.blade.php (condiviso da griglia
// E doppia, non duplicato per vista) ha già lo stesso bottone 🔗
// wire:click="unlinkGroup(...)" di page-row.blade.php (lista) — nessun
// codice nuovo necessario, solo la copertura di test mancante per
// affermarlo esplicitamente (prima questo test copriva solo griglia+lista,
// mai doppia esplicitamente: la lacuna era nel test, non nel prodotto).
test('a linked page renders the same unlink trigger in all three view modes, an unlinked one does not', function (string $viewMode) {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id]);

    $html = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', $viewMode)
        ->html();

    expect(substr_count($html, 'wire:click="unlinkGroup('.$pages[0]->id.')"'))->toBe(1)
        ->and(substr_count($html, 'wire:click="unlinkGroup('.$pages[2]->id.')"'))->toBe(0);
})->with(['griglia', 'doppia', 'lista']);

// Stesso principio del test sopra ma end-to-end: il click sul bottone 🔗 in
// griglia/doppia produce esattamente lo stesso risultato finale di lista
// (stesso Grid::unlinkGroup(), mai una logica alternativa per vista).
test('clicking the unlink trigger produces the identical end result in every view mode', function (string $viewMode) {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', $viewMode)
        ->call('unlinkGroup', $pages[0]->id)
        ->assertHasNoErrors();

    expect($pages[0]->fresh()->page_group_id)->toBeNull()
        ->and($pages[1]->fresh()->page_group_id)->toBeNull()
        ->and(PageGroup::find($group->id))->toBeNull();
})->with(['griglia', 'doppia', 'lista']);

test('linking two or more selected pages creates a group and colors them', function () {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('togglePageSelection', $pages[2]->id)
        ->call('linkSelectedPages')
        ->assertHasNoErrors();

    $group = PageGroup::where('issue_id', $issue->id)->sole();

    expect($pages[0]->fresh()->page_group_id)->toBe($group->id)
        ->and($pages[2]->fresh()->page_group_id)->toBe($group->id)
        ->and($pages[1]->fresh()->page_group_id)->toBeNull();

    Event::assertDispatched(PageGroupsChanged::class, fn (PageGroupsChanged $e) => $e->issueId === $issue->id);
});

test('linking fewer than two selected pages does nothing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->orderBy('pages.position')->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $page->id)
        ->call('linkSelectedPages');

    expect(PageGroup::where('issue_id', $issue->id)->count())->toBe(0);
});

test('linking a page already in another group is rejected without changing anything', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $existingGroup = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $existingGroup->id]);
    $pages[1]->update(['page_group_id' => $existingGroup->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('togglePageSelection', $pages[3]->id)
        ->call('linkSelectedPages')
        ->assertHasErrors('link');

    expect(PageGroup::where('issue_id', $issue->id)->count())->toBe(1)
        ->and($pages[3]->fresh()->page_group_id)->toBeNull();
});

test('linking a selection that includes a locked page is rejected entirely', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();
    $pages[0]->update(['locked_at' => now(), 'locked_by' => $user->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('togglePageSelection', $pages[1]->id)
        ->call('linkSelectedPages')
        ->assertHasErrors('locked');

    expect(PageGroup::where('issue_id', $issue->id)->count())->toBe(0);
});

test('unlinking a group dissolves it entirely, not just the page passed in', function () {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id]);
    $pages[2]->update(['page_group_id' => $group->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('unlinkGroup', $pages[1]->id)
        ->assertHasNoErrors();

    expect($pages[0]->fresh()->page_group_id)->toBeNull()
        ->and($pages[1]->fresh()->page_group_id)->toBeNull()
        ->and($pages[2]->fresh()->page_group_id)->toBeNull()
        ->and(PageGroup::find($group->id))->toBeNull();

    Event::assertDispatched(PageGroupsChanged::class);
});

test('unlinking a group with a locked page is rejected and nothing changes', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id, 'locked_at' => now(), 'locked_by' => $user->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('unlinkGroup', $pages[0]->id)
        ->assertHasErrors('locked');

    expect($pages[0]->fresh()->page_group_id)->toBe($group->id)
        ->and(PageGroup::find($group->id))->not->toBeNull();
});

// Punto 4.6 del prompt (2026-09-02, deciso esplicitamente con l'utente):
// scollegamento SELETTIVO — rimuove solo le pagine selezionate da un
// gruppo, lasciando le altre ancora collegate. A differenza di unlinkGroup()
// sopra (tutto-o-niente), generale e riusabile anche per gruppi collegati
// manualmente (☑️ Selezione multipla + 🔗), non solo quelli da un contenuto
// PDF multi-pagina (Punto 4.5).
test('unlinking selected pages from a group of 3 removes only them, the rest stays linked', function () {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id]);
    $pages[2]->update(['page_group_id' => $group->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('unlinkSelectedFromGroup')
        ->assertHasNoErrors();

    expect($pages[0]->fresh()->page_group_id)->toBeNull()
        ->and($pages[1]->fresh()->page_group_id)->toBe($group->id)
        ->and($pages[2]->fresh()->page_group_id)->toBe($group->id)
        ->and(PageGroup::find($group->id))->not->toBeNull();
});

test('unlinking selected pages down to fewer than 2 remaining dissolves the group entirely', function () {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('unlinkSelectedFromGroup');

    expect($pages[0]->fresh()->page_group_id)->toBeNull()
        ->and($pages[1]->fresh()->page_group_id)->toBeNull()
        ->and(PageGroup::find($group->id))->toBeNull();
});

test('unlinking a selection with no grouped pages does nothing and gives feedback instead of failing silently', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->orderBy('pages.position')->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $page->id)
        ->call('unlinkSelectedFromGroup')
        ->assertHasErrors('link');
});

test('unlinking selected pages from a group is rejected if any of them is locked, the rest of the group is untouched', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id, 'locked_at' => now(), 'locked_by' => $user->id]);
    $pages[1]->update(['page_group_id' => $group->id]);
    $pages[2]->update(['page_group_id' => $group->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('unlinkSelectedFromGroup')
        ->assertHasErrors('locked');

    expect($pages[0]->fresh()->page_group_id)->toBe($group->id)
        ->and($pages[1]->fresh()->page_group_id)->toBe($group->id)
        ->and($pages[2]->fresh()->page_group_id)->toBe($group->id);
});

// Non richiede una pagina bloccata NON selezionata: solo le pagine
// EFFETTIVAMENTE da rimuovere devono essere sbloccate (a differenza di
// unlinkGroup(), che modifica tutte le pagine del gruppo e le richiede
// tutte sbloccate) — vedi App\Support\PageGroupLinker::removeFromGroup().
test('unlinking a selected page from a group succeeds even if another (non-selected) member of that group is locked', function () {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id, 'locked_at' => now(), 'locked_by' => $user->id]);
    $pages[2]->update(['page_group_id' => $group->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('unlinkSelectedFromGroup')
        ->assertHasNoErrors();

    expect($pages[0]->fresh()->page_group_id)->toBeNull()
        ->and($pages[1]->fresh()->page_group_id)->toBe($group->id)
        ->and($pages[2]->fresh()->page_group_id)->toBe($group->id);
});

test('a guest cannot unlink selected pages from a group', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id]);
    $pages[2]->update(['page_group_id' => $group->id]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('unlinkSelectedFromGroup');

    expect($pages[0]->fresh()->page_group_id)->toBe($group->id);
});

test('the "scollega selezionate dal gruppo" button appears in the selection toolbar', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->orderBy('pages.position')->first();

    $html = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $page->id)
        ->html();

    expect($html)->toContain('wire:click="unlinkSelectedFromGroup"');
});

test('a guest cannot link or unlink pages', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('togglePageSelection', $pages[1]->id)
        ->call('linkSelectedPages');

    expect(PageGroup::where('issue_id', $issue->id)->count())->toBe(0);
});

test('a sola lettura user cannot link pages', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('togglePageSelection', $pages[1]->id)
        ->call('linkSelectedPages');

    expect(PageGroup::where('issue_id', $issue->id)->count())->toBe(0);
});

// Integrazione con 2.1/2.2 (progettata insieme, non aggiunta sopra a
// posteriori): trascinare una SOLA pagina di un gruppo, senza alcuna
// selezione multipla attiva, sposta l'intero gruppo insieme, tramite lo
// stesso identico meccanismo di moveSelectedBlock() — non un algoritmo
// parallelo.
test('dragging a single page that belongs to a group moves the whole group together', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[1]->update(['page_group_id' => $group->id]); // posizione 2
    $pages[2]->update(['page_group_id' => $group->id]); // posizione 3

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        // Nessuna selectionMode/selectedPageIds impostata: è un drag di
        // UNA sola pagina (movePage), non un block-drag esplicito.
        ->call('movePage', $pages[1]->id, 6)
        ->assertHasNoErrors();

    $finalOrder = $issue->fresh()->pages()->orderBy('pages.position')->pluck('id')->all();
    $indexOfFirst = array_search($pages[1]->id, $finalOrder, true);
    $indexOfSecond = array_search($pages[2]->id, $finalOrder, true);

    expect($indexOfSecond)->toBe($indexOfFirst + 1);

    Event::assertDispatched(PagesBlockMoved::class, fn (PagesBlockMoved $e) => count($e->pageIds) === 2
        && in_array($pages[1]->id, $e->pageIds, true)
        && in_array($pages[2]->id, $e->pageIds, true));
});

test('dragging a single page whose group has a locked member rejects the whole move', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[1]->update(['page_group_id' => $group->id]);
    $pages[2]->update(['page_group_id' => $group->id, 'locked_at' => now(), 'locked_by' => $user->id]);

    $originalPosition = $pages[1]->position;

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('movePage', $pages[1]->id, 6)
        ->assertHasErrors('locked');

    expect($pages[1]->fresh()->position)->toBe($originalPosition);
    Event::assertNotDispatched(PagesBlockMoved::class);
});

// Fase 2.3, caso "selezione parziale di un gruppo esistente": la decisione
// presa (vedi HANDOFF.md, "Decisioni architetturali da ricordare") è
// l'espansione automatica, mai un blocco o uno spostamento parziale che
// spaccherebbe il gruppo.
test('a block-drag selecting only some pages of an existing group pulls in the rest of the group automatically', function () {
    Event::fake([PagesBlockMoved::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('pages.position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]); // posizione 1
    $pages[1]->update(['page_group_id' => $group->id]); // posizione 2, NON selezionata
    $pages[3]->update([]); // posizione 4, pagina "normale" inclusa nella selezione

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('togglePageSelection', $pages[3]->id);

    $component->call('moveSelectedBlock', $pages[0]->id, 6)
        ->assertHasNoErrors();

    Event::assertDispatched(PagesBlockMoved::class, fn (PagesBlockMoved $e) => count($e->pageIds) === 3
        && in_array($pages[0]->id, $e->pageIds, true)
        && in_array($pages[1]->id, $e->pageIds, true) // mai selezionata esplicitamente, inclusa comunque
        && in_array($pages[3]->id, $e->pageIds, true));
});
