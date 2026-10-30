<?php

use App\Enums\AdFormat;
use App\Enums\CoverFlap;
use App\Enums\CoverSlot;
use App\Enums\PageStatus;
use App\Enums\UserRole;
use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\ContentListChanged;
use App\Events\ContentUnassigned;
use App\Events\CoversChanged;
use App\Events\IssuePageCountUpdated;
use App\Events\PageContentsReordered;
use App\Events\PageGroupsChanged;
use App\Events\PageMoved;
use App\Events\PagesBlockMoved;
use App\Events\PageStatusUpdated;
use App\Events\TimelineApplied;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\ContentEdit;
use App\Livewire\Timone\Grid;
use App\Models\ActivityLog;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\User;
use App\Support\ContentPlacer;
use App\Support\IssueCovers;
use App\Support\Timeline\TimelineActionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// Stato automatico della pagina (2026-10-30, App\Support\PageStatusAutomation):
// un test per ogni percorso trovato nella Fase 0. reorderableIssue()/editorFor()
// in TimoneGridReorderTest.php (6 pagine interne), commercialeFor() in
// CommercialeAdsOnlyTest.php, createIssueWithPlannedAds() in PlannedAdsTest.php.

beforeEach(function () {
    Queue::fake();
    Event::fake([ContentAssigned::class, ContentCreated::class, ContentListChanged::class, ContentUnassigned::class, CoversChanged::class, IssuePageCountUpdated::class, PageContentsReordered::class, PageGroupsChanged::class, PageMoved::class, PagesBlockMoved::class, PageStatusUpdated::class, TimelineApplied::class]);
});

function psaArticle(Issue $issue, string $title, int $twelfths): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'articolo', 'title' => $title, 'occupancy_twelfths' => $twelfths]);
    Article::factory()->create(['content_id' => $content->id]);

    return $content;
}

function psaAd(Issue $issue, string $client, AdFormat $format = AdFormat::PaginaIntera): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'title' => $client, 'occupancy_twelfths' => $format->twelfths()]);
    Advertisement::factory()->create(['content_id' => $content->id, 'format' => $format, 'client' => $client]);

    return $content;
}

function psaPage(Issue $issue, int $position): Page
{
    return $issue->pages()->where('position', $position)->first();
}

function psaStatus(Issue $issue, int $position): PageStatus
{
    return psaPage($issue, $position)->status;
}

function psaGrid(Issue $issue, User $user)
{
    return Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);
}

function psaSetStatus(Issue $issue, int $position, PageStatus $status): void
{
    psaPage($issue, $position)->update(['status' => $status]);
}

// --- Assegnare -------------------------------------------------------------

test('drag: a page that becomes full goes to «Assegnata», logged as automatic and broadcast', function () {
    $issue = reorderableIssue();
    $content = psaArticle($issue, 'Pezzo', 12);

    psaGrid($issue, editorFor($issue))->call('assignContent', $content->id, psaPage($issue, 2)->id);

    expect(psaStatus($issue, 2))->toBe(PageStatus::Assegnata)
        ->and(psaStatus($issue, 3))->toBe(PageStatus::DaAssegnare)
        ->and(ActivityLog::where('action', 'page.status_auto_changed')->value('description'))
        ->toBe('Stato pagina 2: Da assegnare → Assegnata (automatico, dopo: Assegna «Pezzo» (pagina 2))');

    Event::assertDispatched(PageStatusUpdated::class, fn ($e) => $e->pageId === psaPage($issue, 2)->id && $e->status === 'assegnata');
});

test('a page that stays partial keeps its status; filling it later sets «Assegnata» only from «Da assegnare»', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    psaGrid($issue, $user)->call('assignContent', psaArticle($issue, 'Metà', 6)->id, psaPage($issue, 2)->id);
    expect(psaStatus($issue, 2))->toBe(PageStatus::DaAssegnare);

    psaSetStatus($issue, 2, PageStatus::InBozza);
    psaGrid($issue, $user)->call('assignContent', psaArticle($issue, 'Altra metà', 6)->id, psaPage($issue, 2)->id);
    expect(psaStatus($issue, 2))->toBe(PageStatus::InBozza);
});

test('«Assegna alla selezione» fills the selected page', function () {
    $issue = reorderableIssue();
    $content = psaArticle($issue, 'Selezione', 12);

    psaGrid($issue, editorFor($issue))
        ->set('selectedPageIds', [psaPage($issue, 4)->id])
        ->call('assignContentToSelection', $content->id);

    expect(psaStatus($issue, 4))->toBe(PageStatus::Assegnata);
});

