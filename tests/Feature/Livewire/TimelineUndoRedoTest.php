<?php

use App\Enums\AdFormat;
use App\Enums\UserRole;
use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\ContentListChanged;
use App\Events\ContentUnassigned;
use App\Events\PageContentsReordered;
use App\Events\PageGroupsChanged;
use App\Events\PageMoved;
use App\Events\PagesBlockMoved;
use App\Events\PageStatusUpdated;
use App\Events\TimelineApplied;
use App\Livewire\Timone\BulkUpload;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\ContentEdit;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use App\Models\TimelineAction;
use App\Models\User;
use App\Support\PageGroupLinker;
use App\Support\Timeline\TimelineActionService;
use App\Support\Timeline\TimelineSnapshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Annulla/ripeti (2026-10-28): App\Support\Timeline\*, Grid::undo()/redo(),
// tabella timone_azioni. reorderableIssue()/editorFor() sono in
// TimoneGridReorderTest.php (numero da 6 pagine interne).

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    Event::fake([ContentAssigned::class, ContentCreated::class, ContentListChanged::class, ContentUnassigned::class, PageContentsReordered::class, PageGroupsChanged::class, PageMoved::class, PagesBlockMoved::class, PageStatusUpdated::class, TimelineApplied::class]);
});

/** Stato del timone senza i timestamp (che cambiano a ogni scrittura). */
function timoneState(Issue $issue): array
{
    return collect(TimelineSnapshot::take($issue->id))
        ->map(fn ($rows) => collect($rows)->map(fn ($row) => array_map(fn ($v) => $v === null ? null : (string) $v, array_diff_key($row, ['created_at' => 1, 'updated_at' => 1])))->sortKeys()->all())
        ->all();
}

function grid(Issue $issue, User $user)
{
    return Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);
}

function timelineArticle(Issue $issue, string $title, ?int $position = null, int $twelfths = 6): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'articolo', 'title' => $title, 'occupancy_twelfths' => $twelfths]);
    Article::factory()->create(['content_id' => $content->id]);

    if ($position !== null) {
        $issue->pages()->where('position', $position)->first()->contents()->attach($content->id, ['occupied_twelfths' => $twelfths]);
    }

    return $content;
}

test('spec: move → undo gives back exactly the initial state; redo gives back exactly the state after', function () {
    $issue = reorderableIssue();
    timelineArticle($issue, 'A pagina 2', 2);
    $user = editorFor($issue);
    $initial = timoneState($issue);

    grid($issue, $user)->call('movePage', $issue->pages()->where('position', 2)->first()->id, 5);
    $after = timoneState($issue);
    expect($after)->not->toBe($initial);

    grid($issue, $user)->call('undo')->assertDispatched('timeline-result', ok: true, message: 'Annullato: Sposta pagina 2 → 5', canRedo: true);
    expect(timoneState($issue))->toBe($initial);

    grid($issue, $user)->call('redo')->assertDispatched('timeline-result', ok: true);
    expect(timoneState($issue))->toBe($after);
});

test('spec: a group move is undone in one step', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('position')->get();
    PageGroupLinker::link($issue, collect([$pages[1], $pages[2]]));
    $user = editorFor($issue);
    $initial = timoneState($issue);

    grid($issue, $user)->call('movePage', $pages[1]->id, 5);
    expect(TimelineAction::count())->toBe(1);

    grid($issue, $user)->call('undo');
    expect(timoneState($issue))->toBe($initial)
        ->and(TimelineAction::sole()->status)->toBe(TimelineAction::UNDONE);
});

test('spec: A moves, B changes the same page, A undoes → conflict, nothing changes, step marked', function () {
    $issue = reorderableIssue();
    $a = editorFor($issue);
    $b = User::factory()->create(['role' => UserRole::Redattore, 'name' => 'Mario']);
    $b->magazines()->attach($issue->magazine);
    $page = $issue->pages()->where('position', 2)->first();

    grid($issue, $a)->call('changePageStatus', $page->id, 'in_bozza');
    $this->travel(1)->minutes();
    grid($issue, $b)->call('changePageStatus', $page->id, 'revisionata');
    $beforeUndo = timoneState($issue);

    grid($issue, $a)->call('undo')->assertDispatched('timeline-result', fn ($name, $params) => $params['ok'] === false && str_contains($params['message'], 'la pagina 2 è stata modificata da Mario alle'));

    expect(timoneState($issue))->toBe($beforeUndo)
        ->and(TimelineAction::where('user_id', $a->id)->sole()->blocked_reason)->toContain('Mario');
});

