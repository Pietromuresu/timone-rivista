<?php

namespace App\Livewire\Timone;

use App\Models\Issue;
use App\Models\Page;
use App\Models\PageComment;
use App\Support\Comments\CommentRecipients;
use App\Support\Comments\PageCommentReader;
use App\Support\Comments\PageCommentWriter;
use App\Support\Permissions\TimoneAbilities;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pannello dei commenti (2026-10-29): i commenti di UNA pagina (elenco,
 * nuovo commento con destinatari, modifica/eliminazione) oppure quelli del
 * numero (non letti / tutti, anche delle pagine eliminate). Strato sottile:
 * regole in App\Support\Comments, permessi in PageCommentPolicy.
 *
 * Si apre con gli eventi del browser open-page-comments (💬 sulla card) e
 * open-issue-comments (contatore nella testata), o dal link della mail
 * (?commenti=ID pagina). Dopo ogni scrittura o lettura manda
 * comments-changed: Grid ricalcola i contatori senza ridisegnare la griglia.
 */
class PageComments extends Component
{
    public const MAX_LENGTH = 5000;

    public Issue $issue;

    public bool $open = false;

    /** 'page' | 'issue' */
    public string $mode = 'page';

    public ?int $pageId = null;

    /** 'unread' | 'all' (solo in modalità issue) */
    public string $tab = 'unread';

    public string $body = '';

    /** @var list<int> */
    public array $recipientIds = [];

    public bool $notifyEveryone = false;

    public ?int $editingId = null;

    public string $editingBody = '';

    public ?string $notice = null;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;

        // Link della mail: …/numeri/{id}?commenti={pagina}
        $fromMail = request()->query('commenti');

        if (is_string($fromMail) && ctype_digit($fromMail) && $this->issue->allPages()->whereKey((int) $fromMail)->exists()) {
            $this->openPage((int) $fromMail);
        }
    }

    #[On('open-page-comments')]
    public function openPage(int $pageId): void
    {
        $this->authorize('view', $this->issue);
        $page = $this->findPage($pageId);

        $this->reset('body', 'recipientIds', 'notifyEveryone', 'editingId', 'editingBody', 'notice');
        $this->resetErrorBag();
        $this->mode = 'page';
        $this->pageId = $page->id;
        $this->open = true;

        PageCommentReader::markPageRead($page, auth()->user());
        $this->dispatch('comments-changed');
        $this->dispatch('scroll-to-page', pageId: $page->id);
    }

    #[On('open-issue-comments')]
    public function openIssue(): void
    {
        $this->authorize('view', $this->issue);

        $this->reset('editingId', 'editingBody', 'notice');
        $this->mode = 'issue';
        $this->pageId = null;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /**
     * Un collega ha scritto (Grid se ne accorge col polling e lo segnala):
     * se sto guardando quella pagina, i nuovi commenti li sto leggendo.
     */
    #[On('comments-remote-changed')]
    public function onRemoteChange(): void
    {
        if ($this->open && $this->mode === 'page' && $this->pageId !== null && ($page = $this->issue->allPages()->find($this->pageId)) !== null) {
            PageCommentReader::markPageRead($page, auth()->user());
            $this->dispatch('comments-changed');
        }
    }

    public function post(): void
    {
        $this->authorize('create', [PageComment::class, $this->issue]);
        $page = $this->findPage((int) $this->pageId);

        $this->validate(
            ['body' => ['required', 'string', 'max:'.self::MAX_LENGTH], 'recipientIds' => ['array'], 'recipientIds.*' => ['integer']],
            ['body.required' => 'Scrivi il testo del commento.', 'body.max' => 'Il commento può avere al massimo '.self::MAX_LENGTH.' caratteri.'],
        );

        ['recipients' => $recipients] = PageCommentWriter::post($page, auth()->user(), $this->body, $this->recipientIds, $this->notifyEveryone);

        $this->reset('body', 'recipientIds', 'notifyEveryone');
        $this->notice = $recipients->isEmpty()
            ? 'Commento salvato (nessuna mail inviata).'
            : 'Commento salvato, mail a: '.$recipients->sortBy('name')->pluck('name')->join(', ', ' e ').'.';
        $this->dispatch('comments-changed');
    }

    public function startEdit(int $commentId): void
    {
        $comment = $this->findComment($commentId);
        $this->authorize('update', $comment);

        $this->editingId = $comment->id;
        $this->editingBody = $comment->body;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editingBody');
    }

    public function saveEdit(): void
    {
        $comment = $this->findComment((int) $this->editingId);
        $this->authorize('update', $comment);

        $this->validate(
            ['editingBody' => ['required', 'string', 'max:'.self::MAX_LENGTH]],
            ['editingBody.required' => 'Il commento non può essere vuoto: eliminalo, se non serve più.', 'editingBody.max' => 'Il commento può avere al massimo '.self::MAX_LENGTH.' caratteri.'],
        );

        PageCommentWriter::update($comment, $this->editingBody);
        $this->reset('editingId', 'editingBody');
        $this->dispatch('comments-changed');
    }

    public function deleteComment(int $commentId): void
    {
        $comment = $this->findComment($commentId);
        $this->authorize('delete', $comment);

        PageCommentWriter::delete($comment);
        $this->dispatch('comments-changed');
    }

    /** Dal numero ai commenti di una pagina (elenco non letti / tutti). */
    public function goToPage(int $pageId): void
    {
        $this->openPage($pageId);
    }

    public function markAllRead(): void
    {
        $this->authorize('view', $this->issue);

        PageCommentReader::markRead(PageCommentReader::unreadFor($this->issue, auth()->user())->pluck('id')->all(), auth()->user());
        $this->dispatch('comments-changed');
    }

    public function render(): View
    {
        $user = auth()->user();
        $page = $this->open && $this->mode === 'page' && $this->pageId !== null ? $this->issue->allPages()->find($this->pageId) : null;

        return view('livewire.timone.page-comments', [
            'page' => $page,
            'comments' => $page !== null ? PageCommentReader::forPage($page) : collect(),
            'issueComments' => $this->open && $this->mode === 'issue'
                ? ($this->tab === 'all' ? PageCommentReader::allFor($this->issue) : PageCommentReader::unreadFor($this->issue, $user))
                : collect(),
            'canComment' => TimoneAbilities::for($user, $this->issue)->canComment(),
            'candidates' => $page !== null ? CommentRecipients::eligible($this->issue)->reject(fn ($candidate) => $candidate->id === $user->id)->values() : collect(),
        ]);
    }

    private function findPage(int $pageId): Page
    {
        return $this->issue->allPages()->findOrFail($pageId);
    }

    private function findComment(int $commentId): PageComment
    {
        return PageComment::where('issue_id', $this->issue->id)->findOrFail($commentId);
    }
}