test('«Vai a pag.» fills the chosen page, a cover too', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    psaGrid($issue, $user)->call('assignContentToTarget', psaArticle($issue, 'Vai', 12)->id, '5');
    psaGrid($issue, $user)->call('assignContentToTarget', psaAd($issue, 'Copertina', AdFormat::CopertinaSecondaTerzaQuarta)->id, 'II');

    expect(psaStatus($issue, 5))->toBe(PageStatus::Assegnata)
        ->and($issue->covers()->where('cover_slot', CoverSlot::II->value)->first()->status)->toBe(PageStatus::Assegnata);
});

test('a «Battente copertina» ad fills both pages of the flap', function () {
    $issue = reorderableIssue();
    IssueCovers::addFlap($issue, CoverFlap::I);
    $first = $issue->covers()->where('cover_slot', CoverSlot::BattenteI1->value)->first();

    psaGrid($issue, editorFor($issue))->call('assignContent', psaAd($issue, 'Battente', AdFormat::BattenteCopertina)->id, $first->id);

    expect($issue->covers()->whereIn('cover_slot', ['BI1', 'BI2'])->pluck('status')->map->value->all())->toBe(['assegnata', 'assegnata']);
});

test('a content on 3 pages with 1/2 on the last: the first two «Assegnata», the third unchanged', function () {
    $issue = reorderableIssue();
    psaSetStatus($issue, 4, PageStatus::InBozza);

    psaGrid($issue, editorFor($issue))->call('assignContent', psaArticle($issue, 'Due e mezza', 30)->id, psaPage($issue, 2)->id);

    expect([psaStatus($issue, 2), psaStatus($issue, 3), psaStatus($issue, 4)])
        ->toBe([PageStatus::Assegnata, PageStatus::Assegnata, PageStatus::InBozza]);
});

test('↗ extending a content to another page fills that page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = psaArticle($issue, 'Esteso', 12);
    psaGrid($issue, $user)->call('assignContent', $content->id, psaPage($issue, 2)->id);

    psaGrid($issue, $user)->call('extendToPage', $content->id, 3);

    expect(psaStatus($issue, 3))->toBe(PageStatus::Assegnata);
});

// --- Togliere e cambiare lo spazio -------------------------------------------

test('✕ on an «Ok stampa» page: back to «Da assegnare», with a message to whoever did it', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = psaArticle($issue, 'Pronto', 12);
    psaGrid($issue, $user)->call('assignContent', $content->id, psaPage($issue, 2)->id);
    psaSetStatus($issue, 2, PageStatus::OkStampa);

    psaGrid($issue, $user)->call('unassignContent', $content->id, psaPage($issue, 2)->id)
        ->assertDispatched('timeline-result', ok: true, message: 'La pagina 2 è tornata «Da assegnare» (era «Ok stampa»).');

    expect(psaStatus($issue, 2))->toBe(PageStatus::DaAssegnare);
});

test('space from the menu: full → half goes back to «Da assegnare» without a message, half → full to «Assegnata»', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = psaArticle($issue, 'Spazio', 12);
    psaGrid($issue, $user)->call('assignContent', $content->id, psaPage($issue, 2)->id);

    psaGrid($issue, $user)->call('updateContentOccupancy', psaPage($issue, 2)->id, $content->id, 6)
        ->assertNotDispatched('timeline-result');
    expect(psaStatus($issue, 2))->toBe(PageStatus::DaAssegnare);

    psaGrid($issue, $user)->call('updateContentOccupancy', psaPage($issue, 2)->id, $content->id, 12);
    expect(psaStatus($issue, 2))->toBe(PageStatus::Assegnata);
});

test('arrows and rotation do not change the space, so not the status', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = psaAd($issue, 'Mezza', AdFormat::MezzaPaginaOrizzontale);
    psaGrid($issue, $user)->call('assignContent', $content->id, psaPage($issue, 2)->id);
    psaSetStatus($issue, 2, PageStatus::InBozza);

    psaGrid($issue, $user)->call('moveContentOnGrid', psaPage($issue, 2)->id, $content->id, 'down');
    psaGrid($issue, $user)->call('rotateContentOnGrid', psaPage($issue, 2)->id, $content->id);

    expect(psaStatus($issue, 2))->toBe(PageStatus::InBozza);
});

// --- Spostare pagine: lo stato viaggia con la pagina ----------------------------

