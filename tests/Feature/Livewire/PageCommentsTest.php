<?php

use App\Enums\CoverFlap;
use App\Enums\CoverSlot;
use App\Enums\UserRole;
use App\Events\IssuePageCountUpdated;
use App\Events\PageMoved;
use App\Livewire\Timone\Grid;
use App\Livewire\Timone\PageComments;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\PageComment;
use App\Models\User;
use App\Notifications\PageCommentPosted;
use App\Support\Comments\CommentRecipients;
use App\Support\Comments\PageCommentReader;
use App\Support\IssueCovers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

// Commenti per pagina con notifica (2026-10-29): App\Support\Comments,
// App\Livewire\Timone\PageComments, PageCommentPolicy.

beforeEach(function () {
    Notification::fake();
    Event::fake([PageMoved::class, IssuePageCountUpdated::class]);
});

function commentIssue(int $pages = 6): Issue
{
    return Issue::factory()->create(['magazine_id' => Magazine::factory()->create()->id, 'total_pages' => $pages]);
}

function commentUser(Issue $issue, UserRole $role = UserRole::Redattore, ?string $name = null): User
{
    $user = User::factory()->create(['role' => $role] + ($name !== null ? ['name' => $name] : []));
    $user->magazines()->attach($issue->magazine);

    return $user;
}

function commentsPanel(Issue $issue, User $user)
{
    return Livewire::actingAs($user)->test(PageComments::class, ['issue' => $issue]);
}

function postComment(Issue $issue, User $author, int $pageId, string $body, array $recipientIds = [], bool $everyone = false)
{
    return commentsPanel($issue, $author)
        ->call('openPage', $pageId)
        ->set('body', $body)
        ->set('recipientIds', $recipientIds)
        ->set('notifyEveryone', $everyone)
        ->call('post');
}

test('a comment is created on the page; it is unread for the others, never for the author, and opening the page marks it read', function () {
    $issue = commentIssue();
    $author = commentUser($issue);
    $reader = commentUser($issue);
    $page = $issue->pages()->where('position', 3)->first();

    postComment($issue, $author, $page->id, 'Manca la foto')->assertHasNoErrors()->assertDispatched('comments-changed');

    $comment = PageComment::sole();
    expect($comment->page_id)->toBe($page->id)
        ->and($comment->user_id)->toBe($author->id)
        ->and($comment->page_label)->toBe('pagina 3')
        ->and(PageCommentReader::counts($issue, $author))->toBe(['pages' => [$page->id => ['total' => 1, 'unread' => 0]], 'unread' => 0])
        ->and(PageCommentReader::counts($issue, $reader))->toBe(['pages' => [$page->id => ['total' => 1, 'unread' => 1]], 'unread' => 1])
        ->and(PageCommentReader::unreadFor($issue, $reader)->pluck('id')->all())->toBe([$comment->id]);

    commentsPanel($issue, $reader)->call('openPage', $page->id)->assertSet('open', true)->assertSee('Manca la foto');

    expect(PageCommentReader::counts($issue, $reader)['unread'])->toBe(0)
        ->and(PageCommentReader::unreadFor($issue, $reader))->toBeEmpty();
});

test('comments follow the page when it is moved', function () {
    $issue = commentIssue();
    $user = commentUser($issue);
    $page = $issue->pages()->where('position', 2)->first();

    postComment($issue, $user, $page->id, 'Va in apertura');
    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])->call('movePage', $page->id, 5);

    $comment = PageComment::sole();
    expect($page->fresh()->position)->toBe(5)
        ->and($comment->page_id)->toBe($page->id)
        ->and($comment->fresh()->pageName())->toBe('pagina 5');
});

test('reducing the total pages keeps the comments of the removed pages, readable as «pagina N, eliminata», without FK errors', function () {
    $issue = commentIssue(6);
    $author = commentUser($issue);
    $reader = commentUser($issue);
    $last = $issue->pages()->where('position', 6)->first();

    postComment($issue, $author, $last->id, 'Questa pagina salta?');
    $deletedByAuthor = PageComment::create(['issue_id' => $issue->id, 'page_id' => $last->id, 'user_id' => $author->id, 'body' => 'già eliminato', 'page_label' => 'pagina 6']);
    $deletedByAuthor->delete();

    Livewire::actingAs($author)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', '4')->call('resizePages', true)->assertHasNoErrors();

    expect($issue->fresh()->total_pages)->toBe(4)
        ->and(DB::table('pages')->where('id', $last->id)->exists())->toBeFalse();

    $comment = PageComment::where('body', 'Questa pagina salta?')->sole();
    expect($comment->page_id)->toBeNull()
        ->and($comment->page_label)->toBe('pagina 6')
        ->and($comment->page_deleted_at)->not->toBeNull()
        ->and($comment->pageName())->toBe('pagina 6, eliminata')
        ->and(PageComment::withTrashed()->find($deletedByAuthor->id)->page_id)->toBeNull()
        // Nessuna icona (la pagina non c'è più), ma resta nei non letti del numero.
        ->and(PageCommentReader::counts($issue, $reader))->toBe(['pages' => [], 'unread' => 1]);

    commentsPanel($issue, $reader)->call('openIssue')->set('tab', 'all')
        ->assertSee('Pagina 6, eliminata')->assertSee('Questa pagina salta?');
});

