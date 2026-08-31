<?php

use App\Events\PageGroupsChanged;
use App\Events\PagesBlockMoved;
use App\Livewire\Timone\Grid;
use App\Models\PageGroup;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.

test('a linked page renders the group badge in griglia and lista view, an unlinked one does not', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id]);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    $grigliaHtml = $component->html();
    expect(substr_count($grigliaHtml, 'wire:click="unlinkGroup('.$pages[0]->id.')"'))->toBe(1)
        ->and(substr_count($grigliaHtml, 'wire:click="unlinkGroup('.$pages[2]->id.')"'))->toBe(0);

    $listaHtml = $component->call('setViewMode', 'lista')->html();
    expect($listaHtml)->toContain('wire:click="unlinkGroup('.$pages[0]->id.')"');
});

test('linking two or more selected pages creates a group and colors them', function () {
    Event::fake([PageGroupsChanged::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();

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
    $page = $issue->pages()->orderBy('position')->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $page->id)
        ->call('linkSelectedPages');

    expect(PageGroup::where('issue_id', $issue->id)->count())->toBe(0);
});

test('linking a page already in another group is rejected without changing anything', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();

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
    $pages = $issue->pages()->orderBy('position')->get();
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
    $pages = $issue->pages()->orderBy('position')->get();

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
    $pages = $issue->pages()->orderBy('position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[0]->update(['page_group_id' => $group->id]);
    $pages[1]->update(['page_group_id' => $group->id, 'locked_at' => now(), 'locked_by' => $user->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('unlinkGroup', $pages[0]->id)
        ->assertHasErrors('locked');

    expect($pages[0]->fresh()->page_group_id)->toBe($group->id)
        ->and(PageGroup::find($group->id))->not->toBeNull();
});

test('a guest cannot link or unlink pages', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('position')->get();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $pages[0]->id)
        ->call('togglePageSelection', $pages[1]->id)
        ->call('linkSelectedPages');

    expect(PageGroup::where('issue_id', $issue->id)->count())->toBe(0);
});

test('a sola lettura user cannot link pages', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('position')->get();
    $user = User::factory()->create(['role' => \App\Enums\UserRole::SolaLettura]);
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
    $pages = $issue->pages()->orderBy('position')->get();

    $group = PageGroup::create(['issue_id' => $issue->id, 'color' => 'rose']);
    $pages[1]->update(['page_group_id' => $group->id]); // posizione 2
    $pages[2]->update(['page_group_id' => $group->id]); // posizione 3

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        // Nessuna selectionMode/selectedPageIds impostata: è un drag di
        // UNA sola pagina (movePage), non un block-drag esplicito.
        ->call('movePage', $pages[1]->id, 6)
        ->assertHasNoErrors();

    $finalOrder = $issue->fresh()->pages()->orderBy('position')->pluck('id')->all();
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
    $pages = $issue->pages()->orderBy('position')->get();

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
    $pages = $issue->pages()->orderBy('position')->get();

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