test('moving or swapping pages does not change any status', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    psaGrid($issue, $user)->call('assignContent', psaArticle($issue, 'Viaggia', 12)->id, psaPage($issue, 2)->id);
    psaSetStatus($issue, 2, PageStatus::Revisionata);
    $page = psaPage($issue, 2);

    psaGrid($issue, $user)->call('movePage', $page->id, 5);
    expect($page->fresh()->status)->toBe(PageStatus::Revisionata)->and($page->fresh()->position)->toBe(5);

    $ii = $issue->covers()->where('cover_slot', 'II')->first();
    $iii = $issue->covers()->where('cover_slot', 'III')->first();
    psaGrid($issue, $user)->call('assignContentToTarget', psaAd($issue, 'Cop', AdFormat::CopertinaSecondaTerzaQuarta)->id, 'II');
    psaGrid($issue, $user)->call('swapCovers', $ii->id, $iii->id);

    expect($ii->fresh()->status)->toBe(PageStatus::Assegnata)
        ->and($iii->fresh()->status)->toBe(PageStatus::DaAssegnare);
});

// --- Pagine bloccate -----------------------------------------------------------

test('a locked page is never touched, even if its space changes', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = psaArticle($issue, 'Bloccato', 12);
    psaGrid($issue, $user)->call('assignContent', $content->id, psaPage($issue, 2)->id);
    psaPage($issue, 2)->update(['locked_at' => now()]);

    // Nessuna azione dell'interfaccia cambia lo spazio di una pagina
    // bloccata: lo si fa a mano per provare la regola.
    app(TimelineActionService::class)->run($issue, $user, 'content.unassign', fn () => psaPage($issue, 2)->contents()->detach($content->id));

    expect(psaStatus($issue, 2))->toBe(PageStatus::Assegnata);
});

// --- Creazione e modifica --------------------------------------------------------

test('creating with «Posizione nel timone» (default 1 page) fills the page', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Con posizione')
        ->set('placement', 'p:3')
        ->call('save')
        ->assertHasNoErrors();

    expect(psaStatus($issue, 3))->toBe(PageStatus::Assegnata);
});

test('«📎 Carica materiale» from a page fills it', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('openForPage', psaPage($issue, 4)->id)
        ->set('title', 'Da pagina')
        ->set('file', UploadedFile::fake()->image('foto.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    expect(psaStatus($issue, 4))->toBe(PageStatus::Assegnata);
});

test('editing the occupancy redistributes and re-evaluates each page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = psaArticle($issue, 'Tre pagine', 36);
    psaGrid($issue, $user)->call('assignContent', $content->id, psaPage($issue, 2)->id);
    expect([psaStatus($issue, 2), psaStatus($issue, 3), psaStatus($issue, 4)])->each->toBe(PageStatus::Assegnata);

    Livewire::actingAs($user)->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('whole_pages', 2)
        ->set('fraction_twelfths', '6')
        ->call('save')
        ->assertHasNoErrors();

    expect([psaStatus($issue, 2), psaStatus($issue, 3), psaStatus($issue, 4)])
        ->toBe([PageStatus::Assegnata, PageStatus::Assegnata, PageStatus::DaAssegnare]);
});

test('«Posizione nel timone» in the edit modal: the old page empties, the new one fills', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = psaArticle($issue, 'Spostato', 12);
    psaGrid($issue, $user)->call('assignContent', $content->id, psaPage($issue, 1)->id);
    psaSetStatus($issue, 1, PageStatus::Revisionata);

    Livewire::actingAs($user)->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('placement', 'p:5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('timeline-result', ok: true, message: 'La pagina 1 è tornata «Da assegnare» (era «Revisionata»).');

    expect(psaStatus($issue, 1))->toBe(PageStatus::DaAssegnare)
        ->and(psaStatus($issue, 5))->toBe(PageStatus::Assegnata);
});

test('converting a placeholder into a real ad does not change the space, so not the status', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $placeholder = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'title' => 'Pubblicità 1 pagina — da assegnare', 'occupancy_twelfths' => 12, 'is_placeholder' => true]);
    Advertisement::factory()->create(['content_id' => $placeholder->id, 'format' => AdFormat::PaginaIntera, 'client' => null]);
    psaGrid($issue, $user)->call('assignContent', $placeholder->id, psaPage($issue, 2)->id);
    psaSetStatus($issue, 2, PageStatus::InBozza);

    Livewire::actingAs($user)->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $placeholder->id)
        ->set('client', 'Cliente vero')
        ->call('save')
        ->assertHasNoErrors();

    expect($placeholder->fresh()->is_placeholder)->toBeFalse()
        ->and(psaStatus($issue, 2))->toBe(PageStatus::InBozza);
});

