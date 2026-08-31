<?php

use App\Enums\UserRole;
use App\Events\ContentCreated;
use App\Livewire\Timone\ContentCreate;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.
// Fase 4.2 — CRUD rubrica inline dentro ContentCreate (vedi HANDOFF.md,
// "Decisioni architetturali da ricordare": nessun componente Index/Create
// /Edit a sé per un modello a due campi, coerente con "non serve un
// pannello admin separato se il pattern del progetto non lo prevede già").

beforeEach(function () {
    Event::fake([ContentCreated::class]);
});

test('an editor can create a new section from the content form', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->call('startCreateSection')
        ->set('sectionName', 'Motori')
        ->call('saveSection')
        ->assertHasNoErrors()
        ->assertSet('showSectionForm', false);

    expect(Section::where('magazine_id', $issue->magazine_id)->where('name', 'Motori')->exists())->toBeTrue();
});

test('the newly created section id is set on the form in the same session', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $component = Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->call('startCreateSection')
        ->set('sectionName', 'Attualità')
        ->call('saveSection');

    $section = Section::where('magazine_id', $issue->magazine_id)->where('name', 'Attualità')->first();

    $component->assertSet('section_id', $section->id);
});

test('an editor can rename an existing section', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $section = Section::factory()->create(['magazine_id' => $issue->magazine_id, 'name' => 'Vecchio nome']);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('section_id', $section->id)
        ->call('startEditSection', $section->id)
        ->assertSet('sectionName', 'Vecchio nome')
        ->set('sectionName', 'Nuovo nome')
        ->call('saveSection')
        ->assertHasNoErrors();

    expect($section->fresh()->name)->toBe('Nuovo nome');
});

test('a duplicate section name within the same magazine is rejected', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    Section::factory()->create(['magazine_id' => $issue->magazine_id, 'name' => 'Sport']);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->call('startCreateSection')
        ->set('sectionName', 'Sport')
        ->call('saveSection')
        ->assertHasErrors(['sectionName' => 'unique']);
});

test('the same section name is allowed in a different magazine', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $otherMagazine = \App\Models\Magazine::factory()->create();
    Section::factory()->create(['magazine_id' => $otherMagazine->id, 'name' => 'Sport']);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->call('startCreateSection')
        ->set('sectionName', 'Sport')
        ->call('saveSection')
        ->assertHasNoErrors();

    expect(Section::where('magazine_id', $issue->magazine_id)->where('name', 'Sport')->exists())->toBeTrue();
});

test('renaming a section does not collide with its own current name', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $section = Section::factory()->create(['magazine_id' => $issue->magazine_id, 'name' => 'Cucina']);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->call('startEditSection', $section->id)
        ->set('sectionName', 'Cucina') // invariato
        ->call('saveSection')
        ->assertHasNoErrors();
});

test('cancelling the section form discards the draft name without creating anything', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->call('startCreateSection')
        ->set('sectionName', 'Bozza mai salvata')
        ->call('cancelSectionForm')
        ->assertSet('showSectionForm', false)
        ->assertSet('sectionName', '');

    expect(Section::where('name', 'Bozza mai salvata')->exists())->toBeFalse();
});

test('a redattore without access to the magazine cannot create a section', function () {
    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    Livewire::actingAs($outsider)->test(ContentCreate::class, ['issue' => $issue])
        ->call('startCreateSection')
        ->set('sectionName', 'Non dovrebbe esistere')
        ->call('saveSection');

    expect(Section::where('name', 'Non dovrebbe esistere')->exists())->toBeFalse();
});

test('a guest cannot create a section', function () {
    $issue = reorderableIssue();

    Livewire::test(ContentCreate::class, ['issue' => $issue])
        ->call('startCreateSection')
        ->set('sectionName', 'Non dovrebbe esistere')
        ->call('saveSection');

    expect(Section::where('name', 'Non dovrebbe esistere')->exists())->toBeFalse();
});
