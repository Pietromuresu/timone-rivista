<?php

use App\Events\ContentAssigned;
use App\Events\ContentListChanged;
use App\Events\PageGroupsChanged;
use App\Livewire\Timone\ContentEdit;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\User;
use App\Support\ContentPlacer;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Fase 4 (2026-10-27): modale di modifica (App\Livewire\Timone\ContentEdit
// + App\Support\ContentUpdater). reorderableIssue()/editorFor() sono in
// TimoneGridReorderTest.php (6 pagine interne).

beforeEach(fn () => Event::fake([ContentListChanged::class, ContentAssigned::class, PageGroupsChanged::class]));

function placedArticle($issue, int $twelfths, int $startPosition, string $title = 'Servizio'): Content
{
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => $title, 'occupancy_twelfths' => $twelfths]);
    ContentPlacer::place($content, $issue->pages()->where('position', $startPosition)->first());

    return $content->fresh();
}

function twelfthsByPosition(Content $content): array
{
    return $content->pages()->orderBy('position')->get()
        ->mapWithKeys(fn ($page) => [$page->position => (int) $page->pivot->occupied_twelfths])
        ->all();
}

test('opening the modal loads every field of the content', function () {
    $issue = reorderableIssue();
    $content = placedArticle($issue, 30, 1, 'Due pagine e mezza');

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->assertSet('showModal', true)
        ->assertSet('type', 'articolo')
        ->assertSet('title', 'Due pagine e mezza')
        ->assertSet('whole_pages', 2)
        ->assertSet('fraction_twelfths', '6')
        ->assertSee('Modifica contenuto')
        ->assertSee('Occupa 2 e 1/2 pagine')
        ->assertSee('Il contenuto è su 3 pagine');
});

test('saving applies the changes everywhere and tells the other users', function () {
    $issue = reorderableIssue();
    $content = placedArticle($issue, 24, 1);

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('title', 'Titolo corretto')
        ->set('author', 'Mario Rossi')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false)
        ->assertDispatched('content-list-changed');

    $content->refresh();
    expect($content->title)->toBe('Titolo corretto')
        ->and($content->article->author)->toBe('Mario Rossi')
        // Occupazione invariata: le pagine restano com'erano.
        ->and(twelfthsByPosition($content))->toBe([1 => 12, 2 => 12]);

    Event::assertDispatched(ContentListChanged::class, fn (ContentListChanged $e) => $e->action === 'updated' && $e->contentId === $content->id);
});

// Test richiesto dalla specifica: la modifica dell'occupazione ridistribuisce.
test('changing the occupancy redistributes the content from its first page', function () {
    $issue = reorderableIssue();
    $content = placedArticle($issue, 24, 2);

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('whole_pages', 2)
        ->set('fraction_twelfths', '6')
        ->call('save')
        ->assertHasNoErrors();

    expect($content->fresh()->occupancy_twelfths)->toBe(30)
        ->and(twelfthsByPosition($content))->toBe([2 => 12, 3 => 12, 4 => 6]);
});

test('shrinking the occupancy frees the pages it no longer needs and unlinks its old group', function () {
    $issue = reorderableIssue();
    $content = placedArticle($issue, 36, 1);
    expect($issue->pages()->whereNotNull('page_group_id')->count())->toBe(3);

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('whole_pages', 0)
        ->set('fraction_twelfths', '6')
        ->call('save')
        ->assertHasNoErrors();

    expect(twelfthsByPosition($content))->toBe([1 => 6])
        ->and($issue->pages()->whereNotNull('page_group_id')->count())->toBe(0)
        ->and($issue->pages()->where('position', 3)->first()->content_type->value)->toBe('bianca');
});

test('a new occupancy that does not fit changes nothing and says why', function () {
    $issue = reorderableIssue();
    $content = placedArticle($issue, 12, 1);
    placedArticle($issue, 12, 2, 'Occupa la pagina 2');

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('whole_pages', 2)
        ->call('save')
        ->assertHasErrors('occupancy')
        ->assertSee('la pagina è già piena');

    expect($content->fresh()->occupancy_twelfths)->toBe(12)
        ->and(twelfthsByPosition($content))->toBe([1 => 12]);
});

test('the occupancy of a content on a locked page cannot be changed', function () {
    $issue = reorderableIssue();
    $content = placedArticle($issue, 12, 1);
    $issue->pages()->where('position', 1)->first()->update(['locked_at' => now()]);

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('fraction_twelfths', '6')
        ->set('whole_pages', 0)
        ->call('save')
        ->assertHasErrors('occupancy');

    expect($content->fresh()->occupancy_twelfths)->toBe(12);
});

test('an article can become an advertisement, and the page type follows', function () {
    $issue = reorderableIssue();
    $content = placedArticle($issue, 12, 1);

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('type', 'pubblicita')
        ->set('client', 'Acme')
        ->set('format', 'pagina_intera')
        ->set('confirmation_status', 'confermata')
        ->call('save')
        ->assertHasNoErrors();

    $content->refresh();
    expect($content->type->value)->toBe('pubblicita')
        ->and($content->article)->toBeNull()
        ->and($content->advertisement->client)->toBe('Acme')
        ->and($issue->pages()->where('position', 1)->first()->content_type->value)->toBe('pubblicita');
});

test('an advertisement keeps its edited fields and the format proposal can be overridden', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'occupancy_twelfths' => 6]);
    Advertisement::factory()->create(['content_id' => $content->id, 'format' => 'mezza_pagina_orizzontale', 'client' => 'Vecchio']);

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->assertSet('format', 'mezza_pagina_orizzontale')
        ->set('format', 'due_terzi_pagina')
        ->assertSet('fraction_twelfths', '8')
        ->set('fraction_twelfths', '9')
        ->set('client', 'Nuovo')
        ->set('preferred_position', '5')
        ->call('save')
        ->assertHasNoErrors();

    $content->refresh();
    expect($content->occupancy_twelfths)->toBe(9)
        ->and($content->advertisement->format->value)->toBe('due_terzi_pagina')
        ->and($content->advertisement->client)->toBe('Nuovo')
        ->and($content->advertisement->preferred_position)->toBe(5);
});

test('a read-only user cannot open or save the edit modal', function () {
    $issue = reorderableIssue();
    $content = placedArticle($issue, 12, 1);
    $viewer = User::factory()->create(['role' => 'sola_lettura']);
    $viewer->magazines()->attach($issue->magazine_id);

    Livewire::actingAs($viewer)->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->assertForbidden();
});

test('a content of another issue cannot be edited from this timone', function () {
    $issue = reorderableIssue();
    $other = Content::factory()->article()->create();

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $other->id)
        ->assertNotFound();
});
