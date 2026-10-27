<?php

use App\Events\ContentAssigned;
use App\Events\ContentListChanged;
use App\Events\IssuePageCountUpdated;
use App\Events\PageGroupsChanged;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Timone\ContentEdit;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Magazine;
use App\Support\AdPlacement\PlaceholderAdCreator;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Fase 5 (2026-10-27): pubblicità previste alla creazione del numero,
// segnaposto e loro trasformazione in pubblicità vere.
// weightEditorFor() è in IssueWeightTest.php, editorFor() in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class, ContentListChanged::class, PageGroupsChanged::class, IssuePageCountUpdated::class]));

function createIssueWithPlannedAds(Magazine $magazine, int $pages, array $planned): Issue
{
    $component = Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('title', 'Numero con pubblicità')
        ->set('total_pages', $pages);

    foreach ($planned as $kind => $count) {
        $component->set("plannedAds.{$kind}", (string) $count);
    }

    $component->call('save')->assertHasNoErrors();

    return Issue::where('title', 'Numero con pubblicità')->firstOrFail();
}

function placeholderLabels(Issue $issue): array
{
    return Content::where('issue_id', $issue->id)->where('is_placeholder', true)->with('pages')->get()
        ->flatMap(fn (Content $c) => $c->pages->map->label())
        ->sort()->values()->all();
}

// Test richiesto dalla specifica (con la creazione vera).
test('6 full pages and 2 halves on 32 pages are created as placeholders already in the timone', function () {
    $issue = createIssueWithPlannedAds(Magazine::factory()->create(), 32, ['full' => 6, 'half' => 2]);

    $placeholders = Content::where('issue_id', $issue->id)->with(['advertisement', 'pages'])->get();

    expect($placeholders)->toHaveCount(8)
        ->and($placeholders->every->isPlaceholder())->toBeTrue()
        ->and($placeholders->every(fn ($c) => $c->advertisement->client === null))->toBeTrue()
        ->and($placeholders->every(fn ($c) => $c->pages->count() === 1))->toBeTrue()
        ->and($placeholders->pluck('title')->unique()->sort()->values()->all())->toBe(['Pubblicità 1/2 — da assegnare', 'Pubblicità pagina intera — da assegnare'])
        ->and(placeholderLabels($issue))->toBe(collect(['I di copertina', 'II di copertina', 'III di copertina', 'IV di copertina', 'pagina 2', 'pagina 4', 'pagina 6', 'pagina 8'])->sort()->values()->all())
        // Le mezze occupano 1/2 pagina: il resto resta libero per l'editoriale.
        ->and($issue->pages()->where('position', 6)->first()->occupiedTwelfths())->toBe(6)
        ->and($issue->unassignedContents()->count())->toBe(0);
});

test('the warning about the I cover is shown once on the new issue page', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('title', 'Con la I')
        ->set('total_pages', 8)
        ->set('plannedAds.full', '4')
        ->call('save');

    expect(session('ad-placement-warnings'))->toHaveCount(1)
        ->and(session('ad-placement-warnings')[0])->toContain('I di copertina');
});

test('the form shows equivalent pages, internal percentage and positions live', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('total_pages', 32)
        ->set('plannedAds.full', '6')
        ->set('plannedAds.half', '2')
        ->assertSee('7 pagine')
        ->assertSee('9.38%')
        ->assertSee('più 4 pagine sulle copertine')
        ->assertSee('Posizioni: II di copertina, III di copertina, IV di copertina, I di copertina, pagina 2, pagina 4, pagina 6, pagina 8');
});

// Test richiesto dalla specifica: più pubblicità che spazio.
test('ads that do not fit are an error in the form and no issue is created', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('title', 'Troppe pubblicità')
        ->set('total_pages', 4)
        ->set('plannedAds.half', '11') // 4 copertine + 4 pagine = 8 posti
        ->assertSee('Non ci stanno: 3 resterebbero senza posto')
        ->call('save')
        ->assertHasErrors('plannedAds');

    expect(Issue::where('title', 'Troppe pubblicità')->exists())->toBeFalse();
});

