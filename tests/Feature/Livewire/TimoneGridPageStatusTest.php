<?php

use App\Enums\PageStatus;
use App\Enums\UserRole;
use App\Events\PageStatusUpdated;
use App\Livewire\Timone\Grid;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\PageFile;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function issueWithPages(): Issue
{
    $magazine = Magazine::factory()->create();

    return Issue::factory()->create(['magazine_id' => $magazine->id, 'total_pages' => 6]);
}

function editorForIssue(Issue $issue): User
{
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($issue->magazine);

    return $user;
}

test('an editor with access can change a page status', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = issueWithPages();
    $user = editorForIssue($issue);
    $page = $issue->pages()->first();

    expect($page->status)->toBe(PageStatus::DaAssegnare);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, PageStatus::Revisionata->value);

    expect($page->fresh()->status)->toBe(PageStatus::Revisionata);
});

test('changing a page status dispatches a PageStatusUpdated broadcast event', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = issueWithPages();
    $user = editorForIssue($issue);
    $page = $issue->pages()->first();
    // OkStampa richiede un PDF già caricato (Fase 2, §2.1) — non l'oggetto
    // di questo test (verifica solo il broadcast), quindi ne aggiunge uno.
    PageFile::factory()->for($page)->create();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, PageStatus::OkStampa->value);

    Event::assertDispatched(PageStatusUpdated::class, function (PageStatusUpdated $event) use ($issue, $page, $user) {
        return $event->issueId === $issue->id
            && $event->pageId === $page->id
            && $event->status === PageStatus::OkStampa->value
            && $event->updatedByUserId === $user->id
            && $event->updatedByUserName === $user->name;
    });
});

test('setting a page to its current status is a no-op with no event', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = issueWithPages();
    $user = editorForIssue($issue);
    $page = $issue->pages()->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, $page->status->value);

    Event::assertNotDispatched(PageStatusUpdated::class);
});

test('an invalid status value is ignored', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = issueWithPages();
    $user = editorForIssue($issue);
    $page = $issue->pages()->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, 'stato_inesistente');

    expect($page->fresh()->status)->toBe(PageStatus::DaAssegnare);
    Event::assertNotDispatched(PageStatusUpdated::class);
});

test('an admin can change the status of a page on any issue', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = issueWithPages();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $page = $issue->pages()->first();

    Livewire::actingAs($admin)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, PageStatus::InBozza->value);

    expect($page->fresh()->status)->toBe(PageStatus::InBozza);
});

test('a redattore without access to the magazine cannot change a page status', function () {
    $issue = issueWithPages();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);
    $page = $issue->pages()->first();

    Livewire::actingAs($outsider)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, PageStatus::OkStampa->value);

    expect($page->fresh()->status)->toBe(PageStatus::DaAssegnare);
});

test('a sola lettura user cannot change a page status', function () {
    $issue = issueWithPages();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $page = $issue->pages()->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, PageStatus::OkStampa->value);

    expect($page->fresh()->status)->toBe(PageStatus::DaAssegnare);
});

test('a guest cannot change a page status', function () {
    $issue = issueWithPages();
    $page = $issue->pages()->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, PageStatus::OkStampa->value);

    expect($page->fresh()->status)->toBe(PageStatus::DaAssegnare);
});

test('a page from a different issue cannot be updated through this grid', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = issueWithPages();
    $otherIssue = issueWithPages();
    $user = editorForIssue($issue);
    $otherIssue->magazine->users()->attach($user);
    $otherPage = $otherIssue->pages()->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $otherPage->id, PageStatus::OkStampa->value);

    expect($otherPage->fresh()->status)->toBe(PageStatus::DaAssegnare);
    Event::assertNotDispatched(PageStatusUpdated::class);
});