test('removing a battente keeps the comments of its pages', function () {
    $issue = commentIssue();
    $user = commentUser($issue);
    IssueCovers::addFlap($issue, CoverFlap::I);
    $first = $issue->covers()->where('cover_slot', CoverSlot::BattenteI1->value)->sole();

    postComment($issue, $user, $first->id, 'Testo del battente');
    expect(IssueCovers::removeFlap($issue, CoverFlap::I))->toBeNull();

    expect(PageComment::sole()->pageName())->toBe('Battente I · 1, eliminata');
});

test('mail goes only to the chosen recipients and the mentioned users, never to the author, never twice', function () {
    $issue = commentIssue();
    $author = commentUser($issue, name: 'Anna Bianchi');
    $chosen = commentUser($issue, name: 'Mario Rossi');
    $mentioned = commentUser($issue, UserRole::Commerciale, 'Luca Verdi');
    $notChosen = commentUser($issue, name: 'Marione Neri');
    $outsider = User::factory()->create(['role' => UserRole::Redattore, 'name' => 'Paolo Esterno']);

    postComment($issue, $author, $issue->pages()->first()->id, 'Ciao @Mario Rossi e @lucaverdi, vedi anche @Anna Bianchi e @Paolo Esterno', [$chosen->id, $author->id, $outsider->id])
        ->assertHasNoErrors()
        ->assertSee('mail a: Luca Verdi e Mario Rossi');

    Notification::assertSentTo($chosen, PageCommentPosted::class);
    Notification::assertSentTo($mentioned, PageCommentPosted::class);
    Notification::assertNotSentTo($author, PageCommentPosted::class);
    Notification::assertNotSentTo($notChosen, PageCommentPosted::class);
    Notification::assertNotSentTo($outsider, PageCommentPosted::class);
    Notification::assertSentToTimes($chosen, PageCommentPosted::class, 1);
    Notification::assertCount(2);
});

test('«all users of this magazine» means the users assigned to the magazine plus the admins, except the author', function () {
    $issue = commentIssue();
    $author = commentUser($issue);
    $colleague = commentUser($issue, UserRole::SolaLettura);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $otherMagazine = User::factory()->create(['role' => UserRole::Redattore]);
    $otherMagazine->magazines()->attach(Magazine::factory()->create());

    postComment($issue, $author, $issue->pages()->first()->id, 'Per tutti', [], true);

    Notification::assertSentTo([$colleague, $admin], PageCommentPosted::class);
    Notification::assertNotSentTo($author, PageCommentPosted::class);
    Notification::assertNotSentTo($otherMagazine, PageCommentPosted::class);
    expect(CommentRecipients::eligible($issue)->pluck('id')->sort()->values()->all())->toBe(collect([$author->id, $colleague->id, $admin->id])->sort()->values()->all());
});

test('a mention must be the whole name: @Mario does not notify Mario Rossi, @Mario Rossino does not either', function () {
    $issue = commentIssue();
    $mario = commentUser($issue, name: 'Mario Rossi');

    expect(CommentRecipients::mentioned('ciao @Mario', collect([$mario])))->toBeEmpty()
        ->and(CommentRecipients::mentioned('ciao @Mario Rossino', collect([$mario])))->toBeEmpty()
        ->and(CommentRecipients::mentioned('ciao @mario rossi!', collect([$mario]))->pluck('id')->all())->toBe([$mario->id]);
});

test('the mail has magazine, issue, page, author, text and a link that opens the page comments', function () {
    $issue = commentIssue();
    $author = commentUser($issue, name: 'Anna Bianchi');
    $page = $issue->pages()->where('position', 4)->first();
    $comment = PageComment::create(['issue_id' => $issue->id, 'page_id' => $page->id, 'user_id' => $author->id, 'body' => "Prima riga\nseconda <b>riga</b>", 'page_label' => 'pagina 4']);
    $recipient = commentUser($issue);

    $mail = (new PageCommentPosted($comment))->toMail($recipient);
    $url = route('issues.show', [$issue->magazine, $issue]).'?commenti='.$page->id;
    $text = implode("\n", $mail->introLines);

    expect($mail->subject)->toContain($issue->magazine->name)->toContain($issue->title)->toContain('pagina 4')
        ->and($text)->toContain($issue->magazine->name)->toContain($issue->title)->toContain('Pagina 4')->toContain('Anna Bianchi')
        ->toContain('Prima riga')->toContain('&lt;b&gt;riga&lt;/b&gt;')
        ->and($mail->actionUrl)->toBe($url);

    // Testi fissi del modello di Laravel in italiano (lang/it.json).
    expect((string) $mail->render())->toContain('Se il bottone')->toContain('Saluti')->not->toContain('having trouble');

    // Il link apre il pannello su quella pagina.
    Livewire::withQueryParams(['commenti' => (string) $page->id])->actingAs($recipient)
        ->test(PageComments::class, ['issue' => $issue])
        ->assertSet('open', true)->assertSet('pageId', $page->id);

    // Commento eliminato prima dell'invio: niente mail.
    $comment->delete();
    expect((new PageCommentPosted($comment))->shouldSend($recipient, 'mail'))->toBeFalse();
});

