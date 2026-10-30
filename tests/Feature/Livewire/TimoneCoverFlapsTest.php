<?php

use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\CoverFlap;
use App\Enums\CoverSlot;
use App\Enums\UserRole;
use App\Events\CoversChanged;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Magazines\Edit as MagazineEdit;
use App\Livewire\Timone\Grid;
use App\Models\ActivityLog;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\PageComment;
use App\Models\User;
use App\Support\AdPlacement\PlaceholderAdCreator;
use App\Support\ContentPlacer;
use App\Support\Covers\FlapConversion;
use App\Support\IssueCovers;
use App\Support\Signatures\SignaturePlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/*
 * Battenti di copertina (2026-10-30, al posto della bandella): battente di
 * I (2 pagine prima della I) e battente di IV (2 pagine dopo la IV).
 * reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php,
 * weightEditorFor() in IssueWeightTest.php, coverOf() in TimoneCoversTest.php.
 */

beforeEach(function () {
    Event::fake([CoversChanged::class]);
    // Gli altri eventi (contenuto assegnato…) non devono cercare un server Reverb.
    config(['broadcasting.default' => 'null']);
});

function flapAd(Issue $issue, string $client, AdFormat $format = AdFormat::BattenteCopertina): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita, 'title' => $client, 'occupancy_twelfths' => $format->twelfths()]);
    Advertisement::factory()->create(['content_id' => $content->id, 'format' => $format, 'client' => $client]);

    return $content->fresh(['advertisement']);
}

/** @return list<string> etichette delle copertine nell'ordine in cui la fascia le mostra */
function coverBandLabels(string $html): array
{
    $band = mb_substr($html, mb_strpos($html, 'data-testid="cover-band"'));
    preg_match_all('/data-cover-slot="([A-Z0-9]+)"/', $band, $m);

    return array_values(array_unique($m[1]));
}

test('each battente is added with its 2 pages in browsing order, and the cover pages of the weight follow', function () {
    $issue = reorderableIssue();
    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    $grid->call('addFlap', 'IV')->assertHasNoErrors();
    expect($issue->fresh()->pagine_copertina)->toBe(6)
        ->and(coverBandLabels($grid->html()))->toBe(['I', 'II', 'III', 'IV', 'BIV1', 'BIV2']);

    $grid->call('addFlap', 'I')->assertHasNoErrors();
    expect($issue->fresh()->pagine_copertina)->toBe(8)
        ->and($issue->fresh()->weightInput()->pagineCopertina)->toBe(8)
        ->and(coverBandLabels($grid->html()))->toBe(['BI1', 'BI2', 'I', 'II', 'III', 'IV', 'BIV1', 'BIV2'])
        ->and($grid->html())->toContain('📘 Battente I · 1')->toContain('📘 Battente IV · 2');

    $grid->call('removeFlap', 'IV')->assertHasNoErrors();
    expect($issue->fresh()->pagine_copertina)->toBe(6);

    $grid->call('removeFlap', 'I')->assertHasNoErrors();
    expect($issue->fresh()->pagine_copertina)->toBe(4)
        ->and($issue->covers()->count())->toBe(4)
        ->and(ActivityLog::where('action', 'cover.battente_added')->count())->toBe(2)
        ->and(ActivityLog::where('action', 'cover.battente_removed')->count())->toBe(2);

    Event::assertDispatched(CoversChanged::class, fn ($e) => $e->action === 'battente_added');
    Event::assertDispatched(CoversChanged::class, fn ($e) => $e->action === 'battente_removed');
});

test('adding a battente twice is refused', function () {
    $issue = reorderableIssue();
    IssueCovers::addFlap($issue, CoverFlap::I);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('addFlap', 'I')
        ->assertHasErrors('covers');

    expect($issue->covers()->count())->toBe(6);
});

