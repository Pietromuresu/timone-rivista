<?php

namespace App\Livewire\Timone;

use App\Enums\ContentType;
use App\Enums\PageStatus;
use App\Enums\ThumbnailStatus;
use App\Enums\PageContentType;
use App\Events\ContentAssigned;
use App\Events\ContentUnassigned;
use App\Events\IssuePageCountUpdated;
use App\Events\PageLocked;
use App\Events\PageMoved;
use App\Events\PagesSwapped;
use App\Events\PageStatusUpdated;
use App\Events\PageUnlocked;
use App\Jobs\GeneratePageFileThumbnail;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\PageReorderLog;
use App\Support\ActivityLogger;
use App\Support\AdLoadCalculator;
use App\Support\AutomaticChecks;
use App\Support\PageContentTypeResolver;
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

    /**
     * Filtro testuale sul pannello "contenuti da assegnare" (titolo) — con
     * pochi contenuti la lista si scorre a occhio, ma cresce insieme al
     * numero e aveva bisogno di un modo per restringerla.
     */
    public string $contentSearch = '';

    /**
     * Modalità scambio (spec §6.2/§6.7): alternativa al drag&drop, utile
     * soprattutto in modalità "doppia" (che non ha il riordino via
     * trascinamento — vedi nota su PageSpreadBuilder). Attiva/disattivata
     * dalla toolbar, funziona in tutte e tre le modalità di vista tramite
     * un click sulla pagina invece che un drag: click sulla prima pagina
     * la seleziona, click su una seconda le scambia di posto (contenuti,
     * stato e file restano legati alla pagina, non alla posizione — si
     * "spostano" insieme ad essa), un secondo click sulla stessa pagina
     * annulla la selezione.
     */
    public bool $swapMode = false;

    public ?int $swapSelectedPageId = null;

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

        if ($newTotal < $currentTotal) {
            // Una pagina bloccata non può essere "eliminata" (spec §6.6) —
            // controllato prima del gate su $confirmed, così il blocco
            // vince a prescindere da dove si trovi l'utente nel flusso di
            // conferma in due passaggi.
            $hasLockedPageToRemove = $this->issue->pages()
                ->where('position', '>', $newTotal)
                ->whereNotNull('locked_at')
                ->exists();

            if ($hasLockedPageToRemove) {
                $this->addError('locked', 'Alcune delle pagine da eliminare sono bloccate: sbloccale prima di ridurre il numero di pagine.');

                return;
            }
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

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Issue',
            entityId: $this->issue->id,
            action: 'issue.page_count_changed',
            description: "Numero totale di pagine cambiato da {$currentTotal} a {$newTotal}",
            old: ['total_pages' => $currentTotal],
            new: ['total_pages' => $newTotal],
        );

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

        $oldThreshold = $this->issue->magazine->ad_threshold_percentage;

        if ($value === null || trim($value) === '') {
            $this->issue->magazine->update(['ad_threshold_percentage' => null]);
            $this->logAdThresholdChange($oldThreshold, null);

            return;
        }

        $threshold = round((float) $value, 2);

        if ($threshold < 0 || $threshold > 100) {
            return;
        }

        $this->issue->magazine->update(['ad_threshold_percentage' => $threshold]);
        $this->logAdThresholdChange($oldThreshold, $threshold);
    }

    private function logAdThresholdChange(?float $old, ?float $new): void
    {
        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Magazine',
            entityId: $this->issue->magazine_id,
            action: 'magazine.ad_threshold_changed',
            description: 'Soglia di allarme pubblicitario cambiata da '.($old ?? 'nessuna').' a '.($new ?? 'nessuna'),
            old: ['ad_threshold_percentage' => $old],
            new: ['ad_threshold_percentage' => $new],
        );
    }

    public function movePage(int $pageId, int $toPosition): void
    {
        $this->authorize('update', $this->issue);

        if ($this->blockIfLocked(Page::find($pageId), 'spostata')) {
            return;
        }

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

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Page',
            entityId: $pageId,
            action: 'page.moved',
            description: "Pagina spostata dalla posizione {$applied['fromPosition']} alla {$applied['toPosition']}",
            old: ['position' => $applied['fromPosition']],
            new: ['position' => $applied['toPosition']],
        );

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

    public function toggleSwapMode(): void
    {
        $this->swapMode = ! $this->swapMode;
        $this->swapSelectedPageId = null;
    }

    /**
     * Click su una pagina in modalità scambio: primo click la seleziona,
     * click sulla stessa pagina la deseleziona, click su una pagina
     * diversa esegue lo scambio e azzera la selezione. Nessun-op se la
     * modalità scambio non è attiva (difesa anche lato server, non solo
     * nascondendo il click handler lato UI).
     */
    public function selectForSwap(int $pageId): void
    {
        $this->authorize('update', $this->issue);

        if (! $this->swapMode) {
            return;
        }

        if ($this->blockIfLocked(Page::find($pageId), 'scambiata')) {
            return;
        }

        if ($this->swapSelectedPageId === null) {
            $this->swapSelectedPageId = $pageId;

            return;
        }

        if ($this->swapSelectedPageId === $pageId) {
            $this->swapSelectedPageId = null;

            return;
        }

        $firstPageId = $this->swapSelectedPageId;
        $this->swapSelectedPageId = null;

        $this->swapPages($firstPageId, $pageId);
    }

    /**
     * Scambia direttamente le posizioni di due pagine — a differenza di
     * movePage() (che fa slittare tutte le pagine intermedie), qui SOLO
     * le due pagine indicate cambiano posizione. Stesso lock ottimistico
     * su `reorder_version` e stessa tecnica a offset temporaneo di
     * movePage(), per non violare unique(issue_id, position).
     */
    private function swapPages(int $pageIdA, int $pageIdB): void
    {
        $conflict = false;
        $applied = null;

        DB::transaction(function () use ($pageIdA, $pageIdB, &$conflict, &$applied) {
            $issue = Issue::whereKey($this->issue->id)->lockForUpdate()->first();

            if ($issue->reorder_version !== $this->reorderVersion) {
                $conflict = true;
                $this->reorderVersion = $issue->reorder_version;

                return;
            }

            $positions = $issue->pages()->pluck('position', 'id')->all();

            if (! array_key_exists($pageIdA, $positions) || ! array_key_exists($pageIdB, $positions)) {
                // Una delle due pagine non appartiene (più) a questa issue —
                // guardia cross-issue, stesso principio già usato altrove.
                return;
            }

            $changes = PageReorderer::swap($positions, $pageIdA, $pageIdB);

            if ($changes === []) {
                return;
            }

            $offset = count($positions);

            foreach ($changes as $id => $newPosition) {
                Page::whereKey($id)->update(['position' => $newPosition + $offset]);
            }
            foreach ($changes as $id => $newPosition) {
                Page::whereKey($id)->update(['position' => $newPosition]);
            }

            $issue->increment('reorder_version');
            $this->reorderVersion = $issue->reorder_version;

            $applied = [
                'positionA' => $positions[$pageIdA],
                'positionB' => $positions[$pageIdB],
            ];
        });

        if ($conflict) {
            $this->addError('reorder', 'Il timone è stato aggiornato da un altro utente nel frattempo: la modifica non è stata applicata, la vista è ora aggiornata.');

            return;
        }

        if ($applied === null) {
            return;
        }

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Page',
            entityId: $pageIdA,
            action: 'page.swapped',
            description: "Pagine scambiate: posizione {$applied['positionA']} <-> posizione {$applied['positionB']}",
            old: ['pageA' => $pageIdA, 'positionA' => $applied['positionA'], 'pageB' => $pageIdB, 'positionB' => $applied['positionB']],
            new: ['pageA' => $pageIdA, 'positionA' => $applied['positionB'], 'pageB' => $pageIdB, 'positionB' => $applied['positionA']],
        );

        broadcast(new PagesSwapped(
            issueId: $this->issue->id,
            pageIdA: $pageIdA,
            pageIdB: $pageIdB,
            swappedByUserId: auth()->id(),
            swappedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    #[On('echo-presence:issue.{issue.id},PagesSwapped')]
    public function onPagesSwapped(): void
    {
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

        if ($this->blockIfLocked($page)) {
            return;
        }

        $newStatus = PageStatus::tryFrom($status);

        if ($newStatus === null || $newStatus === $page->status) {
            return;
        }

        $oldStatus = $page->status;
        $page->update(['status' => $newStatus]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Page',
            entityId: $page->id,
            action: 'page.status_changed',
            description: "Stato della pagina {$page->position} cambiato da «{$oldStatus->label()}» a «{$newStatus->label()}»",
            old: ['status' => $oldStatus->value],
            new: ['status' => $newStatus->value],
        );

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

    /**
     * §6.6 dello spec: blocco pagina — impedisce spostamento, eliminazione,
     * sovrascrittura o modifica di una pagina bloccata. Il nostro schema
     * non ha permessi granulari `page.lock`/`page.edit` per pubblicazione
     * (solo ruolo globale + accesso binario per rivista, stessa
     * semplificazione già presa per la gestione utenti/ruoli) — bloccare/
     * sbloccare richiede quindi la stessa ability `update` di ogni altra
     * mutazione su questa issue, non un permesso a sé.
     */
    public function togglePageLock(int $pageId): void
    {
        $this->authorize('update', $this->issue);

        $page = Page::findOrFail($pageId);

        if ($page->issue_id !== $this->issue->id) {
            return;
        }

        if ($page->isLocked()) {
            $page->update(['locked_at' => null, 'locked_by' => null]);

            ActivityLogger::log(
                issue: $this->issue,
                entityType: 'Page',
                entityId: $page->id,
                action: 'page.unlocked',
                description: "Pagina {$page->position} sbloccata",
            );

            broadcast(new PageUnlocked(
                issueId: $this->issue->id,
                pageId: $page->id,
                unlockedByUserId: auth()->id(),
                unlockedByUserName: auth()->user()->name,
            ))->toOthers();

            return;
        }

        $page->update(['locked_at' => now(), 'locked_by' => auth()->id()]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Page',
            entityId: $page->id,
            action: 'page.locked',
            description: "Pagina {$page->position} bloccata",
        );

        broadcast(new PageLocked(
            issueId: $this->issue->id,
            pageId: $page->id,
            lockedByUserId: auth()->id(),
            lockedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    #[On('echo-presence:issue.{issue.id},PageLocked')]
    #[On('echo-presence:issue.{issue.id},PageUnlocked')]
    public function onPageLockChanged(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // locked_at/locked_by dal database.
    }

    /**
     * Guardia condivisa da ogni mutazione che tocca direttamente una
     * pagina (spostamento, scambio, cambio stato, assegnazione contenuti,
     * upload file, eliminazione per riduzione pagine totali) — una pagina
     * bloccata non può essere "spostata, eliminata, sovrascritta, o
     * modificata" (spec §6.6). $verb è usato solo per personalizzare il
     * messaggio d'errore (es. "spostata", "modificata").
     */
    private function blockIfLocked(?Page $page, string $verb = 'modificata'): bool
    {
        if ($page !== null && $page->isLocked()) {
            $this->addError('locked', "La pagina {$page->position} è bloccata e non può essere {$verb}: sbloccala prima di procedere.");

            return true;
        }

        return false;
    }

    public function assignContent(int $contentId, int $pageId): void
    {
        $this->authorize('update', $this->issue);

        $content = Content::with('advertisement')->findOrFail($contentId);
        $page = Page::findOrFail($pageId);

        if ($content->issue_id !== $this->issue->id || $page->issue_id !== $this->issue->id) {
            return;
        }

        $this->attachContentToPage($content, $page, 'percentage');
    }

    /**
     * Estende un contenuto **già assegnato altrove** a una pagina
     * aggiuntiva, individuata per posizione (non per id: più naturale da
     * scrivere in un prompt dell'utente che non ha sotto mano l'id della
     * pagina). Il modello supporta da sempre un contenuto su più pagine
     * (`Content::pages()` è `belongsToMany`), ma finora non c'era alcun
     * modo di *arrivarci* dall'interfaccia — il pannello "non assegnati"
     * fa sparire un contenuto non appena ha una prima pagina, quindi non è
     * più ri-trascinabile da lì. Questo metodo è il percorso alternativo:
     * un pulsante "↗" sul contenuto già assegnato, non un secondo drag.
     * Riusa la stessa logica di calcolo/percentuale/broadcast di
     * assignContent() tramite attachContentToPage(), sotto la chiave di
     * errore "extend" invece di "percentage" per non confondere i due
     * pannelli nella UI se falliscono entrambi nello stesso render.
     */
    public function extendToPage(int $contentId, int $targetPosition): void
    {
        $this->authorize('update', $this->issue);

        $content = Content::with('advertisement')->findOrFail($contentId);

        if ($content->issue_id !== $this->issue->id) {
            return;
        }

        $targetPage = $this->issue->pages()->where('position', $targetPosition)->first();

        if ($targetPage === null) {
            $this->addError('extend', "Non esiste nessuna pagina in posizione {$targetPosition}.");

            return;
        }

        if ($targetPage->contents()->where('content_id', $contentId)->exists()) {
            $this->addError('extend', 'Il contenuto è già assegnato a quella pagina.');

            return;
        }

        $this->attachContentToPage($content, $targetPage, 'extend');
    }

    /**
     * Logica condivisa tra assignContent() ed extendToPage(): calcola la
     * percentuale, verifica lo spazio disponibile, allega il contenuto e
     * trasmette lo stesso evento ContentAssigned in entrambi i casi — dal
     * punto di vista degli altri utenti collegati è la stessa identica
     * cosa (un contenuto è comparso su una pagina), non ha senso avere due
     * eventi diversi per come è arrivato lì.
     */
    private function attachContentToPage(Content $content, Page $page, string $errorKey): void
    {
        if ($this->blockIfLocked($page)) {
            return;
        }

        $occupied = $page->contents()->pluck('occupied_percentage')->all();

        $percentage = $content->type === ContentType::Pubblicita
            ? $content->advertisement->occupiedPercentage()
            : PagePercentageAllocator::freeSpace($occupied);

        if (! PagePercentageAllocator::fits($occupied, $percentage)) {
            $this->addError($errorKey, 'Spazio insufficiente su questa pagina per assegnare il contenuto.');

            return;
        }

        $page->contents()->attach($content->id, ['occupied_percentage' => (string) $percentage]);
        $this->syncPageContentType($page);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $content->id,
            action: 'content.assigned',
            description: "Contenuto «{$content->displayLabel()}» assegnato alla pagina {$page->position} ({$percentage}%)",
            new: ['page_id' => $page->id, 'occupied_percentage' => $percentage],
        );

        broadcast(new ContentAssigned(
            issueId: $this->issue->id,
            pageId: $page->id,
            contentId: $content->id,
            percentage: $percentage,
            assignedByUserId: auth()->id(),
            assignedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    /**
     * Bug reale scoperto in questa sessione: assegnare/rimuovere un
     * contenuto non aggiornava mai `page.content_type` (il colore della
     * card) — restava "bianca" per sempre su qualunque pagina toccata
     * dall'interfaccia reale, mai un problema visibile nei dati demo solo
     * perché il seeder imposta `content_type` a mano con un proprio
     * helper mai usato dall'app vera. Richiamato dopo ogni attach/detach
     * in questo componente, in modo che il colore resti sempre coerente
     * con i contenuti effettivamente presenti — non solo al momento
     * dell'assegnazione iniziale.
     */
    private function syncPageContentType(Page $page): void
    {
        $types = $page->contents()->pluck('type')->all();
        $resolved = PageContentTypeResolver::resolve($types);

        if ($resolved !== $page->content_type) {
            $page->update(['content_type' => $resolved]);
        }
    }

    public function updateContentPercentage(int $pageId, int $contentId, float $percentage): void
    {
        $this->authorize('update', $this->issue);

        $page = Page::findOrFail($pageId);

        if ($page->issue_id !== $this->issue->id) {
            return;
        }

        if ($this->blockIfLocked($page)) {
            return;
        }

        $others = $page->contents()->where('content_id', '!=', $contentId)->pluck('occupied_percentage')->all();

        if (! PagePercentageAllocator::fits($others, $percentage)) {
            $this->addError('percentage', 'La percentuale indicata supera lo spazio disponibile sulla pagina.');

            return;
        }

        $oldPercentage = (float) $page->contents()->where('content_id', $contentId)->first()->pivot->occupied_percentage;

        $page->contents()->updateExistingPivot($contentId, ['occupied_percentage' => (string) $percentage]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $contentId,
            action: 'content.percentage_changed',
            description: "Percentuale del contenuto sulla pagina {$page->position} cambiata da {$oldPercentage}% a {$percentage}%",
            old: ['occupied_percentage' => $oldPercentage],
            new: ['occupied_percentage' => $percentage],
        );

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

        if ($this->blockIfLocked($page)) {
            return;
        }

        $page->contents()->detach($contentId);
        $this->syncPageContentType($page);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $contentId,
            action: 'content.unassigned',
            description: "Contenuto rimosso dalla pagina {$page->position}",
            old: ['page_id' => $page->id],
        );

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

    #[On('echo-presence:issue.{issue.id},ContentCreated')]
    public function onContentCreated(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // il nuovo contenuto tra i "non assegnati" dal database. Creato
        // da App\Livewire\Timone\ContentCreate, un componente a sé — vedi
        // "Decisioni architetturali da ricordare" in HANDOFF.md.
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

        if ($this->blockIfLocked($page, 'sovrascritta')) {
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

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'PageFile',
            entityId: $pageFile->id,
            action: 'page.file_uploaded',
            description: "PDF «{$pageFile->original_name}» caricato sulla pagina {$page->position}",
            new: ['page_id' => $page->id, 'original_name' => $pageFile->original_name],
        );

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
                // Per il badge "×N pagine" (Grid::extendToPage()) basterebbe
                // solo l'id; la posizione serve in più ad
                // App\Support\AutomaticChecks per segnalare i contenuti su
                // pagine non contigue — stesso eager load riusato per
                // entrambi, non vale la pena separarli.
                'contents.pages:pages.id,pages.position',
                'files' => fn ($query) => $query->latest()->limit(1),
                'lockedBy:id,name',
            ])
            ->get();

        $unassignedContents = $this->issue->unassignedContents()
            ->with(['article', 'advertisement'])
            ->when($this->contentSearch !== '', fn ($query) => $query->where('title', 'like', '%'.$this->contentSearch.'%'))
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
            'automaticChecks' => AutomaticChecks::check($pages),
        ]);
    }
}