test('planned full-page ads created with the issue make their pages «Assegnata»', function () {
    $issue = createIssueWithPlannedAds(Magazine::factory()->create(), 32, ['full' => 2, 'half' => 1]);

    $statusOf = fn (string $kind) => Content::where('issue_id', $issue->id)->where('is_placeholder', true)->where('occupancy_twelfths', $kind === 'full' ? 12 : 6)
        ->with('pages')->get()->flatMap->pages->map(fn ($page) => $page->status->value)->unique()->values()->all();

    expect($statusOf('full'))->toBe(['assegnata'])
        ->and($statusOf('half'))->toBe(['da_assegnare']);
});

// --- Commerciale, annulla/ripeti ----------------------------------------------------

test('the Commerciale fills a page with an ad: «Assegnata», and can undo and redo it', function () {
    $issue = reorderableIssue();
    $commerciale = commercialeFor($issue);

    psaGrid($issue, $commerciale)->call('assignContent', psaAd($issue, 'Acme')->id, psaPage($issue, 3)->id);
    expect(psaStatus($issue, 3))->toBe(PageStatus::Assegnata);

    psaGrid($issue, $commerciale)->call('undo')->assertDispatched('timeline-result', ok: true);
    expect(psaStatus($issue, 3))->toBe(PageStatus::DaAssegnare)
        ->and(psaPage($issue, 3)->contents()->count())->toBe(0);

    psaGrid($issue, $commerciale)->call('redo')->assertDispatched('timeline-result', ok: true);
    expect(psaStatus($issue, 3))->toBe(PageStatus::Assegnata);
});

test('the Commerciale still cannot undo a manual status change', function () {
    $issue = reorderableIssue();
    $editor = editorFor($issue);
    psaGrid($issue, $editor)->call('changePageStatus', psaPage($issue, 2)->id, 'in_bozza');
    $step = \App\Models\TimelineAction::where('user_id', $editor->id)->latest('id')->first();

    expect(\App\Support\Timeline\TimelineAuthorizer::allows(commercialeFor($issue), $issue, $step->changes))->toBeFalse();
});

test('undo after a ✕ that reset an «Ok stampa» page gives «Ok stampa» back; redo resets it again', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = psaArticle($issue, 'Stampa', 12);
    psaGrid($issue, $user)->call('assignContent', $content->id, psaPage($issue, 2)->id);
    psaSetStatus($issue, 2, PageStatus::OkStampa);

    psaGrid($issue, $user)->call('unassignContent', $content->id, psaPage($issue, 2)->id);
    expect(psaStatus($issue, 2))->toBe(PageStatus::DaAssegnare);

    psaGrid($issue, $user)->call('undo');
    expect(psaStatus($issue, 2))->toBe(PageStatus::OkStampa);

    psaGrid($issue, $user)->call('redo');
    expect(psaStatus($issue, 2))->toBe(PageStatus::DaAssegnare);
});

test('undo after an assignment that filled the page restores the previous status in one step', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    psaGrid($issue, $user)->call('assignContent', psaArticle($issue, 'Uno', 12)->id, psaPage($issue, 2)->id);
    expect(\App\Models\TimelineAction::where('user_id', $user->id)->count())->toBe(1);

    psaGrid($issue, $user)->call('undo');
    expect(psaStatus($issue, 2))->toBe(PageStatus::DaAssegnare);

    psaGrid($issue, $user)->call('redo');
    expect(psaStatus($issue, 2))->toBe(PageStatus::Assegnata);
});

// --- Altri utenti ----------------------------------------------------------------

test('a second user sees the automatic change without reloading (Reverb event and polling marker)', function () {
    $issue = reorderableIssue();
    $other = editorFor($issue);
    $otherGrid = psaGrid($issue, $other);
    $marker = $issue->fresh()->getRawOriginal('pages_updated_at');
    $this->travel(2)->seconds();

    psaGrid($issue, editorFor($issue))->call('assignContent', psaArticle($issue, 'Remoto', 12)->id, psaPage($issue, 2)->id);

    Event::assertDispatched(PageStatusUpdated::class, fn ($e) => $e->issueId === $issue->id && $e->status === 'assegnata');
    expect($issue->fresh()->getRawOriginal('pages_updated_at'))->not->toBe($marker);

    $this->actingAs($other);
    $otherGrid->call('checkForUpdates')->assertDispatched('remote-change');
    expect(psaStatus($issue, 2))->toBe(PageStatus::Assegnata);
});

