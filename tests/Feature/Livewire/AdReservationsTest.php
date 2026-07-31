<?php

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\IssueStatus;
use App\Enums\UserRole;
use App\Livewire\Timone\AdReservations;
use App\Models\Advertisement;
use App\Models\ActivityLog;
use App\Models\Content;
use App\Models\PageFile;
use App\Models\User;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php,
// assignedAdContent() in TimoneGridAdDashboardTest.php — riusate qui.

function reservedAdContent(App\Models\Issue $issue, string $client = 'Cliente prenotato', ?int $preferredPosition = null): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita, 'title' => $client]);
    Advertisement::factory()->create([
        'content_id' => $content->id,
        'client' => $client,
        'preferred_position' => $preferredPosition,
    ]);

    return $content;
}

test('a reservation with no page assigned shows up as prenotato', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    reservedAdContent($issue, 'Acme Corp', preferredPosition: 4);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('toggle')
        ->assertSee('Acme Corp')
        ->assertSee('Prenotato')
        ->assertSee('posizione preferita: pagina 4');
});

test('an advertisement assigned to a page but without a pdf shows up as assegnato', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = assignedAdContent($issue, 1, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('toggle')
        ->assertSee($content->advertisement->client)
        ->assertSee('Assegnato')
        ->assertSee('su pagina 1');
});

test('an advertisement with a matching pdf shows up as completo', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = assignedAdContent($issue, 1, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata);
    $page = $issue->pages()->where('position', 1)->first();
    PageFile::factory()->for($page)->formatMatching()->create();

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('toggle')
        ->assertSee('Completo');
});

test('an editor can delete an unassigned reservation', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = reservedAdContent($issue);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('deleteReservation', $content->id);

    expect(Content::whereKey($content->id)->exists())->toBeFalse()
        ->and(Advertisement::where('content_id', $content->id)->exists())->toBeFalse();
});

test('deleting a reservation that is already assigned to a page does nothing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = assignedAdContent($issue, 1, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('deleteReservation', $content->id);

    expect(Content::whereKey($content->id)->exists())->toBeTrue();
});

test('a reservation from a different issue cannot be deleted through this component', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $otherIssue = reorderableIssue();
    $content = reservedAdContent($otherIssue);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('deleteReservation', $content->id);

    expect(Content::whereKey($content->id)->exists())->toBeTrue();
});

test('a guest cannot delete a reservation', function () {
    $issue = reorderableIssue();
    $content = reservedAdContent($issue);

    Livewire::test(AdReservations::class, ['issue' => $issue])
        ->call('deleteReservation', $content->id);

    expect(Content::whereKey($content->id)->exists())->toBeTrue();
});

test('a sola lettura user cannot delete a reservation', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $content = reservedAdContent($issue);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('deleteReservation', $content->id);

    expect(Content::whereKey($content->id)->exists())->toBeTrue();
});

test('closing an issue is blocked while a reservation is still incomplete', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    reservedAdContent($issue, 'Cliente in sospeso');

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('closeIssue')
        ->assertHasErrors('close');

    expect($issue->fresh()->status)->not->toBe(IssueStatus::Chiuso);
});

test('closing an issue is blocked while an assigned ad still has no pdf', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    assignedAdContent($issue, 1, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('closeIssue')
        ->assertHasErrors('close');

    expect($issue->fresh()->status)->not->toBe(IssueStatus::Chiuso);
});

test('an issue with no ads at all, or only complete ones, can be closed', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = assignedAdContent($issue, 1, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata);
    $page = $issue->pages()->where('position', 1)->first();
    PageFile::factory()->for($page)->formatMatching()->create();

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('closeIssue')
        ->assertHasNoErrors();

    expect($issue->fresh()->status)->toBe(IssueStatus::Chiuso);
});

test('deleting the blocking reservation unblocks closing the issue', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = reservedAdContent($issue);

    $component = Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('closeIssue')
        ->assertHasErrors('close');

    $component->call('deleteReservation', $content->id)
        ->call('closeIssue')
        ->assertHasNoErrors();

    expect($issue->fresh()->status)->toBe(IssueStatus::Chiuso);
});

test('closing an already closed issue is a no-op', function () {
    $issue = reorderableIssue();
    $issue->update(['status' => IssueStatus::Chiuso]);
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('closeIssue')
        ->assertHasNoErrors();

    expect($issue->fresh()->status)->toBe(IssueStatus::Chiuso);
});

test('a guest cannot close an issue', function () {
    $issue = reorderableIssue();

    Livewire::test(AdReservations::class, ['issue' => $issue])
        ->call('closeIssue');

    expect($issue->fresh()->status)->not->toBe(IssueStatus::Chiuso);
});

test('deleting a reservation is recorded in the activity log', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = reservedAdContent($issue, 'Cliente Tracciato');

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('deleteReservation', $content->id);

    expect(ActivityLog::where('issue_id', $issue->id)->where('action', 'content.reservation_deleted')->exists())->toBeTrue();
});

test('closing an issue is recorded in the activity log', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('closeIssue');

    expect(ActivityLog::where('issue_id', $issue->id)->where('action', 'issue.closed')->exists())->toBeTrue();
});

test('a sola lettura user cannot close an issue', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);

    Livewire::actingAs($user)->test(AdReservations::class, ['issue' => $issue])
        ->call('closeIssue');

    expect($issue->fresh()->status)->not->toBe(IssueStatus::Chiuso);
});