test('spec: A undoes only A\'s steps; B\'s actions stay intact', function () {
    $issue = reorderableIssue();
    $a = editorFor($issue);
    $b = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();

    grid($issue, $a)->call('changePageStatus', $pages[0]->id, 'in_bozza');
    grid($issue, $b)->call('changePageStatus', $pages[3]->id, 'revisionata');

    grid($issue, $a)->call('undo')->assertDispatched('timeline-result', ok: true);

    expect($pages[0]->fresh()->status->value)->toBe('da_assegnare')
        ->and($pages[3]->fresh()->status->value)->toBe('revisionata')
        ->and(TimelineAction::where('user_id', $b->id)->sole()->status)->toBe(TimelineAction::DONE);
});

test('spec: a new action after an undo empties the redo stack', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();

    grid($issue, $user)->call('changePageStatus', $pages[0]->id, 'in_bozza');
    grid($issue, $user)->call('undo');
    expect(app(TimelineActionService::class)->state($issue, $user)['redo'])->not->toBeNull();

    grid($issue, $user)->call('changePageStatus', $pages[1]->id, 'in_bozza');

    expect(app(TimelineActionService::class)->state($issue, $user)['redo'])->toBeNull()
        ->and(TimelineAction::where('status', TimelineAction::UNDONE)->count())->toBe(0);
});

test('spec: undoing a deletion restores the content with its data; undoing a placed creation removes it, redo brings it back with its pages', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = timelineArticle($issue, 'Da eliminare');
    $articleId = $content->article->id;

    grid($issue, $user)->call('deleteUnassignedContent', $content->id, true);
    expect(Content::find($content->id))->toBeNull();

    grid($issue, $user)->call('undo')->assertDispatched('timeline-result', ok: true);
    expect(Content::find($content->id))->not->toBeNull()
        ->and(Article::find($articleId))->not->toBeNull();

    // Creazione con posizione: un passo solo, che comprende l'assegnazione.
    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')->set('title', 'Nuovo pezzo')->set('whole_pages', 2)->set('placement', 'p:3')
        ->call('save')->assertHasNoErrors();
    $created = Content::where('title', 'Nuovo pezzo')->sole();
    expect($created->pages()->pluck('pages.position')->all())->toBe([3, 4]);

    grid($issue, $user)->call('undo')->assertDispatched('timeline-result', ok: true);
    expect(Content::find($created->id))->toBeNull()
        ->and(Content::withTrashed()->find($created->id)->pages()->count())->toBe(0);

    grid($issue, $user)->call('redo')->assertDispatched('timeline-result', ok: true);
    expect(Content::find($created->id)->pages()->pluck('pages.position')->sort()->values()->all())->toBe([3, 4]);
});

test('spec: a commerciale cannot undo or redo a step that touches an article (server side)', function () {
    $issue = reorderableIssue();
    $editor = editorFor($issue);
    $commerciale = User::factory()->create(['role' => UserRole::Commerciale]);
    $commerciale->magazines()->attach($issue->magazine);
    $article = timelineArticle($issue, 'Articolo');

    // Il passo (un articolo assegnato) è registrato a nome del commerciale,
    // come se un tempo avesse avuto i permessi: annullarlo ora è vietato.
    app(TimelineActionService::class)->run($issue, $commerciale, 'content.assign', fn () => $issue->pages()->first()->contents()->attach($article->id, ['occupied_twelfths' => 6]));

    grid($issue, $commerciale)->call('undo')->assertForbidden();
    expect($article->fresh()->pages)->toHaveCount(1);

    // Una pubblicità invece sì.
    $ad = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'title' => 'Acme', 'occupancy_twelfths' => 6]);
    Advertisement::factory()->create(['content_id' => $ad->id, 'format' => AdFormat::MezzaPaginaOrizzontale]);
    grid($issue, $commerciale)->call('assignContentToTarget', $ad->id, '4');
    grid($issue, $commerciale)->call('undo')->assertDispatched('timeline-result', ok: true);
    expect($ad->fresh()->pages)->toBeEmpty();
});

test('spec: at most 50 steps per user and issue; steps older than 24 hours are pruned', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();

    foreach (range(1, 52) as $i) {
        grid($issue, $user)->call('changePageStatus', $page->id, $i % 2 ? 'in_bozza' : 'revisionata');
    }

    expect(TimelineAction::where('user_id', $user->id)->count())->toBe(50);

    $this->travel(25)->hours();
    $this->artisan('timone:history-prune')->assertSuccessful();

    expect(TimelineAction::count())->toBe(0);
});