test('a battente with a content or a lock cannot be removed, and the button says why', function () {
    $issue = reorderableIssue();
    IssueCovers::addFlap($issue, CoverFlap::I);
    IssueCovers::addFlap($issue, CoverFlap::IV);
    coverOf($issue, CoverSlot::BattenteI2)->contents()->attach(flapAd($issue, 'Acme', AdFormat::PaginaIntera)->id, ['occupied_twelfths' => 12]);
    coverOf($issue, CoverSlot::BattenteIV1)->update(['locked_at' => now()]);

    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    // Confronto diretto: assertHasErrors() leggerebbe «:» come parametro di una regola.
    expect($grid->call('removeFlap', 'I')->errors()->get('covers'))->toBe(['La pagina 2 del battente I contiene «Acme»: toglila prima.'])
        ->and($grid->call('removeFlap', 'IV')->errors()->get('covers'))->toBe(['La pagina 1 del battente IV è bloccata: sbloccala prima.']);

    expect($issue->covers()->count())->toBe(8)
        ->and($grid->html())->toContain('aria-disabled="true" title="La pagina 2 del battente I contiene «Acme»: toglila prima."');
});

test('the battenti do not count as internal pages nor in the ad percentage', function () {
    $issue = reorderableIssue();
    IssueCovers::addFlap($issue, CoverFlap::I);
    ContentPlacer::place(flapAd($issue, 'Battente Spa'), coverOf($issue, CoverSlot::BattenteI1));

    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    expect($issue->fresh()->total_pages)->toBe(6)
        ->and($issue->pages()->count())->toBe(6)
        ->and($grid->viewData('summary')['adPercentage'])->toEqual(0);
});

test('signatures with the covers included count 4, 6 or 8 cover pages', function () {
    $magazine = Magazine::factory()->create(['signature_pages' => 32, 'signature_includes_covers' => true]);
    $issue = Issue::factory()->create(['magazine_id' => $magazine->id, 'total_pages' => 56]);

    expect(SignaturePlan::forIssue($issue->fresh())->countedPages())->toBe(60);
    IssueCovers::addFlap($issue, CoverFlap::I);
    expect(SignaturePlan::forIssue($issue->fresh())->countedPages())->toBe(62);
    IssueCovers::addFlap($issue, CoverFlap::IV);
    expect(SignaturePlan::forIssue($issue->fresh())->countedPages())->toBe(64);
});

test('the «Battente copertina» ad takes both pages of a battente and goes only on a battente', function () {
    $issue = reorderableIssue();
    IssueCovers::addFlap($issue, CoverFlap::IV);

    $result = ContentPlacer::place(flapAd($issue, 'Battente Spa'), coverOf($issue, CoverSlot::BattenteIV1));

    expect($result)->toBeArray()
        ->and($result['pages']->map->label()->all())->toBe(['Battente IV · 1', 'Battente IV · 2'])
        ->and($result['twelfths'])->toBe([12, 12]);

    foreach ([coverOf($issue, CoverSlot::II), $issue->pages()->where('position', 2)->first()] as $wrong) {
        expect(ContentPlacer::place(flapAd($issue, 'Fuori posto'), $wrong))->toBeString()->toContain('va su un battente');
    }

    expect(ContentPlacer::place(flapAd($issue, 'Dalla seconda'), coverOf($issue, CoverSlot::BattenteIV2)))->toBeString();
});

test('a page of a battente takes one-page contents like any cover, and swaps with the other covers', function () {
    $issue = reorderableIssue();
    IssueCovers::addFlap($issue, CoverFlap::I);
    $first = coverOf($issue, CoverSlot::BattenteI1);
    $ad = flapAd($issue, 'Pagina sola', AdFormat::PaginaIntera);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $ad->id, $first->id)
        ->assertHasNoErrors()
        ->call('swapCovers', $first->id, coverOf($issue, CoverSlot::IV)->id)
        ->assertHasNoErrors();

    expect(coverOf($issue, CoverSlot::IV)->contents()->pluck('contents.id')->all())->toBe([$ad->id]);
});

