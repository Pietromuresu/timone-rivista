<?php

use App\Enums\PlannedAdKind;
use App\Events\ContentCreated;
use App\Events\ContentListChanged;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\ContentEdit;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Content;
use App\Support\AdPlaceholder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Revisione 2, Fase 3.1 (2026-10-28): pubblicità segnaposto anche dal
// modulo "Nuovo contenuto" e regola unica (AdPlaceholder): segnaposto =
// pubblicità senza cliente e senza file.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    Event::fake([ContentCreated::class, ContentListChanged::class]);
});

function createAdFromForm($issue, array $fields, ?UploadedFile $file = null)
{
    $component = Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('format', 'mezza_pagina_orizzontale');

    foreach ($fields as $name => $value) {
        $component->set($name, $value);
    }

    if ($file !== null) {
        $component->set('file', $file);
    }

    return $component->call('save');
}

test('the automatic title is the same text used by the planned ads of an issue', function () {
    expect(AdPlaceholder::title(6))->toBe(PlannedAdKind::Half->placeholderTitle())
        ->and(AdPlaceholder::title(12))->toBe('Pubblicità pagina intera — da assegnare')
        ->and(AdPlaceholder::title(3))->toBe('Pubblicità 1/4 — da assegnare')
        ->and(AdPlaceholder::title(24))->toBe('Pubblicità 2 pagine — da assegnare')
        ->and(AdPlaceholder::isAutomaticTitle('Pubblicità 2/3 — da assegnare'))->toBeTrue()
        ->and(AdPlaceholder::isAutomaticTitle('Acme, campagna autunno'))->toBeFalse();
});

test('the rule: an ad is a placeholder only without client and without file; articles never', function () {
    expect(AdPlaceholder::applies('pubblicita', null, false))->toBeTrue()
        ->and(AdPlaceholder::applies('pubblicita', '   ', false))->toBeTrue()
        ->and(AdPlaceholder::applies('pubblicita', 'Acme', false))->toBeFalse()
        ->and(AdPlaceholder::applies('pubblicita', null, true))->toBeFalse()
        ->and(AdPlaceholder::applies('articolo', null, false))->toBeFalse();
});

test('an ad created from the form without client and title is a placeholder with the automatic title', function () {
    $issue = reorderableIssue();

    createAdFromForm($issue, ['client' => '', 'title' => ''])->assertHasNoErrors();

    $content = Content::where('issue_id', $issue->id)->with('advertisement')->sole();

    expect($content->isPlaceholder())->toBeTrue()
        ->and($content->title)->toBe('Pubblicità 1/2 — da assegnare')
        ->and($content->advertisement->client)->toBeNull()
        ->and($content->displayLabel())->toBe('Pubblicità 1/2 — da assegnare');
});

test('the dashboard "da assegnare" count includes placeholders created from the form', function () {
    $issue = reorderableIssue();

    createAdFromForm($issue, ['client' => '', 'title' => ''])->assertHasNoErrors();
    createAdFromForm($issue, ['client' => 'Acme', 'title' => ''])->assertHasNoErrors();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('Da assegnare: <strong>1</strong>');
});

test('an ad with a client but no title is a real ad with the automatic title', function () {
    $issue = reorderableIssue();

    createAdFromForm($issue, ['client' => 'Acme Tools', 'title' => ''])->assertHasNoErrors();

    $content = Content::where('issue_id', $issue->id)->sole();

    expect($content->isPlaceholder())->toBeFalse()
        ->and($content->title)->toBe('Pubblicità 1/2 — da assegnare')
        ->and($content->displayLabel())->toBe('Acme Tools');
});

test('an ad without client but with a file is a real ad', function () {
    $issue = reorderableIssue();

    createAdFromForm($issue, ['client' => '', 'title' => 'Campagna autunno'], UploadedFile::fake()->image('campagna.png', 600, 400))
        ->assertHasNoErrors();

    $content = Content::where('issue_id', $issue->id)->sole();

    expect($content->isPlaceholder())->toBeFalse()
        ->and($content->title)->toBe('Campagna autunno')
        ->and($content->files()->count())->toBe(1);
});

test('a placeholder given only its own title stays a placeholder (the title does not count anymore)', function () {
    $issue = reorderableIssue();
    createAdFromForm($issue, ['client' => '', 'title' => ''])->assertHasNoErrors();
    $content = Content::where('issue_id', $issue->id)->sole();

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('title', 'Pagina Acme, da confermare')
        ->call('save')
        ->assertHasNoErrors();

    expect($content->fresh()->isPlaceholder())->toBeTrue()
        ->and($content->fresh()->title)->toBe('Pagina Acme, da confermare');
});

test('removing the client from an ad without file turns it back into a placeholder', function () {
    $issue = reorderableIssue();
    createAdFromForm($issue, ['client' => 'Acme', 'title' => 'Acme autunno'])->assertHasNoErrors();
    $content = Content::where('issue_id', $issue->id)->sole();
    expect($content->isPlaceholder())->toBeFalse();

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('client', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($content->fresh()->isPlaceholder())->toBeTrue()
        ->and($content->fresh()->advertisement->client)->toBeNull();
});

test('an emptied title gets the automatic one; an automatic title follows a new occupancy', function () {
    $issue = reorderableIssue();
    createAdFromForm($issue, ['client' => '', 'title' => 'Titolo provvisorio'])->assertHasNoErrors();
    $content = Content::where('issue_id', $issue->id)->sole();

    $edit = Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue]);

    $edit->call('open', $content->id)->set('title', '')->call('save')->assertHasNoErrors();
    expect($content->fresh()->title)->toBe('Pubblicità 1/2 — da assegnare');

    $edit->call('open', $content->id)->set('format', 'un_quarto_pagina')->set('fraction_twelfths', '3')->call('save')->assertHasNoErrors();
    expect($content->fresh()->title)->toBe('Pubblicità 1/4 — da assegnare');
});

test('timone:placeholder-sync shows in dry-run and then applies the new rule to old data', function () {
    $issue = reorderableIssue();
    // Dato scritto con la regola della Fase 5: titolo proprio → "vera", ma
    // senza cliente né file.
    $old = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'title' => 'Titolo proprio', 'is_placeholder' => false, 'occupancy_twelfths' => 6]);
    Advertisement::factory()->create(['content_id' => $old->id, 'client' => null, 'format' => 'mezza_pagina_orizzontale']);
    $real = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'title' => 'Acme', 'is_placeholder' => false, 'occupancy_twelfths' => 6]);
    Advertisement::factory()->create(['content_id' => $real->id, 'client' => 'Acme', 'format' => 'mezza_pagina_orizzontale']);

    $this->artisan('timone:placeholder-sync', ['--dry-run' => true])
        ->expectsOutputToContain('«Titolo proprio» → diventa segnaposto')
        ->expectsOutputToContain('Dry-run: 1 tornano segnaposto, 0 diventano pubblicità vere.')
        ->assertSuccessful();
    expect($old->fresh()->isPlaceholder())->toBeFalse();

    $this->artisan('timone:placeholder-sync')->assertSuccessful();
    expect($old->fresh()->isPlaceholder())->toBeTrue()
        ->and($real->fresh()->isPlaceholder())->toBeFalse();
});
