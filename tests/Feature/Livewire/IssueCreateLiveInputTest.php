<?php

use App\Events\ContentAssigned;
use App\Events\ContentListChanged;
use App\Livewire\Issues\Create as IssueCreate;
use App\Models\ActivityLog;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Magazine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

// Revisione 2, Fase 1 (2026-10-27): bug 1 (cifre perse/focus nel campo
// pagine) e bug 2 ("Text too long" con tante pubblicità previste).
// weightEditorFor() è in IssueWeightTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class, ContentListChanged::class]));

// Bug 1, la causa: con una proprietà `?int` il server rimandava 12 (intero)
// per "12" (testo) e Livewire riscriveva il campo mentre l'utente digitava.
test('the page count comes back exactly as typed, so Livewire never rewrites the field', function () {
    $magazine = Magazine::factory()->create();

    $component = Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('total_pages', '1')
        ->set('total_pages', '12');

    expect($component->get('total_pages'))->toBe('12');
});

test('a half-typed or invalid page count does not break the preview and fails on save', function (string $value) {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('plannedAds.full', '2')
        ->set('total_pages', $value)
        ->assertOk()
        ->assertDontSeeHtml('data-testid="planned-ads-summary"')
        ->set('title', 'Numero')
        ->call('save')
        ->assertHasErrors('total_pages');

    expect(Issue::count())->toBe(0);
})->with(['', '3.5', '-2', 'abc']);

test('numeric inputs preview with a 600 ms debounce and warnings wait for the blur', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('total_pages', '30')
        ->set('plannedAds.half', '99')
        ->assertSeeHtml('wire:model.live.debounce.600ms="total_pages"')
        ->assertSeeHtml('wire:model.live.debounce.600ms="plannedAds.full"')
        ->assertSeeHtml('wire:model.live.debounce.600ms="weight.n_copie"')
        ->assertSeeHtml('wire:model.live.debounce.600ms="weight.grammatura_interno"')
        ->assertDontSeeHtml('wire:model.live="total_pages"')
        // Avvisi presenti ma nascosti finché il focus è nel campo (x-live-field).
        ->assertSeeHtml('x-on:focusout="editing = false"')
        ->assertSeeHtml('<p x-show="! editing" class="mt-1 text-xs text-amber-600 dark:text-amber-400" data-testid="not-multiple-of-four">')
        ->assertSeeHtml('<p x-show="! editing" class="mt-1 text-xs font-medium text-red-600');
});

// Test richiesto dalla specifica (bug 2).
test('an issue with 60 planned ads is created without errors and without losing data', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('title', 'Numero pieno di pubblicità')
        ->set('total_pages', '120')
        ->set('plannedAds.half', '60')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $issue = Issue::where('title', 'Numero pieno di pubblicità')->firstOrFail();
    $log = ActivityLog::where('issue_id', $issue->id)->where('action', 'issue.placeholder_ads_created')->firstOrFail();

    expect(Content::where('issue_id', $issue->id)->where('is_placeholder', true)->count())->toBe(60)
        ->and($issue->unassignedContents()->count())->toBe(0)
        // Frase di lunghezza fissa: valida anche con il vecchio VARCHAR(255)…
        ->and(mb_strlen($log->description))->toBeLessThan(255)
        ->and($log->description)->toBe('60 pubblicità previste create come segnaposto: 4 sulle copertine, 56 nelle pagine interne')
        // …e le posizioni restano tutte nei dati strutturati.
        ->and($log->new_values['placed'])->toHaveCount(60);
});

test('the activity log description column is TEXT', function () {
    expect(Schema::getColumnType('activity_logs', 'description'))->toBe('text');
});

test('issue notes longer than the limit get a clear message instead of a database error', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('title', 'Numero')
        ->set('notes', str_repeat('a', 2001))
        ->call('save')
        ->assertHasErrors(['notes' => 'max'])
        ->assertSee('Le note possono essere lunghe al massimo 2000 caratteri.');
});