test('a battente holding a «Battente copertina» ad is not swapped with another cover', function () {
    $issue = reorderableIssue();
    IssueCovers::addFlap($issue, CoverFlap::I);
    ContentPlacer::place(flapAd($issue, 'Battente Spa'), coverOf($issue, CoverSlot::BattenteI1));

    $grid = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('swapCovers', coverOf($issue, CoverSlot::BattenteI2)->id, coverOf($issue, CoverSlot::II)->id);

    expect($grid->errors()->get('covers'))->toBe(['«Battente Spa» occupa tutto il battente: non si scambia con un\'altra copertina.'])
        ->and(coverOf($issue, CoverSlot::II)->contents()->count())->toBe(0);
});

test('planned ads at issue creation never land on a battente', function () {
    $issue = reorderableIssue();
    IssueCovers::addFlap($issue, CoverFlap::I);
    IssueCovers::addFlap($issue, CoverFlap::IV);

    PlaceholderAdCreator::create($issue, ['full' => 8]);

    $onFlaps = $issue->covers()->whereIn('cover_slot', ['BI1', 'BI2', 'BIV1', 'BIV2'])->withCount('contents')->get()->sum('contents_count');
    expect($onFlaps)->toBe(0)
        ->and($issue->covers()->whereIn('cover_slot', ['I', 'II', 'III', 'IV'])->withCount('contents')->get()->sum('contents_count'))->toBe(4);
});

test('the magazine default chooses the battenti of new issues, and the form can change them', function (string $default, ?string $chosen, array $slots, int $coverPages) {
    $magazine = Magazine::factory()->withWeightDefaults()->create(['default_cover_flaps' => $default]);

    $form = Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->assertSet('coverFlaps', $default)
        ->set('title', 'Numero battenti')
        ->set('total_pages', 8);

    if ($chosen !== null) {
        $form->set('coverFlaps', $chosen)->assertSet('weight.pagine_copertina', (string) $coverPages);
    }

    $form->call('save')->assertHasNoErrors();
    $issue = Issue::where('title', 'Numero battenti')->firstOrFail();

    expect($issue->covers()->get()->sortBy(fn ($c) => $c->cover_slot->order())->map(fn ($c) => $c->cover_slot->value)->values()->all())->toBe($slots)
        ->and($issue->pagine_copertina)->toBe($coverPages)
        ->and($issue->total_pages)->toBe(8);
})->with([
    'none' => ['nessuno', null, ['I', 'II', 'III', 'IV'], 4],
    'battente I from the magazine' => ['I', null, ['BI1', 'BI2', 'I', 'II', 'III', 'IV'], 6],
    'battente IV chosen in the form' => ['I', 'IV', ['I', 'II', 'III', 'IV', 'BIV1', 'BIV2'], 6],
    'both' => ['nessuno', 'entrambi', ['BI1', 'BI2', 'I', 'II', 'III', 'IV', 'BIV1', 'BIV2'], 8],
]);

test('the magazine settings save the battenti and derive the cover pages', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)->test(MagazineEdit::class, ['magazine' => $magazine])
        ->assertSet('default_cover_flaps', 'I')
        ->set('default_cover_flaps', 'entrambi')
        ->assertSee('Pagine di copertina per il peso: 8')
        ->call('save')
        ->assertHasNoErrors();

    expect($magazine->fresh()->default_cover_flaps->value)->toBe('entrambi')
        ->and($magazine->fresh()->pagine_copertina)->toBe(8);
});

test('only who could change the bandella can add or remove a battente', function (UserRole $role, bool $allowed) {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => $role]);
    $user->magazines()->attach($issue->magazine);

    $grid = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])->call('addFlap', 'I');

    $allowed ? $grid->assertHasNoErrors() : $grid->assertForbidden();
    expect($issue->covers()->count())->toBe($allowed ? 6 : 4)
        ->and(str_contains($grid->html(), 'add-flap-I') || str_contains($grid->html(), 'remove-flap-I'))->toBe($allowed);
})->with([
    'admin' => [UserRole::Admin, true],
    'redattore' => [UserRole::Redattore, true],
    'commerciale' => [UserRole::Commerciale, false],
    'sola lettura' => [UserRole::SolaLettura, false],
]);

