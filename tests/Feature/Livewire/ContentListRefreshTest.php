<?php

use App\Events\ContentCreated;
use App\Events\ContentListChanged;
use App\Livewire\Timone\AdReservations;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/*
 * Fase 1, bug 3 (2026-10-26): un contenuto creato non compariva nel
 * pannello "Contenuti da assegnare" senza ricaricare la pagina — il
 * broadcast ContentCreated è `toOthers()` e Grid è un componente diverso
 * da ContentCreate. Ora ContentCreate emette l'evento Livewire locale
 * `content-created`, ascoltato da Grid e AdReservations; archivia/
 * ripristina/elimina avvisano gli altri utenti con ContentListChanged.
 *
 * reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php,
 * reservedAdContent() in AdReservationsTest.php.
 */

test('creating a content dispatches the local content-created event', function () {
    Event::fake([ContentCreated::class]);

    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo appena creato')
        ->set('whole_pages', 1)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('content-created', contentId: Content::where('title', 'Articolo appena creato')->value('id'));
});

test('the grid shows a newly created content as soon as it receives content-created', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $grid = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertDontSee('Articolo comparso senza refresh');

    // Il contenuto nasce "altrove" (in ContentCreate): Grid lo deve vedere
    // al solo evento, senza essere rimontato.
    Content::factory()->create(['issue_id' => $issue->id, 'type' => 'articolo', 'title' => 'Articolo comparso senza refresh']);

    $grid->dispatch('content-created', contentId: 1)
        ->assertSee('Articolo comparso senza refresh');
});

test('content-created realigns the polling reference so no fake "other user" toast appears', function () {
    $issue = reorderableIssue();

    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    $this->travel(5)->seconds();
    $issue->markPagesUpdated();

    $grid->dispatch('content-created', contentId: 1)
        ->call('checkForUpdates')
        ->assertNotDispatched('remote-change');
});

test('archiving, restoring and deleting an unassigned content notify the other users', function () {
    Event::fake([ContentListChanged::class]);

    $issue = reorderableIssue();
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'articolo', 'title' => 'Da archiviare']);
    $before = $issue->fresh()->getRawOriginal('pages_updated_at');
    $this->travel(5)->seconds();

    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('archiveContent', $content->id)
        ->call('unarchiveContent', $content->id)
        ->call('deleteUnassignedContent', $content->id, true);

    Event::assertDispatched(ContentListChanged::class, fn (ContentListChanged $e) => $e->action === 'archived' && $e->contentId === $content->id);
    Event::assertDispatched(ContentListChanged::class, fn (ContentListChanged $e) => $e->action === 'unarchived');
    Event::assertDispatched(ContentListChanged::class, fn (ContentListChanged $e) => $e->action === 'deleted');

    expect($issue->fresh()->getRawOriginal('pages_updated_at'))->not->toBe($before)
        ->and(Content::find($content->id))->toBeNull();

    // Le proprie modifiche non devono apparire come "di un altro utente".
    $grid->call('checkForUpdates')->assertNotDispatched('remote-change');
});

test('deleting an ad reservation refreshes the grid locally and notifies the others', function () {
    Event::fake([ContentListChanged::class]);

    $issue = reorderableIssue();
    $content = reservedAdContent($issue, 'Cliente da eliminare');

    Livewire::actingAs(editorFor($issue))->test(AdReservations::class, ['issue' => $issue])
        ->call('deleteReservation', $content->id)
        ->assertDispatched('content-list-changed');

    Event::assertDispatched(ContentListChanged::class, fn (ContentListChanged $e) => $e->action === 'deleted' && $e->contentId === $content->id);
});

test('the ad reservations panel refreshes on content-created', function () {
    $issue = reorderableIssue();

    $panel = Livewire::actingAs(editorFor($issue))->test(AdReservations::class, ['issue' => $issue])
        ->call('toggle')
        ->assertDontSee('Cliente appena prenotato');

    reservedAdContent($issue, 'Cliente appena prenotato');

    $panel->dispatch('content-created', contentId: 1)
        ->assertSee('Cliente appena prenotato');
});
