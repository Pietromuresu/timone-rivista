<?php

use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\PageContentType;
use App\Enums\UserRole;
use App\Events\ContentAssigned;
use App\Events\ContentUnassigned;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

test('an editor assigns an article to an empty page as a whole page', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $content->id)->first()->pivot;

    expect($pivot->occupied_twelfths)->toBe(12)
        ->and($issue->fresh()->unassignedContents->pluck('id'))->not->toContain($content->id);

    Event::assertDispatched(ContentAssigned::class, fn (ContentAssigned $event) => $event->issueId === $issue->id
        && $event->pageId === $page->id
        && $event->contentId === $content->id
        && $event->twelfths === 12);
});

// Fase 3 (2026-10-27): lo spazio sul pivot è quello dell'occupazione del
// Content (proposta dal formato alla creazione, modificabile) — non più
// la percentuale del formato letta al momento dell'assegnazione.
test('assigning an advertisement uses its own occupancy in twelfths', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita, 'occupancy_twelfths' => 3]);
    Advertisement::factory()->create([
        'content_id' => $content->id,
        'format' => AdFormat::UnQuartoPagina,
    ]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $content->id)->first()->pivot;

    expect($pivot->occupied_twelfths)->toBe(3);
});

test('assigning a content to a page without enough free space is rejected', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $existing = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($existing->id, ['occupied_twelfths' => 12]);

    $newContent = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $newContent->id, $page->id);

    expect($newContent->fresh()->isAssigned())->toBeFalse();

    Event::assertNotDispatched(ContentAssigned::class);
});

test('updating a content occupancy beyond the page free space is rejected, saying what is left', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $a = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $b = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($a->id, ['occupied_twelfths' => 6]);
    $page->contents()->attach($b->id, ['occupied_twelfths' => 6]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateContentOccupancy', $page->id, $a->id, 8)
        ->assertHasErrors('percentage')
        ->assertSee('resta libero solo 1/2 di pagina');

    $pivot = $page->contents()->where('content_id', $a->id)->first()->pivot;

    expect($pivot->occupied_twelfths)->toBe(6);
});

test('updating a content occupancy within the page free space is applied and broadcast', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $a = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $b = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($a->id, ['occupied_twelfths' => 6]);
    $page->contents()->attach($b->id, ['occupied_twelfths' => 6]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateContentOccupancy', $page->id, $a->id, 4)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $a->id)->first()->pivot;

    expect($pivot->occupied_twelfths)->toBe(4);

    Event::assertDispatched(ContentAssigned::class, fn (ContentAssigned $event) => $event->contentId === $a->id && $event->twelfths === 4);
});

test('an occupancy outside 1/12 – a whole page is refused', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $article = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($article->id, ['occupied_twelfths' => 12]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('updateContentOccupancy', $page->id, $article->id, 0)
        ->assertHasErrors('percentage')
        ->call('updateContentOccupancy', $page->id, $article->id, 13)
        ->assertHasErrors('percentage');

    expect($page->contents()->first()->pivot->occupied_twelfths)->toBe(12);
});

test('unassigning a content removes it from the page and returns it to the unassigned pool', function () {
    Event::fake([ContentUnassigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($content->id, ['occupied_twelfths' => 12]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('unassignContent', $content->id, $page->id);

    expect($page->contents()->where('content_id', $content->id)->exists())->toBeFalse()
        ->and($issue->fresh()->unassignedContents->pluck('id'))->toContain($content->id);

    Event::assertDispatched(ContentUnassigned::class, fn (ContentUnassigned $event) => $event->contentId === $content->id && $event->pageId === $page->id);
});

test('a sola lettura user cannot assign a content', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    expect($content->fresh()->isAssigned())->toBeFalse();

    Event::assertNotDispatched(ContentAssigned::class);
});

test('a redattore without access to the magazine cannot assign a content', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($outsider)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    expect($content->fresh()->isAssigned())->toBeFalse();
});

test('an admin can assign a content on any issue', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($admin)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    expect($content->fresh()->isAssigned())->toBeTrue();
});

test('a guest cannot assign a content', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    expect($content->fresh()->isAssigned())->toBeFalse();
});

test('a content belonging to a different issue cannot be assigned', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $otherIssue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $otherIssue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id);

    expect($content->fresh()->isAssigned())->toBeFalse();

    Event::assertNotDispatched(ContentAssigned::class);
});

test('assigning a content updates the page content_type to match (regression)', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    expect($page->content_type->value)->toBe('bianca');

    $article = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $article->id, $page->id);

    expect($page->fresh()->content_type->value)->toBe('editoriale');

    // L'articolo (occupazione di fabbrica: 1 pagina) ha preso la pagina
    // intera: va ridotto a 3/4 per lasciare posto a una vetrina (1/4) e
    // ottenere davvero una pagina mista.
    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateContentOccupancy', $page->id, $article->id, 9);

    $ad = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita, 'occupancy_twelfths' => 3]);
    Advertisement::factory()->create(['content_id' => $ad->id, 'format' => AdFormat::UnQuartoPagina]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $ad->id, $page->id);

    expect($page->fresh()->content_type->value)->toBe('mista');
});

test('unassigning the last content on a page reverts it to bianca (regression)', function () {
    Event::fake([ContentUnassigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($content->id, ['occupied_twelfths' => 12]);
    $page->update(['content_type' => PageContentType::Editoriale]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('unassignContent', $content->id, $page->id);

    expect($page->fresh()->content_type->value)->toBe('bianca');
});