test('another user sees the new battente without reloading (Reverb event and polling marker)', function () {
    $issue = reorderableIssue();
    $before = $issue->fresh()->getRawOriginal('pages_updated_at');
    $other = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue]);

    $this->travel(2)->seconds();
    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->call('addFlap', 'IV');

    Event::assertDispatched(CoversChanged::class, fn ($e) => $e->issueId === $issue->id && $e->action === 'battente_added');
    expect($issue->fresh()->getRawOriginal('pages_updated_at'))->not->toBe($before);

    $other->call('onCoversChanged')->assertDispatched('remote-change');
    expect($other->html())->toContain('📘 Battente IV · 1');
});

test('the migration turns a full bandella into page 1 of the battente of I, and back', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $ad = flapAd($issue, 'Vecchia bandella', AdFormat::PaginaIntera);
    $bandellaId = DB::table('pages')->insertGetId(['issue_id' => $issue->id, 'cover_slot' => 'bandella', 'content_type' => 'pubblicita', 'status' => 'revisionata', 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('page_content')->insert(['page_id' => $bandellaId, 'content_id' => $ad->id, 'occupied_twelfths' => 12]);
    DB::table('issues')->where('id', $issue->id)->update(['pagine_copertina' => 6]);
    $comment = PageComment::create(['issue_id' => $issue->id, 'page_id' => $bandellaId, 'user_id' => $user->id, 'body' => 'Sulla bandella', 'page_label' => 'Bandella']);
    $empty = reorderableIssue();
    DB::table('pages')->insert(['issue_id' => $empty->id, 'cover_slot' => 'bandella', 'content_type' => 'bianca', 'status' => 'da_assegnare', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('magazines')->where('id', $issue->magazine_id)->update(['pagine_copertina' => 6, 'default_cover_flaps' => 'nessuno']);

    FlapConversion::apply();

    $first = coverOf($issue, CoverSlot::BattenteI1);
    expect($first->id)->toBe($bandellaId)
        ->and($first->contents()->pluck('contents.id')->all())->toBe([$ad->id])
        ->and($first->isLocked())->toBeTrue()
        ->and($first->status->value)->toBe('revisionata')
        ->and($comment->fresh()->page_id)->toBe($bandellaId)
        ->and(coverOf($issue, CoverSlot::BattenteI2)->contents()->count())->toBe(0)
        ->and($issue->fresh()->pagine_copertina)->toBe(6)
        ->and($issue->fresh()->coverPageCount())->toBe(6)
        ->and($empty->covers()->pluck('cover_slot')->map->value->sort()->values()->all())->toBe(['BI1', 'BI2', 'I', 'II', 'III', 'IV'])
        ->and($issue->magazine->fresh()->default_cover_flaps->value)->toBe('I')
        ->and(FlapConversion::counts()['numeri con bandella'])->toBe(0);

    FlapConversion::revert();

    expect(DB::table('pages')->where('id', $bandellaId)->value('cover_slot'))->toBe('bandella')
        ->and(DB::table('pages')->where('cover_slot', 'BI2')->count())->toBe(0)
        ->and(DB::table('page_content')->where('page_id', $bandellaId)->count())->toBe(1)
        ->and(DB::table('issues')->where('id', $issue->id)->value('pagine_copertina'))->toBe(6);
});

test('going back to the bandella stops, changing nothing, if a battente of IV or a used second page exists', function () {
    $issue = reorderableIssue();
    IssueCovers::addFlap($issue, CoverFlap::I);
    IssueCovers::addFlap($issue, CoverFlap::IV);
    coverOf($issue, CoverSlot::BattenteI2)->contents()->attach(flapAd($issue, 'Seconda', AdFormat::PaginaIntera)->id, ['occupied_twelfths' => 12]);

    expect(fn () => FlapConversion::revert())->toThrow(RuntimeException::class, 'battente di IV');
    expect(FlapConversion::revertProblems())->toHaveCount(2)
        ->and($issue->covers()->count())->toBe(8);
});

test('the dry-run command prints before and after counts without writing', function () {
    $issue = reorderableIssue();

    $this->artisan('timone:battenti-dry-run')
        ->expectsOutputToContain('già applicata')
        ->assertSuccessful();

    expect($issue->covers()->count())->toBe(4);
});
