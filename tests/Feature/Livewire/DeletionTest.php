<?php

use App\Enums\UserRole;
use App\Events\IssueDeleted;
use App\Jobs\DeleteStoredFiles;
use App\Livewire\Admin\ConfirmDeletion;
use App\Models\ActivityLog;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\Page;
use App\Models\PageReorderLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Revisione 2, Fasi 4.2–4.3 (2026-10-28): eliminazione definitiva di
// numeri, riviste e utenti, solo admin, conferma in due passaggi con
// password + testo digitato, file cancellati da un job dopo il commit.

beforeEach(function () {
    Queue::fake();
    Event::fake([IssueDeleted::class]);
    Storage::fake('local');
    RateLimiter::clear('dangerous-delete:1');
});

function deletionAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin, 'password' => bcrypt('segreta-admin')]);
}

/** Numero con 2 contenuti, uno con un file da 2 MB (+ miniatura). */
function issueWithMaterial(?Magazine $magazine = null, string $title = 'Ottobre 2026'): Issue
{
    $issue = Issue::factory()->create(['title' => $title, 'total_pages' => 4] + ($magazine ? ['magazine_id' => $magazine->id] : []));
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    Content::factory()->advertisement()->create(['issue_id' => $issue->id]);
    Storage::disk('local')->put("contents/{$content->id}/a.pdf", 'pdf');
    Storage::disk('local')->put("thumbnails/{$content->id}.png", 'png');
    ContentFile::factory()->create([
        'content_id' => $content->id,
        'disk' => 'local',
        'path' => "contents/{$content->id}/a.pdf",
        'thumbnail_path' => "thumbnails/{$content->id}.png",
        'size' => 2 * 1024 * 1024,
    ]);

    return $issue;
}

function deletionComponent(User $as, string $kind, int $id)
{
    return Livewire::actingAs($as)->test(ConfirmDeletion::class, ['kind' => $kind, 'targetId' => $id]);
}

test('only an admin can open the deletion of an issue or a magazine (server-side)', function () {
    $issue = issueWithMaterial();
    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $redattore->magazines()->attach($issue->magazine);

    deletionComponent($redattore, 'issue', $issue->id)->call('start')->assertForbidden();
    deletionComponent($redattore, 'magazine', $issue->magazine_id)->call('start')->assertForbidden();
    deletionComponent($redattore, 'issue', $issue->id)->set('typed', 'Ottobre 2026')->set('password', 'password')->call('confirm')->assertForbidden();

    expect(Issue::find($issue->id))->not->toBeNull();
});

test('step 1 shows what will be deleted: pages, contents, files and MB', function () {
    $issue = issueWithMaterial();

    deletionComponent(deletionAdmin(), 'issue', $issue->id)
        ->call('start')
        ->assertSee('Eliminare il numero «Ottobre 2026»')
        ->assertSee('8 pagine (copertine comprese)')
        ->assertSee('2 contenuti')
        ->assertSee('1 file caricato (2 MB)')
        ->call('next')
        ->assertSet('step', 2)
        ->assertSee('Per confermare scrivi');
});

test('step 2 needs the exact name and the admin password; nothing is deleted otherwise', function () {
    $issue = issueWithMaterial();
    $component = deletionComponent(deletionAdmin(), 'issue', $issue->id)->call('start')->call('next');

    $component->set('typed', 'ottobre 2026')->set('password', 'segreta-admin')->call('confirm')->assertHasErrors('typed');
    $component->set('typed', 'Ottobre 2026')->set('password', 'sbagliata')->call('confirm')->assertHasErrors(['password']);

    expect(Issue::find($issue->id))->not->toBeNull();
    Queue::assertNothingPushed();
});

test('wrong passwords are rate limited (5 per minute)', function () {
    $issue = issueWithMaterial();
    $component = deletionComponent(deletionAdmin(), 'issue', $issue->id)->call('start')->call('next')->set('typed', 'Ottobre 2026');

    foreach (range(1, 5) as $attempt) {
        $component->set('password', 'sbagliata-'.$attempt)->call('confirm')->assertHasErrors(['password']);
    }

    // Anche con la password giusta: bloccato finché non passa il minuto.
    $component->set('password', 'segreta-admin')->call('confirm')->assertSee('Troppi tentativi');
    expect(Issue::find($issue->id))->not->toBeNull();
});

test('confirmed: the issue and everything in it are deleted, files queued after commit, logged, others notified', function () {
    $issue = issueWithMaterial();
    $admin = deletionAdmin();
    $pageIds = Page::where('issue_id', $issue->id)->pluck('id');

    deletionComponent($admin, 'issue', $issue->id)
        ->call('start')->call('next')
        ->set('typed', 'Ottobre 2026')->set('password', 'segreta-admin')
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertRedirect(route('magazines.show', $issue->magazine));

    expect(Issue::find($issue->id))->toBeNull()
        ->and(Page::whereIn('id', $pageIds)->count())->toBe(0)
        ->and(Content::where('issue_id', $issue->id)->count())->toBe(0)
        ->and(ContentFile::count())->toBe(0);

    Queue::assertPushed(DeleteStoredFiles::class, fn (DeleteStoredFiles $job) => count($job->paths) === 2 && $job->afterCommit === true);
    Event::assertDispatched(IssueDeleted::class, fn (IssueDeleted $event) => $event->issueId === $issue->id);

    $log = ActivityLog::where('action', 'issue.deleted')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->issue_id)->toBeNull()
        ->and($log->description)->toContain('Ottobre 2026');
});

