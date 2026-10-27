<?php

use App\Events\ContentAssigned;
use App\Events\ContentListChanged;
use App\Events\PageGroupsChanged;
use App\Livewire\Timone\Grid;
use App\Models\ActivityLog;
use App\Models\Advertisement;
use App\Models\Content;
use App\Support\ContentPlacer;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Fase 4 (2026-10-27): rinomina inline di un contenuto dalla pagina.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentListChanged::class, ContentAssigned::class, PageGroupsChanged::class]));

// Test richiesto dalla specifica: un contenuto su 3 pagine aggiornato su tutte e 3.
test('renaming a content on 3 pages updates all 3 and tells the other users', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Vecchio titolo', 'occupancy_twelfths' => 36]);
    ContentPlacer::place($content, $issue->pages()->where('position', 2)->first());

    $component = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('renameContent', $content->id, '  Nuovo titolo  ')
        ->assertHasNoErrors();

    expect($content->fresh()->title)->toBe('Nuovo titolo');

    // Ogni pagina occupata mostra il nome nuovo, nessuna quello vecchio.
    $html = $component->html();
    expect(substr_count($html, 'Nuovo titolo'))->toBeGreaterThanOrEqual(3)
        ->and($html)->not->toContain('Vecchio titolo');

    foreach ($content->pages as $page) {
        expect($page->contents()->first()->title)->toBe('Nuovo titolo');
    }

    Event::assertDispatched(ContentListChanged::class, fn (ContentListChanged $e) => $e->contentId === $content->id && $e->action === 'renamed');
    expect($issue->fresh()->pages_updated_at)->not->toBeNull()
        ->and(ActivityLog::where('action', 'content.renamed')->first()->description)->toBe('Contenuto «Vecchio titolo» rinominato in «Nuovo titolo»');
});

test('renaming an advertisement changes the client, which is the name shown on the page', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'title' => 'Inserzione']);
    Advertisement::factory()->create(['content_id' => $content->id, 'client' => 'Acme']);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('renameContent', $content->id, 'Acme Tools');

    expect($content->fresh()->advertisement->client)->toBe('Acme Tools')
        ->and($content->fresh()->title)->toBe('Inserzione');
});

test('an empty name is refused and nothing changes', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Resta']);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('renameContent', $content->id, '   ')
        ->assertHasErrors('rename');

    expect($content->fresh()->title)->toBe('Resta');
    Event::assertNotDispatched(ContentListChanged::class);
});

test('a content of another issue cannot be renamed from this timone', function () {
    $issue = reorderableIssue();
    $other = Content::factory()->article()->create(['title' => 'Altrove']);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('renameContent', $other->id, 'Hack');

    expect($other->fresh()->title)->toBe('Altrove');
});

test('the content row on a page offers double-click rename and the edit button', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Con nome']);
    ContentPlacer::place($content, $issue->pages()->first());

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('$wire.renameContent('.$content->id.', name)')
        ->assertSeeHtml('x-on:dblclick.stop')
        ->assertSeeHtml("\$dispatch('open-content-edit', { contentId: {$content->id} })");
});