test('without planned ads the issue is created as before', function () {
    $issue = createIssueWithPlannedAds(Magazine::factory()->create(), 8, []);

    expect(Content::where('issue_id', $issue->id)->count())->toBe(0);
});

// Test richiesto dalla specifica: il posizionamento non si ripete modificando il numero.
test('the automatic placement never runs again after creation', function () {
    $issue = createIssueWithPlannedAds(Magazine::factory()->create(), 8, ['full' => 5]);
    $before = placeholderLabels($issue);

    // Spostata a mano una pubblicità: pagina 2 → pagina 3 (tolta e rimessa).
    $content = Content::whereHas('pages', fn ($q) => $q->where('position', 2))->where('issue_id', $issue->id)->firstOrFail();
    $content->pages()->detach();
    $issue->pages()->where('position', 3)->first()->contents()->attach($content->id, ['occupied_twelfths' => 12]);

    // Modifica del numero: pagine totali 8 → 12.
    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', '12')
        ->call('resizePages', true);

    expect($issue->fresh()->total_pages)->toBe(12)
        ->and(Content::where('issue_id', $issue->id)->count())->toBe(5)
        ->and(placeholderLabels($issue))->toBe(collect($before)->map(fn ($l) => $l === 'pagina 2' ? 'pagina 3' : $l)->sort()->values()->all());
});

test('placeholders are marked "Da assegnare" in the timone and counted in the dashboard and ad load', function () {
    $issue = createIssueWithPlannedAds(Magazine::factory()->create(), 8, ['full' => 4, 'half' => 2]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('data-placeholder="1"')
        ->assertSee('Da assegnare')
        ->assertSee('Pubblicità da assegnare (segnaposto):')
        ->assertSeeHtml('Pubblicità da assegnare (segnaposto): <strong>6</strong>')
        ->assertSee('Mezza pagina orizzontale: 2')
        // 2 mezze su 8 pagine interne = 1 pagina su 8 = 12.5% (copertine escluse).
        ->assertSee('12.5%');
});

test('a placeholder becomes a real ad when it gets a client from the edit modal', function () {
    $issue = createIssueWithPlannedAds(Magazine::factory()->create(), 8, ['half' => 1]);
    $content = Content::where('issue_id', $issue->id)->firstOrFail();

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->assertSee('da assegnare')
        ->set('client', 'Acme Tools')
        ->call('save')
        ->assertHasNoErrors();

    expect($content->fresh()->isPlaceholder())->toBeFalse()
        ->and($content->fresh()->displayLabel())->toBe('Acme Tools');
});

test('a placeholder edited without client, title or pdf stays a placeholder', function () {
    $issue = createIssueWithPlannedAds(Magazine::factory()->create(), 8, ['half' => 1]);
    $content = Content::where('issue_id', $issue->id)->firstOrFail();

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('confirmation_status', 'confermata')
        ->call('save')
        ->assertHasNoErrors();

    expect($content->fresh()->isPlaceholder())->toBeTrue()
        ->and($content->fresh()->advertisement->confirmation_status->value)->toBe('confermata');
});

test('naming a placeholder inline turns it into a real ad', function () {
    $issue = createIssueWithPlannedAds(Magazine::factory()->create(), 8, ['half' => 1]);
    $content = Content::where('issue_id', $issue->id)->firstOrFail();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('renameContent', $content->id, 'Cliente Vero');

    expect($content->fresh()->isPlaceholder())->toBeFalse()
        ->and($content->fresh()->advertisement->client)->toBe('Cliente Vero');
});

test('placeholders that did not fit are created unassigned (service used outside the form)', function () {
    $issue = Issue::factory()->create(['total_pages' => 2]);

    // 4 copertine + 2 pagine = 6 posti per 7 pubblicità.
    $plan = PlaceholderAdCreator::create($issue, ['half' => 7]);

    expect($plan->unplaced)->toHaveCount(1)
        ->and(Content::where('issue_id', $issue->id)->count())->toBe(7)
        ->and($issue->unassignedContents()->count())->toBe(1)
        ->and($issue->unassignedContents()->first()->isPlaceholder())->toBeTrue();
});