test('the prune command removes contents deleted more than 24 hours ago, with their files', function () {
    $issue = reorderableIssue();
    $content = timelineArticle($issue, 'Vecchio');
    Storage::disk('local')->put('contents/x/a.pdf', 'pdf');
    ContentFile::factory()->create(['content_id' => $content->id, 'disk' => 'local', 'path' => 'contents/x/a.pdf']);
    $content->delete();

    $this->artisan('timone:history-prune')->assertSuccessful();
    expect(Content::withTrashed()->find($content->id))->not->toBeNull();

    $this->travel(25)->hours();
    $this->artisan('timone:history-prune')->assertSuccessful();

    expect(Content::withTrashed()->find($content->id))->toBeNull();
    Storage::disk('local')->assertMissing('contents/x/a.pdf');
});

test('undo is refused when it would put a content where someone else has put something meanwhile', function () {
    $issue = reorderableIssue();
    $a = editorFor($issue);
    $b = editorFor($issue);
    $content = timelineArticle($issue, 'Piena', 2, 12);
    $page = $issue->pages()->where('position', 2)->first();

    grid($issue, $a)->call('unassignContent', $content->id, $page->id);
    $other = timelineArticle($issue, 'Del collega');
    grid($issue, $b)->call('assignContentToTarget', $other->id, '2');
    $beforeUndo = timoneState($issue);

    grid($issue, $a)->call('undo')->assertDispatched('timeline-result', fn ($name, $params) => $params['ok'] === false && str_contains($params['message'], 'non c\'è più spazio'));

    expect(timoneState($issue))->toBe($beforeUndo)
        ->and($content->fresh()->pages)->toBeEmpty();
});

test('a bulk upload batch is one step', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $bulk = fn () => Livewire::actingAs($user)->test(BulkUpload::class, ['issue' => $issue]);

    foreach (['uno.png', 'due.png', 'tre.png'] as $name) {
        $bulk()->set('upload', UploadedFile::fake()->image($name))->call('importUploaded', 'articolo', 'lotto-1');
    }

    expect(TimelineAction::count())->toBe(1)
        ->and(TimelineAction::sole()->label)->toBe('Crea 3 contenuti (caricamento multiplo)');

    grid($issue, $user)->call('undo')->assertDispatched('timeline-result', ok: true);
    expect(Content::where('issue_id', $issue->id)->count())->toBe(0);
});

test('an edit that replaced the PDF stays in history but cannot be undone', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = timelineArticle($issue, 'Con file', null, 12);

    Livewire::actingAs($user)->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('file', UploadedFile::fake()->image('nuovo.png'))
        ->call('save')->assertHasNoErrors();

    grid($issue, $user)->call('undo')->assertDispatched('timeline-result', fn ($name, $params) => $params['ok'] === false && str_contains($params['message'], 'Non annullabile') && str_contains($params['message'], 'PDF'));
});

test('actions that change nothing are not recorded; the buttons show the step in the tooltip', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();

    grid($issue, $user)->call('changePageStatus', $page->id, 'da_assegnare'); // già così
    expect(TimelineAction::count())->toBe(0);

    grid($issue, $user)->call('changePageStatus', $page->id, 'revisionata');

    grid($issue, $user)
        ->assertSeeHtml('title="Annulla: Stato pagina 1 → Revisionata (Ctrl/⌘+Z)"')
        ->assertSeeHtml('title="Niente da ripetere"');
});

test('labels: a page moved by one place is a swap, a longer move names the moved page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();

    grid($issue, $user)->call('nudgePage', $pages[1]->id, 1);
    expect(TimelineAction::latest('id')->first()->label)->toBe('Scambia pagina 2 e pagina 3');

    // fresh(): la reorder_version è cambiata con lo spostamento precedente.
    grid($issue->fresh(), $user)->call('movePage', $pages[0]->id, 5);
    expect(TimelineAction::latest('id')->first()->label)->toBe('Sposta pagina 1 → 5');
});

// Ctrl+Z annulla solo le MIE azioni (2026-10-29): creazione e modifica con file
// non sono in transazione, quindi un collega può scrivere fra le due fotografie.
// Qui la scrittura di B avviene proprio durante l'azione di A (evento del model).
function whileAIsWorking(Issue $issue, string $title, Closure $writesOfB): void
{
    $done = false;
    Content::saved(function (Content $content) use ($title, $writesOfB, &$done) {
        if (! $done && $content->title === $title) {
            $done = true;
            $writesOfB();
        }
    });
}