test('permissions: commerciale writes, sola lettura only reads, the author edits, the author or an admin deletes', function () {
    $issue = commentIssue();
    $page = $issue->pages()->first();
    $author = commentUser($issue, UserRole::Commerciale);
    $colleague = commentUser($issue);
    $readOnly = commentUser($issue, UserRole::SolaLettura);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    postComment($issue, $author, $page->id, 'Dal commerciale')->assertHasNoErrors();
    $comment = PageComment::sole();

    commentsPanel($issue, $readOnly)->call('openPage', $page->id)->assertSee('Dal commerciale')->assertSee('puoi leggere i commenti ma non scriverne');
    commentsPanel($issue, $readOnly)->call('openPage', $page->id)->set('body', 'no')->call('post')->assertForbidden();
    commentsPanel($issue, $outsider)->call('openPage', $page->id)->assertForbidden();

    commentsPanel($issue, $colleague)->call('startEdit', $comment->id)->assertForbidden();
    commentsPanel($issue, $admin)->call('startEdit', $comment->id)->assertForbidden();
    commentsPanel($issue, $colleague)->call('deleteComment', $comment->id)->assertForbidden();

    commentsPanel($issue, $author)->call('openPage', $page->id)->call('startEdit', $comment->id)
        ->set('editingBody', 'Testo corretto')->call('saveEdit')->assertHasNoErrors();
    expect($comment->fresh()->body)->toBe('Testo corretto')->and($comment->fresh()->edited_at)->not->toBeNull();

    commentsPanel($issue, $admin)->call('deleteComment', $comment->id)->assertHasNoErrors();
    expect(PageComment::find($comment->id))->toBeNull()
        ->and(PageComment::withTrashed()->find($comment->id))->not->toBeNull();

    postComment($issue, $author, $page->id, 'Secondo');
    commentsPanel($issue, $author)->call('deleteComment', PageComment::sole()->id)->assertHasNoErrors();
    expect(PageComment::count())->toBe(0);
});

test('polling: a comment changes comments_updated_at (not pages_updated_at) and the other user gets new counters without a grid render', function () {
    $issue = commentIssue();
    $author = commentUser($issue);
    $other = commentUser($issue);
    $page = $issue->pages()->first();
    $pagesBefore = DB::table('issues')->where('id', $issue->id)->value('pages_updated_at');

    $grid = Livewire::actingAs($other)->test(Grid::class, ['issue' => $issue->fresh()]);
    $grid->call('checkForUpdates')->assertNotDispatched('comment-counts');

    postComment($issue, $author, $page->id, 'Nuovo');
    expect(DB::table('issues')->where('id', $issue->id)->value('comments_updated_at'))->not->toBeNull()
        ->and(DB::table('issues')->where('id', $issue->id)->value('pages_updated_at'))->toBe($pagesBefore);

    // actingAs è globale: postComment() ha fatto entrare l'autore.
    $this->actingAs($other);
    $grid->call('checkForUpdates')
        ->assertDispatched('comment-counts', counts: ['pages' => [$page->id => ['total' => 1, 'unread' => 1]], 'unread' => 1])
        ->assertDispatched('comments-remote-changed')
        ->assertNotDispatched('remote-change');

    // Con Reverb collegato: il controllo dei soli commenti.
    $connected = Livewire::actingAs($other)->test(Grid::class, ['issue' => $issue->fresh()]);
    postComment($issue, $author, $page->id, 'Ancora');
    $this->actingAs($other);
    $connected->call('checkForCommentUpdates')->assertDispatched('comment-counts', counts: ['pages' => [$page->id => ['total' => 2, 'unread' => 2]], 'unread' => 2]);

    // Modifica ed eliminazione cambiano anche loro il segnale.
    foreach (['edit', 'delete'] as $action) {
        $before = DB::table('issues')->where('id', $issue->id)->value('comments_updated_at');
        $comment = PageComment::latest('id')->first();
        $panel = commentsPanel($issue, $author)->call('openPage', $page->id);
        $action === 'edit'
            ? $panel->call('startEdit', $comment->id)->set('editingBody', 'cambiato')->call('saveEdit')
            : $panel->call('deleteComment', $comment->id);
        expect(DB::table('issues')->where('id', $issue->id)->value('comments_updated_at'))->not->toBe($before);
    }
});

test('the grid seeds the counters on render and shows the comment button on every page', function () {
    $issue = commentIssue(2);
    $user = commentUser($issue);
    $other = commentUser($issue);
    $page = $issue->pages()->first();
    postComment($issue, $other, $page->id, 'Ciao');

    $grid = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    expect($grid->viewData('commentCounts'))->toBe(['pages' => [$page->id => ['total' => 1, 'unread' => 1]], 'unread' => 1])
        ->and(substr_count($grid->html(), 'data-testid="page-comments-button"'))->toBe(2 + $issue->covers()->count());
});
