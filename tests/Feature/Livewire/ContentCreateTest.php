<?php

use App\Enums\UserRole;
use App\Events\ContentCreated;
use App\Livewire\Timone\ContentCreate;
use App\Models\Content;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php).

test('an editor can create an article and it appears among unassigned contents', function () {
    Event::fake([ContentCreated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Prova su strada: nuova moto elettrica')
        ->set('author', 'Luca Rossi')
        ->set('editorial_status', 'in_scrittura')
        ->set('expected_length', 4)
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Prova su strada: nuova moto elettrica')->first();

    expect($content)->not->toBeNull()
        ->and($content->type->value)->toBe('articolo')
        ->and($content->article->author)->toBe('Luca Rossi')
        ->and($content->article->editorial_status->value)->toBe('in_scrittura')
        ->and($content->isAssigned())->toBeFalse()
        ->and($issue->fresh()->unassignedContents->pluck('id'))->toContain($content->id);

    Event::assertDispatched(ContentCreated::class, fn (ContentCreated $e) => $e->issueId === $issue->id && $e->contentId === $content->id);
});

test('an editor can create an advertisement with format default percentage', function () {
    Event::fake([ContentCreated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('title', 'Pubblicità Acme Tools')
        ->set('client', 'Acme Tools')
        ->set('agency', 'Agenzia Creativa')
        ->set('format', 'mezza_pagina_orizzontale')
        ->set('confirmation_status', 'confermata')
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Pubblicità Acme Tools')->first();

    expect($content->type->value)->toBe('pubblicita')
        ->and($content->advertisement->client)->toBe('Acme Tools')
        ->and($content->advertisement->agency)->toBe('Agenzia Creativa')
        ->and($content->advertisement->occupied_percentage_override)->toBeNull()
        ->and($content->advertisement->occupiedPercentage())->toBe(50.0)
        ->and($content->advertisement->confirmation_status->value)->toBe('confermata');
});

test('an advertisement can be created with a preferred position, purely informational', function () {
    Event::fake([ContentCreated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('title', 'Pubblicità con preferenza')
        ->set('client', 'Cliente Preferenza')
        ->set('format', 'pagina_intera')
        ->set('confirmation_status', 'in_trattativa')
        ->set('preferred_position', '7')
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Pubblicità con preferenza')->first();

    expect($content->advertisement->preferred_position)->toBe(7)
        ->and($content->isAssigned())->toBeFalse(); // solo informativo, non assegna davvero
});

test('a non-numeric preferred position is silently ignored instead of failing the whole form', function () {
    Event::fake([ContentCreated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('title', 'Pubblicità senza preferenza valida')
        ->set('client', 'Cliente Senza Preferenza')
        ->set('format', 'pagina_intera')
        ->set('confirmation_status', 'in_trattativa')
        ->set('preferred_position', 'abc')
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Pubblicità senza preferenza valida')->first();

    expect($content->advertisement->preferred_position)->toBeNull();
});

test('an advertisement with a manual percentage override stores it', function () {
    Event::fake([ContentCreated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('title', 'Pubblicità con override')
        ->set('client', 'Cliente Override')
        ->set('format', 'un_quarto_pagina')
        ->set('occupied_percentage_override', '35.5')
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Pubblicità con override')->first();

    expect((float) $content->advertisement->occupied_percentage_override)->toBe(35.5)
        ->and($content->advertisement->occupiedPercentage())->toBe(35.5);
});

test('a content can be linked to a magazine section', function () {
    Event::fake([ContentCreated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $section = Section::factory()->create(['magazine_id' => $issue->magazine_id, 'name' => 'Attualità']);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'In breve')
        ->set('section_id', $section->id)
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'In breve')->first();

    expect($content->section_id)->toBe($section->id);
});

test('the title is required', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

test('the client is required for an advertisement', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('title', 'Pubblicità senza cliente')
        ->set('client', '')
        ->call('save')
        ->assertHasErrors(['client' => 'required_if']);
});

test('a redattore without access to the magazine cannot create a content', function () {
    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    Livewire::actingAs($outsider)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Non dovrebbe esistere')
        ->call('save');

    expect(Content::where('title', 'Non dovrebbe esistere')->exists())->toBeFalse();
});

test('a sola lettura user cannot create a content', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Non dovrebbe esistere')
        ->call('save');

    expect(Content::where('title', 'Non dovrebbe esistere')->exists())->toBeFalse();
});

test('a guest cannot create a content', function () {
    $issue = reorderableIssue();

    Livewire::test(ContentCreate::class, ['issue' => $issue])
        ->set('title', 'Non dovrebbe esistere')
        ->call('save');

    expect(Content::where('title', 'Non dovrebbe esistere')->exists())->toBeFalse();
});