test('A\'s undo of a creation does not undo what B did meanwhile', function () {
    $issue = reorderableIssue();
    $a = editorFor($issue);
    $b = editorFor($issue);
    $page1 = $issue->pages()->where('position', 1)->first();
    $bContent = null;

    whileAIsWorking($issue, 'Pezzo di A', function () use ($issue, $page1, &$bContent) {
        $page1->update(['status' => 'revisionata']);
        $bContent = timelineArticle($issue, 'Pezzo di B', 6);
    });

    Livewire::actingAs($a)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')->set('title', 'Pezzo di A')->set('whole_pages', 1)->set('placement', 'p:3')
        ->call('save')->assertHasNoErrors();

    $step = TimelineAction::where('user_id', $a->id)->sole();
    $contentIds = collect($step->changes)->where('t', 'contents')->pluck('id')->all();
    expect($contentIds)->toBe([Content::where('title', 'Pezzo di A')->value('id')])
        ->and(collect($step->changes)->where('t', 'pages')->pluck('id')->all())->not->toContain($page1->id);

    grid($issue, $a)->call('undo')->assertDispatched('timeline-result', ok: true);

    expect(Content::where('title', 'Pezzo di A')->exists())->toBeFalse()
        ->and(Content::find($bContent->id))->not->toBeNull()
        ->and($bContent->pages()->pluck('pages.position')->all())->toBe([6])
        ->and($page1->fresh()->status->value)->toBe('revisionata');
});

test('A\'s undo of an edit and of a bulk upload does not undo what B did meanwhile', function () {
    $issue = reorderableIssue();
    $a = editorFor($issue);
    $b = editorFor($issue);
    $page2 = $issue->pages()->where('position', 2)->first();
    $mine = timelineArticle($issue, 'Titolo vecchio', 4);

    whileAIsWorking($issue, 'Titolo nuovo', fn () => $page2->update(['status' => 'revisionata']));
    Livewire::actingAs($a)->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $mine->id)->set('title', 'Titolo nuovo')->call('save')->assertHasNoErrors();

    grid($issue, $a)->call('undo')->assertDispatched('timeline-result', ok: true);
    expect($mine->fresh()->title)->toBe('Titolo vecchio')
        ->and($page2->fresh()->status->value)->toBe('revisionata');

    $bContent = null;
    whileAIsWorking($issue, 'caricato', function () use ($issue, &$bContent) {
        $bContent = timelineArticle($issue, 'Di B durante il lotto', 5);
    });
    Livewire::actingAs($a)->test(BulkUpload::class, ['issue' => $issue])
        ->set('upload', UploadedFile::fake()->image('caricato.png'))->call('importUploaded', 'articolo', 'lotto-a');

    grid($issue, $a)->call('undo')->assertDispatched('timeline-result', ok: true);
    expect(Content::where('title', 'caricato')->exists())->toBeFalse()
        ->and(Content::find($bContent->id)?->pages()->pluck('pages.position')->all())->toBe([5]);
});

test('undo and redo never pick another user\'s step, even when it is the most recent on the issue', function () {
    $issue = reorderableIssue();
    $a = editorFor($issue);
    $b = editorFor($issue);
    $pages = $issue->pages()->orderBy('position')->get();

    grid($issue, $b)->call('changePageStatus', $pages[1]->id, 'revisionata');

    // A non ha passi: niente da annullare, il passo di B resta com'è.
    grid($issue, $a)->call('undo')->assertDispatched('timeline-result', ok: false, message: 'Niente da annullare.');
    expect($pages[1]->fresh()->status->value)->toBe('revisionata');

    grid($issue, $a)->call('changePageStatus', $pages[0]->id, 'in_bozza');
    grid($issue, $b)->call('changePageStatus', $pages[2]->id, 'revisionata');

    grid($issue, $a)->call('undo')->assertDispatched('timeline-result', ok: true);
    grid($issue, $a)->call('undo')->assertDispatched('timeline-result', ok: false, message: 'Niente da annullare.');
    grid($issue, $a)->call('redo')->assertDispatched('timeline-result', ok: true);
    grid($issue, $a)->call('redo')->assertDispatched('timeline-result', ok: false, message: 'Niente da ripetere.');

    expect($pages[0]->fresh()->status->value)->toBe('in_bozza')
        ->and($pages[1]->fresh()->status->value)->toBe('revisionata')
        ->and($pages[2]->fresh()->status->value)->toBe('revisionata')
        ->and(TimelineAction::where('user_id', $b->id)->pluck('status')->unique()->all())->toBe([TimelineAction::DONE])
        ->and(grid($issue, $a)->set('showTimelineMenu', true)->viewData('timelineHistory'))->toHaveCount(1)
        ->and(grid($issue, $a)->set('showTimelineMenu', true)->viewData('timelineHistory')->pluck('id')->diff(TimelineAction::where('user_id', $a->id)->pluck('id'))->all())->toBe([]);
});
