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
use App\Livewire\Timone\BulkUpload;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\ContentEdit;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;
use App\Models\User;
use App\Support\PageGroupLinker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Revisione 2, Fase 4.1 (2026-10-28): il Commerciale sposta, crea e
// modifica SOLO le pubblicità (scelta dell'utente: anche crearle e
// modificarle). Controlli lato server (ContentPolicy/IssuePolicy, regole in
// App\Support\Permissions\TimoneAbilities) + interfaccia.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    Event::fake([ContentAssigned::class, ContentUnassigned::class, ContentCreated::class, ContentListChanged::class, PageContentsReordered::class, PageGroupsChanged::class, PageMoved::class]);
});

function commercialeFor(Issue $issue, UserRole $role = UserRole::Commerciale): User
{
    $user = User::factory()->create(['role' => $role]);
    $user->magazines()->attach($issue->magazine);

    return $user;
}

function articleIn(Issue $issue, ?int $position = null): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'articolo', 'title' => 'Servizio redazionale', 'occupancy_twelfths' => 6]);
    Article::factory()->create(['content_id' => $content->id]);

    if ($position !== null) {
        $issue->pages()->where('position', $position)->first()->contents()->attach($content->id, ['occupied_twelfths' => 6]);
    }

    return $content;
}

function adIn(Issue $issue, ?int $position = null): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => 'pubblicita', 'title' => 'Acme', 'occupancy_twelfths' => 6]);
    Advertisement::factory()->create(['content_id' => $content->id, 'format' => AdFormat::MezzaPaginaOrizzontale, 'client' => 'Acme']);

    if ($position !== null) {
        $issue->pages()->where('position', $position)->first()->contents()->attach($content->id, ['occupied_twelfths' => 6]);
    }

    return $content;
}

function timonePageAt(Issue $issue, int $position): Page
{
    return $issue->pages()->where('position', $position)->first();
}

// --- Test richiesti dalla specifica ---

test('spec: moving an article as commerciale is forbidden (403), moving an ad is allowed', function () {
    $issue = reorderableIssue();
    $article = articleIn($issue);
    $ad = adIn($issue);
    $user = commercialeFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $article->id, timonePageAt($issue, 2)->id)->assertForbidden();
    expect($article->fresh()->isAssigned())->toBeFalse();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $ad->id, timonePageAt($issue, 2)->id)->assertOk();
    expect($ad->fresh()->isAssigned())->toBeTrue();
});

test('commerciale: arrows, rotation, extend and removal work on ads and are forbidden on articles', function () {
    $issue = reorderableIssue();
    $article = articleIn($issue, 1);
    $ad = adIn($issue, 2);
    $user = commercialeFor($issue);

    // Un 403 lascia il componente di test inutilizzabile: uno nuovo per ogni chiamata rifiutata.
    foreach ([
        ['moveContentOnGrid', timonePageAt($issue, 1)->id, $article->id, 'down'],
        ['rotateContentOnGrid', timonePageAt($issue, 1)->id, $article->id],
        ['extendToPage', $article->id, 3],
        ['updateContentOccupancy', timonePageAt($issue, 1)->id, $article->id, 4],
        ['unassignContent', $article->id, timonePageAt($issue, 1)->id],
        ['renameContent', $article->id, 'Nuovo titolo'],
        ['archiveContent', $article->id],
    ] as $call) {
        Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])->call(...$call)->assertForbidden();
    }

    $grid = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    expect($article->fresh()->pages()->pluck('pages.position')->all())->toBe([1])
        ->and($article->fresh()->title)->toBe('Servizio redazionale');

    $grid->call('moveContentOnGrid', timonePageAt($issue, 2)->id, $ad->id, 'down')->assertOk();
    $grid->call('renameContent', $ad->id, 'Acme Tools')->assertOk();
    $grid->call('unassignContent', $ad->id, timonePageAt($issue, 2)->id)->assertOk();

    expect($ad->fresh()->isAssigned())->toBeFalse()
        ->and($ad->fresh()->advertisement->client)->toBe('Acme Tools');
});

test('commerciale: pages with articles (or in a group with one) cannot be moved; pages with only ads can', function () {
    $issue = reorderableIssue();
    articleIn($issue, 1);
    $adPage = timonePageAt($issue, 3);
    adIn($issue, 3);
    $user = commercialeFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])->call('movePage', timonePageAt($issue, 1)->id, 5)->assertForbidden();
    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])->call('movePage', $adPage->id, 5)->assertOk();
    expect($adPage->fresh()->position)->toBe(5);

    // Gruppo: pagina con pubblicità collegata a una con un articolo.
    $issue->refresh();
    PageGroupLinker::link($issue, collect([timonePageAt($issue, 1), timonePageAt($issue, 2)]));
    adIn($issue, 2);

    Livewire::actingAs(commercialeFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('movePage', timonePageAt($issue, 2)->id, 6)
        ->assertForbidden();
});