// --- «Sposta le pagine esistenti dopo» (PDF reale: serve Imagick) ----------------

test('«Sposta le pagine esistenti dopo»: the moved page keeps its status, the new content fills its pages', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker).');
    }

    $issue = reorderableIssue();
    $user = editorFor($issue);
    psaGrid($issue, $user)->call('assignContent', psaArticle($issue, 'Esistente', 12)->id, psaPage($issue, 3)->id);
    psaSetStatus($issue, 3, PageStatus::OkStampa);
    // Il conflitto scatta su una pagina con un'anteprima (Page::previewFile()).
    PageFile::factory()->for(psaPage($issue, 3))->create();
    $existing = psaPage($issue, 3)->contents()->first();

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('openForPage', psaPage($issue, 2)->id)
        ->set('title', 'Nuovo di due pagine')
        ->set('file', UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([[mmToPt(210), mmToPt(270)], [mmToPt(210), mmToPt(270)]])))
        ->call('save')
        ->assertSet('multipageUploadConflict.conflictingPositions', [3])
        ->call('confirmMultipageUploadWithShift')
        ->assertNotDispatched('timeline-result');

    $moved = $existing->pages()->first();
    expect($moved->position)->toBe(4)
        ->and($moved->status)->toBe(PageStatus::OkStampa)
        ->and(psaStatus($issue, 2))->toBe(PageStatus::Assegnata)
        ->and(psaStatus($issue, 3))->toBe(PageStatus::Assegnata);
});

test('multi-page conflict, «salta»: only the pages that receive the content are evaluated', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker).');
    }

    $issue = reorderableIssue();
    $user = editorFor($issue);
    psaGrid($issue, $user)->call('assignContent', psaArticle($issue, 'Esistente', 6)->id, psaPage($issue, 3)->id);
    psaSetStatus($issue, 3, PageStatus::InBozza);
    PageFile::factory()->for(psaPage($issue, 3))->create();

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('openForPage', psaPage($issue, 2)->id)
        ->set('title', 'Nuovo di due pagine')
        ->set('file', UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([[mmToPt(210), mmToPt(270)], [mmToPt(210), mmToPt(270)]])))
        ->call('save')
        ->assertSet('multipageUploadConflict.conflictingPositions', [3])
        ->call('confirmMultipageUpload', false);

    expect(psaStatus($issue, 2))->toBe(PageStatus::Assegnata)
        ->and(psaStatus($issue, 3))->toBe(PageStatus::InBozza);
});

// --- Riallineamento dei dati esistenti ------------------------------------------------

test('timone:sync-page-status lists both cases, and without --dry-run fixes only full pages in «Da assegnare»', function () {
    $issue = reorderableIssue();
    $full = psaArticle($issue, 'Pieno', 12);
    $half = psaArticle($issue, 'Metà', 6);
    $locked = psaArticle($issue, 'Bloccato', 12);
    psaPage($issue, 1)->contents()->attach($full->id, ['occupied_twelfths' => 12]);
    psaPage($issue, 2)->contents()->attach($half->id, ['occupied_twelfths' => 6]);
    psaSetStatus($issue, 2, PageStatus::Assegnata);
    psaPage($issue, 3)->contents()->attach($locked->id, ['occupied_twelfths' => 12]);
    psaPage($issue, 3)->update(['locked_at' => now()]);

    $this->artisan('timone:sync-page-status', ['--dry-run' => true])
        ->expectsOutputToContain('pagina 1 (1 pagina): piena, «Da assegnare» → «Assegnata»')
        ->expectsOutputToContain('pagina 2 (1/2 di pagina): «Assegnata» ma non piena')
        ->expectsOutputToContain('pagina 3 (1 pagina): piena, «Da assegnare», bloccata')
        ->assertSuccessful();
    expect(psaStatus($issue, 1))->toBe(PageStatus::DaAssegnare);

    $this->artisan('timone:sync-page-status')->assertSuccessful();

    expect([psaStatus($issue, 1), psaStatus($issue, 2), psaStatus($issue, 3)])
        ->toBe([PageStatus::Assegnata, PageStatus::Assegnata, PageStatus::DaAssegnare]);
});