test('the files job deletes files and thumbnails from disk', function () {
    Storage::disk('local')->put('contents/9/a.pdf', 'pdf');
    Storage::disk('local')->put('thumbnails/9.png', 'png');

    (new DeleteStoredFiles([['disk' => 'local', 'path' => 'contents/9/a.pdf'], ['disk' => 'local', 'path' => 'thumbnails/9.png'], ['disk' => 'local', 'path' => 'gia-assente.pdf']], 'test'))->handle();

    Storage::disk('local')->assertMissing('contents/9/a.pdf');
    Storage::disk('local')->assertMissing('thumbnails/9.png');
});

test('magazine: the summary lists its issues; confirmed, magazine and issues go, users stay', function () {
    $magazine = Magazine::factory()->create(['name' => 'Motori Elettrici']);
    issueWithMaterial($magazine, 'Settembre');
    issueWithMaterial($magazine, 'Ottobre');
    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $redattore->magazines()->attach($magazine);
    $admin = deletionAdmin();

    deletionComponent($admin, 'magazine', $magazine->id)
        ->call('start')
        ->assertSee('2 numeri')
        ->assertSee('Numeri che verranno eliminati')
        ->assertSee('Settembre')->assertSee('Ottobre')
        ->call('next')
        ->set('typed', 'Motori Elettrici')->set('password', 'segreta-admin')
        ->call('confirm')
        ->assertRedirect(route('magazines.index'));

    expect(Magazine::find($magazine->id))->toBeNull()
        ->and(Issue::where('magazine_id', $magazine->id)->count())->toBe(0)
        ->and($redattore->fresh())->not->toBeNull()
        ->and(ActivityLog::where('action', 'magazine.deleted')->count())->toBe(1)
        ->and(ActivityLog::where('action', 'issue.deleted_with_magazine')->count())->toBe(2);

    Event::assertDispatchedTimes(IssueDeleted::class, 2);
});

test('user: an admin deletes a user, their files and history stay with "utente eliminato", sessions are closed', function () {
    config(['session.driver' => 'database']);
    $issue = issueWithMaterial();
    $admin = deletionAdmin();
    $redattore = User::factory()->create(['role' => UserRole::Redattore, 'email' => 'luca@timone.test']);
    $file = ContentFile::first();
    $file->update(['uploaded_by' => $redattore->id]);
    $log = PageReorderLog::create(['issue_id' => $issue->id, 'page_id' => $issue->pages()->first()->id, 'user_id' => $redattore->id, 'from_position' => 1, 'to_position' => 2]);
    DB::table('sessions')->insert(['id' => 'sessione-luca', 'user_id' => $redattore->id, 'payload' => '', 'last_activity' => time()]);

    deletionComponent($admin, 'user', $redattore->id)
        ->call('start')
        ->assertSee('1 file caricato: resta')
        ->call('next')
        ->set('typed', 'luca@timone.test')->set('password', 'segreta-admin')
        ->call('confirm')
        ->assertRedirect(route('users.index'));

    expect(User::find($redattore->id))->toBeNull()
        ->and($file->fresh())->not->toBeNull()
        ->and($file->fresh()->uploaded_by)->toBeNull()
        ->and($log->fresh()->user_id)->toBeNull()
        ->and(DB::table('sessions')->where('id', 'sessione-luca')->exists())->toBeFalse()
        ->and(ActivityLog::where('action', 'user.deleted')->sole()->user_id)->toBe($admin->id);
});

test('user: an admin cannot delete themselves; non-admins cannot delete anyone', function () {
    $admin = deletionAdmin();
    $other = User::factory()->create(['role' => UserRole::Redattore]);

    deletionComponent($admin, 'user', $admin->id)->call('start')->assertForbidden();
    deletionComponent($other, 'user', $admin->id)->call('start')->assertForbidden();

    // Il bottone per sé stessi è disattivato con il motivo.
    deletionComponent($admin, 'user', $admin->id)->assertSeeHtml('Non puoi eliminare il tuo stesso account.');
});

test('user: the last admin can never be deleted', function () {
    $onlyAdmin = deletionAdmin();
    $second = User::factory()->create(['role' => UserRole::Admin]);

    // Con due admin uno può eliminare l'altro.
    expect(Gate::forUser($onlyAdmin)->allows('delete', $second))->toBeTrue();

    // Con un solo admin nel database: in pratica ci si arriva solo con una
    // richiesta di un admin declassato nel frattempo (qui un admin non
    // salvato), ma la regola regge comunque.
    $second->delete();
    $staleAdmin = User::factory()->make(['role' => UserRole::Admin]);

    expect(Gate::forUser($staleAdmin)->inspect('delete', $onlyAdmin)->message())
        ->toBe("È l'ultimo amministratore: nomina prima un altro admin.");
});

test('the delete buttons are shown to admins only', function () {
    $issue = issueWithMaterial();
    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $redattore->magazines()->attach($issue->magazine);

    $this->actingAs(deletionAdmin())->get(route('magazines.show', $issue->magazine))->assertSee('confirm-deletion-issue-'.$issue->id, false);
    $this->actingAs($redattore)->get(route('magazines.show', $issue->magazine))->assertDontSee('confirm-deletion-issue-', false);
});

test('a viewer of a deleted issue gets a 404 from the timone (the browser then goes back to the list)', function () {
    $issue = issueWithMaterial();
    $admin = deletionAdmin();

    $this->actingAs($admin)->get(route('issues.show', [$issue->magazine, $issue]))->assertOk()->assertSee('data-deleted-redirect', false);

    $issue->delete();

    $this->actingAs($admin)->get(route('issues.show', [$issue->magazine, $issue]))->assertNotFound();
    $this->actingAs($admin)->get(route('magazines.index', ['eliminato' => 1]))->assertSee('Il numero che avevi aperto è stato eliminato da un amministratore.');
});