test('commerciale: page status, lock and other timone settings stay forbidden', function () {
    $issue = reorderableIssue();
    $user = commercialeFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])->call('changePageStatus', timonePageAt($issue, 1)->id, 'revisionata')->assertForbidden();
    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])->call('togglePageLock', timonePageAt($issue, 1)->id)->assertForbidden();
});

test('commerciale: creates ads (form and bulk upload) but not articles', function () {
    $issue = reorderableIssue();
    $user = commercialeFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->assertSet('type', 'pubblicita')
        ->set('type', 'articolo')->set('title', 'Articolo vietato')->set('whole_pages', 1)
        ->call('save')
        ->assertForbidden();

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('format', 'mezza_pagina_orizzontale')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($user)->test(BulkUpload::class, ['issue' => $issue])
        ->set('upload', UploadedFile::fake()->image('articolo.png'))->call('importUploaded', 'articolo')->assertForbidden();
    Livewire::actingAs($user)->test(BulkUpload::class, ['issue' => $issue])
        ->set('upload', UploadedFile::fake()->image('acme.png'))->call('importUploaded', 'pubblicita')->assertOk();

    expect(Content::where('issue_id', $issue->id)->pluck('type')->map->value->all())->toBe(['pubblicita', 'pubblicita']);
});

test('commerciale: edits and moves an ad from the modal, cannot open an article nor turn an ad into one', function () {
    $issue = reorderableIssue();
    $article = articleIn($issue);
    $ad = adIn($issue);
    $user = commercialeFor($issue);

    Livewire::actingAs($user)->test(ContentEdit::class, ['issue' => $issue])->call('open', $article->id)->assertForbidden();

    $edit = Livewire::actingAs($user)->test(ContentEdit::class, ['issue' => $issue]);
    $edit->call('open', $ad->id)->assertOk()
        ->set('client', 'Acme Tools')
        ->set('placement', 'p:4')
        ->call('save')
        ->assertHasNoErrors();

    expect($ad->fresh()->advertisement->client)->toBe('Acme Tools')
        ->and($ad->fresh()->pages()->pluck('pages.position')->all())->toBe([4]);

    $edit->call('open', $ad->id)->set('type', 'articolo')->set('title', 'Ora articolo')->call('save')->assertForbidden();
    expect($ad->fresh()->type->value)->toBe('pubblicita');
});

test('sola lettura still cannot move anything; redattore can still move articles', function () {
    $issue = reorderableIssue();
    $article = articleIn($issue);
    $ad = adIn($issue);

    Livewire::actingAs(commercialeFor($issue, UserRole::SolaLettura))->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $ad->id, timonePageAt($issue, 1)->id)
        ->assertForbidden();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $article->id, timonePageAt($issue, 1)->id)
        ->assertOk();

    expect($article->fresh()->isAssigned())->toBeTrue()
        ->and($ad->fresh()->isAssigned())->toBeFalse();
});

test('UI for commerciale: articles not draggable (cursor and tooltip), arrows disabled, pages with articles without handle', function () {
    $issue = reorderableIssue();
    articleIn($issue);
    articleIn($issue, 1);
    adIn($issue);
    adIn($issue, 3);

    $html = Livewire::actingAs(commercialeFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    expect($html)->toContain('data-not-movable="1"')
        ->toContain('cursor-not-allowed opacity-70')
        ->toContain('Solo la redazione può spostare questo contenuto')
        ->toContain('data-testid="page-not-movable"')
        ->toContain('Solo la redazione può spostare pagine che contengono articoli')
        ->toContain('data-testid="editorial-only-note"')
        // la pubblicità resta trascinabile
        ->toContain("setData('text/content-id'")
        // stati e blocco pagina non compaiono
        ->not->toContain('wire:change="changePageStatus')
        ->not->toContain('wire:click="togglePageLock');

    // pagina 3 (solo pubblicità) ha ancora la maniglia
    expect($html)->toContain('⠿ 3')->not->toContain('⠿ 1');
});

test('UI for redattore is unchanged: every page has its handle, no "solo la redazione" note', function () {
    $issue = reorderableIssue();
    articleIn($issue, 1);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    expect($html)->toContain('⠿ 1')
        ->not->toContain('data-testid="page-not-movable"')
        ->not->toContain('Solo la redazione può spostare');
});
