<?php

namespace App\Livewire\Timone;

use App\Enums\ContentType;
use App\Enums\PageStatus;
use App\Enums\ThumbnailStatus;
use App\Enums\PageContentType;
use App\Events\ContentAssigned;
use App\Events\ContentUnassigned;
use App\Events\IssuePageCountUpdated;
use App\Events\PageMoved;
use App\Events\PageStatusUpdated;
use App\Jobs\GeneratePageFileThumbnail;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\PageReorderLog;
use App\Support\AdLoadCalculator;
use App\Support\PageCountResizer;
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

    /**
     * Versione del layout pagine ("reorder_version" dell'Issue) che il
     * client aveva l'ultima volta che ha visto la griglia — sincronizzata
     * di nuovo ad ogni render(). Usata per il lock ottimistico su
     * movePage(): se nel frattempo un altro utente ha riordinato le
     * pagine, questa proprietà resta quella "vecchia" mentre il DB è già
     * avanti, permettendo di rilevare il conflitto prima di applicare
     * uno spostamento calcolato su posizioni non più valide.
     */
    public int $reorderVersion = 0;

    /**
     * Il pannello storico spostamenti (page_reorder_logs) è chiuso di
     * default: la query viene fatta in render() solo quando è aperto,
     * per non pesare su ogni singolo render del componente (compreso
     * ogni poll di fallback) con dati che nella maggior parte dei casi
     * nessuno sta guardando.
     */
    public bool $showReorderLog = false;

    /**
     * Pannello "modifica pagine totali" — chiuso di default, stesso
     * motivo dello storico spostamenti.
     */
    public bool $showPageCountEditor = false;

    public int $newTotalPages = 0;

    /**
     * 'end' = aggiunge le nuove pagine in coda; 'position' = le inserisce
     * prima di $insertAtPosition, facendo slittare le pagine successive.
     */
    public string $insertMode = 'end';

    public ?int $insertAtPosition = null;

    protected const VIEW_MODES = ['griglia', 'doppia', 'lista'];

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
        $this->reorderVersion = $issue->reorder_version ?? 0;
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, self::VIEW_MODES, true)) {
            $this->viewMode = $mode;
        }
    }

    public function toggleReorderLog(): void
    {
        $this->showReorderLog = ! $this->showReorderLog;
    }

    public function togglePageCountEditor(): void
    {
        $this->showPageCountEditor = ! $this->showPageCountEditor;

        if ($this->showPageCountEditor) {
            $this->newTotalPages = $this->issue->total_pages;
            $this->insertMode = 'end';
            $this->insertAtPosition = null;
        }
    }

    /**
     * Applica il nuovo numero totale di pagine. Un aumento è sempre
     * "sicuro" (non distrugge nulla) e viene applicato subito. Una
     * riduzione elimina le pagine in eccesso **in coda** (posizione >
     * newTotalPages, mai una scelta arbitraria dell'utente su quale
     * pagina buttare) e richiede $confirmed=true — il pannello mostra
     * prima l'impatto (App\Support\PageCountResizer::impact(), calcolato
     * in render() sulle pagine già caricate) e solo un secondo clic
     * esplicito arriva qui con $confirmed=true. I contenuti delle pagine
     * rimosse non vengono cancellati: restano come Content orfani di
     * pagina, quindi tornano automaticamente nel pannello "non
     * assegnati" (Issue::unassignedContents(), già esistente) grazie al
     * cascadeOnDelete su page_content — nessuna cancellazione silenziosa
     * di contenuti, solo della riga di pagina e della sua assegnazione.
     */
    public function resizePages(bool $confirmed = false): void
    {
        $this->authorize('update', $this->issue);

        $currentTotal = $this->issue->total_pages;
        $newTotal = $this->newTotalPages;

        if ($newTotal === $currentTotal || $newTotal < 0 || $newTotal > 2000) {
            return;
        }

        if ($newTotal < $currentTotal && ! $confirmed) {
            return;
        }

        DB::transaction(function () use ($currentTotal, $newTotal) {
            if ($newTotal > $currentTotal) {
                $this->insertPages($currentTotal, $newTotal);
            } else {
                $this->issue->pages()->where('position', '>', $newTotal)->delete();
            }

            $this->issue->update(['total_pages' => $newTotal]);
        });

        broadcast(new IssuePageCountUpdated(
            issueId: $this->issue->id,
            oldTotalPages: $currentTotal,
            newTotalPages: $newTotal,
            changedByUserId: auth()->id(),
            changedByUserName: auth()->user()->name,
        ))->toOthers();

        $this->showPageCountEditor = false;
    }

    private function insertPages(int $currentTotal, int $newTotal): void
    {
        $countToAdd = $newTotal - $currentTotal;

        $insertBefore = $this->insertMode === 'position' && $this->insertAtPosition !== null
            ? max(1, min($this->insertAtPosition, $currentTotal + 1))
            : $currentTotal + 1;

        if ($insertBefore <= $currentTotal) {
            // Stessa tecnica a offset temporaneo di Grid::movePage(), per
            // non violare unique(issue_id, position) mentre si fa spazio
            // alle nuove pagine.
            $toShift = $this->issue->pages()->where('position', '>=', $insertBefore)->pluck('id', 'position');
            $offset = $currentTotal + $countToAdd;

            foreach ($toShift as $position => $id) {
                Page::whereKey($id)->update(['position' => $position + $offset]);
            }
            foreach ($toShift as $position => $id) {
                Page::whereKey($id)->update(['position' => $position + $countToAdd]);
            }
        }

        $now = now();
        $newRows = [];

        for ($i = 0; $i < $countToAdd; $i++) {
            $newRows[] = [
                'issue_id' => $this->issue->id,
                'position' => $insertBefore + $i,
                'content_type' => PageContentType::Bianca->value,
                'status' => PageStatus::DaAssegnare->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Page::insert($newRows);
    }

    #[On('echo-presence:issue.{issue.id},IssuePageCountUpdated')]
    public function onIssuePageCountUpdated(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // pagine e total_pages aggiornati dal database.
    }

    /**
     * Soglia di allarme del carico pubblicitario: campo già esistente su
     * Magazine (`ad_threshold_percentage`, previsto fin dallo schema
     * iniziale ma mai collegato a una UI finora) — impostazione della
     * rivista, non della singola issue, ma modificabile da qui perché è
     * il cruscotto pubblicitario a usarla. Stringa vuota/null azzera la
     * soglia (nessun allarme configurato).
     */
    public function updateAdThreshold(?string $value): void
    {
        $this->authorize('update', $this->issue);

        if ($value === null || trim($value) === '') {
            $this->issue->magazine->update(['ad_threshold_percentage' => null]);

            return;
        }

        $threshold = round((float) $value, 2);

        if ($threshold < 0 || $threshold > 100) {
            return;
        }

        $this->issue->magazine->update(['ad_threshold_percentage' => $threshold]);
    }

    public function movePage(int $pageId, int $toPosition): void
    {
        $this->authorize('update', $this->issue);

        $conflict = false;
        $applied = null;

        DB::transaction(function () use ($pageId, $toPosition, &$conflict, &$applied) {
            // lockForUpdate() sulla riga issue serializza anche il caso in
            // cui due movePage() concorrenti su questa stessa issue
            // arrivino a ridosso l'uno dell'altro: il secondo aspetta che
            // il primo rilasci il lock (commit) prima di rileggere
            // posizioni/versione, quindi non può mai calcolare gli
            // spostamenti su dati già superati.
            $issue = Issue::whereKey($this->issue->id)->lockForUpdate()->first();

            if ($issue->reorder_version !== $this->reorderVersion) {
                $conflict = true;
                // Riallinea subito il client alla versione vera: la
                // pagina non deve restare bloccata in un ciclo di
                // conflitti dovuti a una versione ormai vecchia.
                $this->reorderVersion = $issue->reorder_version;

                return;
            }

            $positions = $issue->pages()->pluck('position', 'id')->all();
            $changes = PageReorderer::move($positions, $pageId, $toPosition);

            if ($changes === []) {
                return;
            }

            $fromPosition = $positions[$pageId];
            $finalToPosition = $changes[$pageId];
            $offset = count($positions);

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

            $issue->increment('reorder_version');
            $this->reorderVersion = $issue->reorder_version;

            $applied = ['fromPosition' => $fromPosition, 'toPosition' => $finalToPosition];
        });

        if ($conflict) {
            $this->addError('reorder', 'Il timone è stato aggiornato da un altro utente nel frattempo: la modifica non è stata applicata, la vista è ora aggiornata.');

            return;
        }

        if ($applied === null) {
            return;
        }

        broadcast(new PageMoved(
            issueId: $this->issue->id,
            pageId: $pageId,
            fromPosition: $applied['fromPosition'],
            toPosition: $applied['toPosition'],
            movedByUserId: auth()->id(),
            movedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    #[On('echo-presence:issue.{issue.id},PageMoved')]
    public function onPageMoved(): void
    {
        // Il prossimo render() ripesca già le pagine ordinate dal database:
        // qui serve solo riallineare la versione del lock ottimistico,
        // altrimenti un successivo movePage() di questo client verrebbe
        // rifiutato come conflitto anche se la sua vista è ormai aggiornata.
        $this->reorderVersion = $this->issue->fresh()->reorder_version;
    }

    /**
     * Fallback quando i websocket non sono disponibili: il client lo
     * richiama a intervalli regolari al posto di ricevere i broadcast
     * (vedi `resources/js/app.js`, store `realtimeFallback`). render()
     * ripesca già tutto (pagine, contenuti, stati, file) ad ogni
     * richiesta Livewire: qui serve solo riallineare la versione del
     * lock ottimistico, esattamente come farebbe onPageMoved() se il
     * broadcast fosse arrivato normalmente.
     */
    public function pollRefresh(): void
    {
        $this->reorderVersion = $this->issue->fresh()->reorder_version;
    }

    public function changePageStatus(int $pageId, string $status): void
    {
        $this->authorize('update', $this->issue);

        $page = Page::findOrFail($pageId);

        if ($page->issue_id !== $this->issue->id) {
            return;
        }

        $newStatus = PageStatus::tryFrom($status);

        if ($newStatus === null || $newStatus === $page->status) {
            return;
        }

        $page->update(['status' => $newStatus]);

        broadcast(new PageStatusUpdated(
            issueId: $this->issue->id,
            pageId: $page->id,
            status: $newStatus->value,
            updatedByUserId: auth()->id(),
            updatedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    #[On('echo-presence:issue.{issue.id},PageStatusUpdated')]
    public function onPageStatusUpdated(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // lo stato aggiornato delle pagine dal database.
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

        $reorderLogs = $this->showReorderLog
            ? $this->issue->reorderLogs()->with(['page', 'user'])->latest()->limit(50)->get()
            : null;

        $pageCountImpact = $this->showPageCountEditor
            ? PageCountResizer::impact($pages, $this->issue->total_pages, $this->newTotalPages)
            : null;

        return view('livewire.timone.grid', [
            'pages' => $pages,
            'spreads' => $this->viewMode === 'doppia' ? PageSpreadBuilder::build($pages) : null,
            'unassignedContents' => $unassignedContents,
            'reorderLogs' => $reorderLogs,
            'adLoad' => AdLoadCalculator::summarize($pages),
            'adThreshold' => $this->issue->magazine->ad_threshold_percentage,
            'unassignedAdCount' => $unassignedContents->where('type', ContentType::Pubblicita)->count(),
            'pageCountImpact' => $pageCountImpact,
        ]);
    }
}
