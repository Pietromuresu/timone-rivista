<?php

use App\Enums\AdFormat;
use App\Enums\ContentType;
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

test('an editor assigns an article to an empty page at 100 percent', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $content->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(100.0)
        ->and($issue->fresh()->unassignedContents->pluck('id'))->not->toContain($content->id);

    Event::assertDispatched(ContentAssigned::class, fn (ContentAssigned $event) => $event->issueId === $issue->id
        && $event->pageId === $page->id
        && $event->contentId === $content->id
        && $event->percentage === 100.0);
});

test('assigning an advertisement uses its format default percentage', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create([
        'content_id' => $content->id,
        'format' => AdFormat::UnQuartoPagina,
        'occupied_percentage_override' => null,
    ]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $content->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(25.0);
});

test('assigning an advertisement with a manual override uses the override', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create([
        'content_id' => $content->id,
        'format' => AdFormat::UnQuartoPagina,
        'occupied_percentage_override' => 40,
    ]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $page->id)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $content->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(40.0);
});

test('assigning a content to a page without enough free space is rejected', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $existing = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($existing->id, ['occupied_percentage' => 100]);

    $newContent = Content::factory()->article()->create(['issue_id' => $issue->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $newContent->id, $page->id);

    expect($newContent->fresh()->isAssigned())->toBeFalse();

    Event::assertNotDispatched(ContentAssigned::class);
});

test('updating a content percentage beyond the page free space is rejected', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $a = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $b = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($a->id, ['occupied_percentage' => 50]);
    $page->contents()->attach($b->id, ['occupied_percentage' => 50]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateContentPercentage', $page->id, $a->id, 60);

    $pivot = $page->contents()->where('content_id', $a->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(50.0);
});

test('updating a content percentage within the page free space is applied', function () {
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $a = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $b = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($a->id, ['occupied_percentage' => 50]);
    $page->contents()->attach($b->id, ['occupied_percentage' => 50]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateContentPercentage', $page->id, $a->id, 30)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $a->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(30.0);

    Event::assertDispatched(ContentAssigned::class, fn (ContentAssigned $event) => $event->contentId === $a->id && $event->percentage === 30.0);
});

test('a non-advertisement content accepts a genuinely fractional percentage, not just whole numbers (Fase 3.3)', function () {
    // Verifica esplicita (Fase 3.3 del prompt "Batch modifiche": "estendi
    // la stessa logica decimale già usata per gli spazi pubblicitari
    // anche ai contenuti generici") che il meccanismo è GIÀ generico: non
    // esiste alcuna distinzione per tipo di contenuto in
    // updateContentPercentage()/PagePercentageAllocator, solo i test
    // esistenti usavano finora percentuali intere (50/60/30), mai una
    // frazione — questo test chiude quel dubbio con un numero vero.
    Event::fake([ContentAssigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $article = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($article->id, ['occupied_percentage' => 100]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateContentPercentage', $page->id, $article->id, 33.33)
        ->assertHasNoErrors();

    $pivot = $page->contents()->where('content_id', $article->id)->first()->pivot;

    expect((float) $pivot->occupied_percentage)->toBe(33.33);
});

test('unassigning a content removes it from the page and returns it to the unassigned pool', function () {
    Event::fake([ContentUnassigned::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($content->id, ['occupied_percentage' => 100]);

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

    // L'articolo appena assegnato ha preso tutto lo spazio libero (100% —
    // "un articolo prende tutto lo spazio libero rimasto sulla pagina",
    // comportamento consolidato del Punto 3): va ridotto per lasciare
    // posto a una pubblicità e ottenere davvero una pagina mista.
    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateContentPercentage', $page->id, $article->id, 75);

    $ad = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
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
    $page->contents()->attach($content->id, ['occupied_percentage' => 100]);
    $page->update(['content_type' => \App\Enums\PageContentType::Editoriale]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('unassignContent', $content->id, $page->id);

    expect($page->fresh()->content_type->value)->toBe('bianca');
});
