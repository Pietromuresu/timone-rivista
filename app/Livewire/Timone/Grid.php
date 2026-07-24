<?php

namespace App\Livewire\Timone;

use App\Enums\ContentType;
use App\Enums\ThumbnailStatus;
use App\Events\ContentAssigned;
use App\Events\ContentUnassigned;
use App\Events\PageMoved;
use App\Jobs\GeneratePageFileThumbnail;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\PageReorderLog;
use App\Support\PagePercentageAllocator;
use App\Support\PageReorderer;
use App\Support\PageSpreadBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Grid extends Component
{
    use WithFileUploads;

    public Issue $issue;

    /**
     * griglia (flat plan a card), doppia (pagine affiancate come aperte
     * nella rivista stampata) o lista (una riga per pagina).
     */
    public string $viewMode = 'griglia';

    /**
     * File PDF in fase di upload, indicizzati per page_id — un solo
     * componente Grid gestisce l'intero timone, non uno per card.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $pendingUploads = [];

    protected const VIEW_MODES = ['griglia', 'doppia', 'lista'];

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, self::VIEW_MODES, true)) {
            $this->viewMode = $mode;
        }
    }

    public function movePage(int $pageId, int $toPosition): void
    {
        $this->authorize('update', $this->issue);

        $positions = $this->issue->pages()->pluck('position', 'id')->all();
        $changes = PageReorderer::move($positions, $pageId, $toPosition);

        if ($changes === []) {
            return;
        }

        $fromPosition = $positions[$pageId];
        $finalToPosition = $changes[$pageId];
        $offset = count($positions);

        DB::transaction(function () use ($changes, $offset, $pageId, $fromPosition, $finalToPosition) {
            foreach ($changes as $id => $newPosition) {
                Page::whereKey($id)->update(['position' => $newPosition + $offset]);
            }
            foreach ($changes as $id => $newPosition) {
                Page::whereKey($id)->update(['position' => $newPosition]);
            }

            PageReorderLog::create([
                'issue_id' => $this->issue->id,
                'page_id' => $pageId,
                'user_id' => auth()->id(),
                'from_position' => $fromPosition,
                'to_position' => $finalToPosition,
            ]);
        });

        broadcast(new PageMoved(
            issueId: $this->issue->id,
            pageId: $pageId,
            fromPosition: $fromPosition,
            toPosition: $finalToPosition,
            movedByUserId: auth()->id(),
            movedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    #[On('echo-presence:issue.{issue.id},PageMoved')]
    public function onPageMoved(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // le pagine già ordinate dal database.
    }

    public function assignContent(int $contentId, int $pageId): void
    {
        $this->authorize('update', $this->issue);

        $content = Content::with('advertisement')->findOrFail($contentId);
        $page = Page::findOrFail($pageId);

        if ($content->issue_id !== $this->issue->id || $page->issue_id !== $this->issue->id) {
            return;
        }

        $occupied = $page->contents()->pluck('occupied_percentage')->all();

        $percentage = $content->type === ContentType::Pubblicita
            ? $content->advertisement->occupiedPercentage()
            : PagePercentageAllocator::freeSpace($occupied);

        if (! PagePercentageAllocator::fits($occupied, $percentage)) {
            $this->addError('percentage', 'Spazio insufficiente su questa pagina per assegnare il contenuto.');

            return;
        }

        $page->contents()->attach($contentId, ['occupied_percentage' => (string) $percentage]);

        broadcast(new ContentAssigned(
            issueId: $this->issue->id,
            pageId: $page->id,
            contentId: $contentId,
            percentage: $percentage,
            assignedByUserId: auth()->id(),
            assignedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    public function updateContentPercentage(int $pageId, int $contentId, float $percentage): void
    {
        $this->authorize('update', $this->issue);

        $page = Page::findOrFail($pageId);

        if ($page->issue_id !== $this->issue->id) {
            return;
        }

        $others = $page->contents()->where('content_id', '!=', $contentId)->pluck('occupied_percentage')->all();

        if (! PagePercentageAllocator::fits($others, $percentage)) {
            $this->addError('percentage', 'La percentuale indicata supera lo spazio disponibile sulla pagina.');

            return;
        }

        $page->contents()->updateExistingPivot($contentId, ['occupied_percentage' => (string) $percentage]);

        broadcast(new ContentAssigned(
            issueId: $this->issue->id,
            pageId: $page->id,
            contentId: $contentId,
            percentage: $percentage,
            assignedByUserId: auth()->id(),
            assignedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    public function unassignContent(int $contentId, int $pageId): void
    {
        $this->authorize('update', $this->issue);

        $page = Page::findOrFail($pageId);

        if ($page->issue_id !== $this->issue->id) {
            return;
        }

        $page->contents()->detach($contentId);

        broadcast(new ContentUnassigned(
            issueId: $this->issue->id,
            pageId: $page->id,
            contentId: $contentId,
            unassignedByUserId: auth()->id(),
            unassignedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    #[On('echo-presence:issue.{issue.id},ContentAssigned')]
    public function onContentAssigned(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // pagine e contenuti non assegnati dal database.
    }

    #[On('echo-presence:issue.{issue.id},ContentUnassigned')]
    public function onContentUnassigned(): void
    {
        // idem
    }

    /**
     * Hook automatico Livewire per la proprietà annidata "pendingUploads.{pageId}":
     * chiamato dopo che Livewire ha già salvato il file nello storage temporaneo.
     */
    public function updatedPendingUploads($value, $key): void
    {
        if ($value instanceof TemporaryUploadedFile) {
            $this->uploadPageFile((int) $key, $value);
        }
    }

    public function uploadPageFile(int $pageId, TemporaryUploadedFile $file): void
    {
        $this->authorize('update', $this->issue);

        $page = Page::findOrFail($pageId);

        if ($page->issue_id !== $this->issue->id) {
            return;
        }

        validator(['pendingFile' => $file], [
            'pendingFile' => 'required|file|mimes:pdf|max:32768',
        ])->validate();

        $storedPath = $file->storeAs("pages/{$pageId}", Str::uuid().'.pdf', 'local');

        $pageFile = PageFile::create([
            'page_id' => $pageId,
            'uploaded_by' => auth()->id(),
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'thumbnail_status' => ThumbnailStatus::Pending,
        ]);

        GeneratePageFileThumbnail::dispatch($pageFile);

        unset($this->pendingUploads[$pageId]);
    }

    #[On('echo-presence:issue.{issue.id},PageFileUploaded')]
    public function onPageFileUploaded(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // l'ultimo PageFile (con thumbnail_status aggiornato) dal database.
    }

    public function render(): View
    {
        $pages = $this->issue->pages()
            ->orderBy('position')
            ->with([
                'contents.article',
                'contents.advertisement',
                'files' => fn ($query) => $query->latest()->limit(1),
            ])
            ->get();

        $unassignedContents = $this->issue->unassignedContents()
            ->with(['article', 'advertisement'])
            ->orderBy('created_at')
            ->get();

        return view('livewire.timone.grid', [
            'pages' => $pages,
            'spreads' => $this->viewMode === 'doppia' ? PageSpreadBuilder::build($pages) : null,
            'unassignedContents' => $unassignedContents,
        ]);
    }
}
