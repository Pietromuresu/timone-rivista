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
use App\Events\PagesBlockMoved;
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
use App\Support\PdfPageMeasurer;
use App\Support\ThumbnailProgressEstimator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Stato del riepilogo di conflitto per un caricamento PDF multipagina
     * (§2.1) in attesa di conferma dall'utente — null quando non c'è
     * nessun caricamento multipagina in sospeso. Il file resta comunque
     * in $pendingUploads[$pageId] finché l'utente non conferma/annulla
     * (vedi Grid::confirmMultipageUpload()/cancelMultipageUpload()).
     *
     * @var array{pageId: int, initiatingPosition: int, totalPdfPages: int, availablePages: int, targetPositions: list<int>, conflictingPositions: list<int>}|null
     */
    public ?array $multipageUploadConflict = null;

    /**
     * Solo un'etichetta "nascosto per ora" per il banner di avanzamento
     * miniature (2026-07-31) — NON i dati stessi dell'avanzamento, che
     * restano sempre ricalcolati da zero da render() (vedi
     * App\Support\ThumbnailProgressEstimator). Effimero di proposito: un
     * refresh della pagina lo riazzera, e il banner ricompare se c'è
     * ancora lavoro reale in corso — comportamento corretto, diverso dal
     * bug segnalato dall'utente (che riguardava la sparizione dei DATI di
     * avanzamento, non il fatto che un dismiss manuale sopravviva).
     */
    public bool $thumbnailProgressDismissed = false;

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

    /**
     * Tipizzato `string`, non `int` — un `int $newTotalPages` andava in
     * errore Livewire ad ogni carattere digitato: `wire:model` scrive
     * sempre una stringa (anche "" a campo svuotato), e PHP non riesce a
     * coercire una stringa non numerica su una proprietà tipata `int`
     * (`TypeError`, non catturabile lato utente). Stesso pattern già
     * adottato altrove nel progetto per campi numerici opzionali/in corso
     * di digitazione (vedi nota "campi numerici/data opzionali" in
     * HANDOFF.md, Punto 6) — normalizzato in intero solo dove serve,
     * tramite parsedNewTotalPages(), mai un cast diretto.
     */
    public string $newTotalPages = '0';

    /**
     * 'end' = aggiunge le nuove pagine in coda; 'position' = le inserisce
     * prima di $insertAtPosition, facendo slittare le pagine successive.
     */
    public string $insertMode = 'end';

    /**
     * Stesso motivo di $newTotalPages sopra (`?int` andava in errore su
     * un campo lasciato vuoto: "" non è né un int né esplicitamente
     * `null`, quindi non rientra nella nullabilità del tipo).
     */
    public ?string $insertAtPosition = null;

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

    /**
     * Selezione multipla e azioni di massa (spec §6.4/§19): a differenza
     * della modalità scambio (che intercetta il click sull'intera card),
     * qui la selezione avviene tramite checkbox dedicata su ogni
     * card/riga, quindi le due modalità possono restare attive insieme
     * senza conflitto. $selectedPageIds contiene solo id di pagine di
     * *questa* issue (verificato ad ogni azione bulk, mai fidandosi del
     * solo stato client).
     *
     * @var array<int, int>
     */
    public bool $selectionMode = false;

    public array $selectedPageIds = [];

    /**
     * Esito dell'ultima azione di massa (es. "3 pagine aggiornate, 1
     * bloccata ignorata") — non è un errore, quindi non usa addError()
     * come il resto del componente (che lo riserva a condizioni di
     * blocco/conflitto reali), ma un messaggio informativo a sé.
     */
    public ?string $bulkResultMessage = null;

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
            $this->newTotalPages = (string) $this->issue->total_pages;
            $this->insertMode = 'end';
            $this->insertAtPosition = null;
        }
    }

    /**
     * Normalizza il campo testuale $newTotalPages in un intero valido
     * (0-2000), o null se il valore digitato non è utilizzabile (vuoto,
     * non numerico, negativo, decimale...) — unico punto in cui la
     * stringa grezza del campo viene convertita, per non ripetere la
     * stessa validazione difensiva sia in resizePages() sia in render()
     * (anteprima d'impatto). Nessuna eccezione: un input non valido
     * produce semplicemente null, gestito dal chiamante con un messaggio
     * controllato invece di un errore Livewire.
     */
    private function parsedNewTotalPages(): ?int
    {
        $trimmed = trim($this->newTotalPages);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        $value = (int) $trimmed;

        return $value <= 2000 ? $value : null;
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

        $this->resetErrorBag('pageCount');

        $currentTotal = $this->issue->total_pages;
        $newTotal = $this->parsedNewTotalPages();

        if ($newTotal === null) {
            $this->addError('pageCount', 'Inserisci un numero di pagine valido (un intero da 0 a 2000).');

            return;
        }

        if ($newTotal === $currentTotal) {
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

    private function parsedInsertAtPosition(): ?int
    {
        $trimmed = trim((string) $this->insertAtPosition);

        return $trimmed !== '' && ctype_digit($trimmed) ? (int) $trimmed : null;
    }

    private function insertPages(int $currentTotal, int $newTotal): void
    {
        $countToAdd = $newTotal - $currentTotal;
        $requestedPosition = $this->parsedInsertAtPosition();

        $insertBefore = $this->insertMode === 'position' && $requestedPosition !== null
            ? max(1, min($requestedPosition, $currentTotal + 1))
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
     * Sposta l'intera selezione multipla corrente come blocco unico (Fase
     * 5) — a differenza di movePage() (una pagina, con le intermedie che
     * slittano), qui l'intero insieme di pagine selezionate si sposta
     * insieme, mantenendo il proprio ordine relativo originale
     * (App\Support\PageReorderer::moveBlock(), che compatta automaticamente
     * anche una selezione non contigua in origine — scelta esplicita
     * documentata in HANDOFF.md, mai un comportamento ambiguo/silenzioso).
     *
     * $anchorPageId è la pagina che l'utente ha effettivamente afferrato
     * per trascinare (deve far parte della selezione corrente — verificato
     * lato server, mai fidandosi del solo stato client); $toPosition è la
     * posizione calcolata da Sortable per quella pagina, usata come punto
     * di inserimento per l'intero blocco.
     *
     * Guardia sulle pagine bloccate DIVERSA da quella delle azioni di massa
     * (bulkChangeStatus()/bulkToggleLock(), che ignorano le pagine bloccate
     * e procedono con il resto): qui, richiesta esplicita della fase,
     * anche una sola pagina bloccata nella selezione rifiuta l'INTERA
     * operazione, senza applicarne una parte.
     */
    public function moveSelectedBlock(int $anchorPageId, int $toPosition): void
    {
        $this->authorize('update', $this->issue);

        $selected = Page::whereIn('id', $this->selectedPageIds)
            ->where('issue_id', $this->issue->id)
            ->get();

        // Stato client non valido/selezione non davvero pertinente a questo
        // drag: degrada al comportamento di una singola pagina invece di
        // ignorare silenziosamente il gesto dell'utente.
        if (! $this->selectionMode || $selected->count() < 2 || ! $selected->contains('id', $anchorPageId)) {
            $this->movePage($anchorPageId, $toPosition);

            return;
        }

        $lockedSelected = $selected->filter(fn (Page $page) => $page->isLocked());

        if ($lockedSelected->isNotEmpty()) {
            $positions = $lockedSelected->pluck('position')->sort()->values()->implode(', ');
            $this->addError('locked', $lockedSelected->count() === 1
                ? "La pagina {$positions} è bloccata: sbloccala prima di spostare l'intera selezione."
                : "Le pagine {$positions} sono bloccate: sbloccale prima di spostare l'intera selezione.");

            return;
        }

        $blockPageIds = $selected->pluck('id')->all();

        $conflict = false;
        $applied = null;

        DB::transaction(function () use ($blockPageIds, $toPosition, &$conflict, &$applied) {
            $issue = Issue::whereKey($this->issue->id)->lockForUpdate()->first();

            if ($issue->reorder_version !== $this->reorderVersion) {
                $conflict = true;
                $this->reorderVersion = $issue->reorder_version;

                return;
            }

            $positions = $issue->pages()->pluck('position', 'id')->all();
            $changes = PageReorderer::moveBlock($positions, $blockPageIds, $toPosition);

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

            $applied = true;
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
            entityId: $anchorPageId,
            action: 'page.block_moved',
            description: count($blockPageIds).' pagine spostate insieme come blocco unico',
            new: ['pageIds' => $blockPageIds, 'toPosition' => $toPosition],
        );

        // Un solo evento per l'intera operazione (non N, uno per pagina
        // spostata) — richiesta esplicita della fase, per evitare che gli
        // altri utenti collegati vedano stati intermedi inconsistenti.
        broadcast(new PagesBlockMoved(
            issueId: $this->issue->id,
            pageIds: $blockPageIds,
            movedByUserId: auth()->id(),
            movedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    #[On('echo-presence:issue.{issue.id},PagesBlockMoved')]
    public function onPagesBlockMoved(): void
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

        if ($newStatus === PageStatus::OkStampa && ! $this->pageHasFile($page)) {
            $this->addError('pdfRequired', "La pagina {$page->position} non ha ancora un PDF caricato: non può essere segnata «Ok stampa».");

            return;
        }

        $this->applyStatusChange($page, $newStatus);
    }

    /**
     * §2.1: ogni pagina deve avere un PDF associato per essere considerata
     * pronta per la stampa — controllo bloccante solo sulla transizione
     * verso PageStatus::OkStampa (l'"approvazione finale" citata dal
     * prompt), non sugli stati intermedi come Revisionata. Una query
     * dedicata invece di riusare $page->files (limitata all'ultimo file
     * da Grid::render()) perché qui serve solo sapere SE esiste almeno
     * un file, non quale sia l'ultimo.
     */
    private function pageHasFile(Page $page): bool
    {
        return $page->files()->exists();
    }

    /**
     * Logica condivisa tra changePageStatus() (singola pagina) e
     * bulkChangeStatus() (selezione multipla) — stessa mutazione, stesso
     * log, stesso evento, a prescindere da dove arriva la richiesta.
     * Il chiamante deve già aver verificato blocco/appartenenza/stato
     * diverso da quello attuale: qui non si ripetono quei controlli.
     */
    private function applyStatusChange(Page $page, PageStatus $newStatus): void
    {
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

        $this->applyLockToggle($page, ! $page->isLocked());
    }

    /**
     * Logica condivisa tra togglePageLock() (singola pagina) e
     * bulkToggleLock() (selezione multipla). $lock=true blocca, false
     * sblocca — il chiamante decide già la direzione (togglePageLock la
     * inverte rispetto allo stato attuale, bulkToggleLock la impone a
     * prescindere, così un pulsante "Blocca selezionate" fa sempre e solo
     * quello anche su una selezione mista).
     */
    private function applyLockToggle(Page $page, bool $lock): void
    {
        if (! $lock) {
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

    public function toggleSelectionMode(): void
    {
        $this->selectionMode = ! $this->selectionMode;
        $this->selectedPageIds = [];
        $this->bulkResultMessage = null;
    }

    /**
     * Click sulla checkbox di una card/riga: no-op se la selezione
     * multipla non è attiva (stessa difesa server-side già usata per
     * selectForSwap() in modalità scambio, non solo nascosta lato UI).
     */
    public function togglePageSelection(int $pageId): void
    {
        if (! $this->selectionMode) {
            return;
        }

        if (in_array($pageId, $this->selectedPageIds, true)) {
            $this->selectedPageIds = array_values(array_diff($this->selectedPageIds, [$pageId]));

            return;
        }

        $this->selectedPageIds[] = $pageId;
    }

    public function selectAllPages(): void
    {
        $this->selectedPageIds = $this->issue->pages()->pluck('id')->all();
    }

    public function clearSelection(): void
    {
        $this->selectedPageIds = [];
        $this->bulkResultMessage = null;
    }

    /**
     * Applica un nuovo stato a tutte le pagine selezionate (spec §6.4:
     * "cambio stato multiplo"). Le pagine bloccate nella selezione sono
     * ignorate (mai un errore bloccante: il resto della selezione va
     * comunque applicato, coerente con la scelta già presa per la
     * riduzione pagine — §6.6, "il blocco vince" solo per la singola
     * pagina bloccata, non per l'intera operazione) — il conteggio di
     * quelle ignorate compare nel messaggio di esito.
     */
    public function bulkChangeStatus(string $status): void
    {
        $this->authorize('update', $this->issue);

        $newStatus = PageStatus::tryFrom($status);

        if ($newStatus === null || $this->selectedPageIds === []) {
            return;
        }

        $pages = Page::whereIn('id', $this->selectedPageIds)->where('issue_id', $this->issue->id)->get();

        $applied = 0;
        $skippedLocked = 0;
        $skippedNoPdf = 0;

        foreach ($pages as $page) {
            if ($page->isLocked()) {
                $skippedLocked++;

                continue;
            }

            if ($page->status === $newStatus) {
                continue;
            }

            // Stessa regola di changePageStatus() (§2.1): mai approvare per
            // la stampa in massa una pagina senza PDF — ignorata, non
            // blocca il resto della selezione (stesso principio già in uso
            // per le pagine bloccate).
            if ($newStatus === PageStatus::OkStampa && ! $this->pageHasFile($page)) {
                $skippedNoPdf++;

                continue;
            }

            $this->applyStatusChange($page, $newStatus);
            $applied++;
        }

        $this->reportBulkResult(
            $applied,
            $skippedLocked,
            'aggiornata a «'.$newStatus->label().'»',
            'aggiornate a «'.$newStatus->label().'»',
            $skippedNoPdf,
        );
    }

    /**
     * Blocca o sblocca tutte le pagine selezionate in un colpo solo.
     * A differenza di bulkChangeStatus() non c'è nulla da "ignorare" per
     * blocco: bloccare una pagina già bloccata (o sbloccarne una già
     * libera) è semplicemente un no-op silenzioso per quella pagina.
     */
    public function bulkToggleLock(bool $lock): void
    {
        $this->authorize('update', $this->issue);

        if ($this->selectedPageIds === []) {
            return;
        }

        $pages = Page::whereIn('id', $this->selectedPageIds)->where('issue_id', $this->issue->id)->get();

        $applied = 0;

        foreach ($pages as $page) {
            if ($page->isLocked() === $lock) {
                continue;
            }

            $this->applyLockToggle($page, $lock);
            $applied++;
        }

        $this->reportBulkResult(
            $applied,
            0,
            $lock ? 'bloccata' : 'sbloccata',
            $lock ? 'bloccate' : 'sbloccate',
        );
    }

    /**
     * Costruisce il messaggio di esito di un'azione bulk, con l'accordo
     * singolare/plurale sia sul sostantivo ("pagina"/"pagine") sia
     * sull'aggettivo/participio passato ("aggiornata"/"aggiornate",
     * "bloccata"/"bloccate"...) — l'italiano richiede l'accordo su
     * entrambi, non basta pluralizzare solo il sostantivo.
     */
    private function reportBulkResult(int $applied, int $skippedLocked, string $verbSingular, string $verbPlural, int $skippedNoPdf = 0): void
    {
        $message = "{$applied} ".($applied === 1 ? 'pagina '.$verbSingular : 'pagine '.$verbPlural).'.';

        if ($skippedLocked > 0) {
            $message .= " {$skippedLocked} ".($skippedLocked === 1 ? 'pagina bloccata ignorata' : 'pagine bloccate ignorate').'.';
        }

        if ($skippedNoPdf > 0) {
            $message .= " {$skippedNoPdf} ".($skippedNoPdf === 1 ? 'pagina senza PDF ignorata' : 'pagine senza PDF ignorate').'.';
        }

        $this->bulkResultMessage = $message;
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

    /**
     * §2.1: un PDF di una sola pagina va sempre e solo sulla pagina su cui
     * è stato caricato (comportamento invariato). Un PDF con N>1 pagine
     * interne deve occupare automaticamente le N pagine successive del
     * timone — ma MAI scrivere nulla finché l'utente non ha visto ed
     * eventualmente confermato un riepilogo dei conflitti: qui ci si
     * ferma alla sola rilevazione, salvando lo stato in
     * $multipageUploadConflict, così il file resta disponibile
     * (`$pendingUploads[$pageId]` non viene svuotato) per la conferma
     * successiva (confirmMultipageUpload()/cancelMultipageUpload()).
     */
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

        // Bug scoperto il 2026-07-31: questa validazione usava una chiave
        // d'errore artificiale ("pendingFile") che nessuna vista del
        // progetto legge (`grep @error` non trova nulla) — se mai fosse
        // scattata, la richiesta sarebbe comunque andata a buon fine dal
        // punto di vista di Livewire (nessuna eccezione non gestita, nessun
        // errore in console), ma l'utente non avrebbe visto NULLA cambiare:
        // esattamente il sintomo "si blocca, nessun errore in console"
        // segnalato per i PDF multipagina. Allineata alla stessa chiave
        // "pendingUploads.{pageId}" già letta dal badge "⚠️ upload fallito"
        // aggiunto in page-row.blade.php/page-card.blade.php.
        $uploadFieldKey = "pendingUploads.{$pageId}";

        validator(['pendingUploads' => [$pageId => $file]], [
            // Regola coerente con config/livewire.php e docker/php/uploads.ini (100MB).
            $uploadFieldKey => 'required|file|mimes:pdf|max:102400',
        ])->validate();

        try {
            // Un PDF illeggibile (pageCount() torna null, es. ext-imagick
            // assente in locale) degrada al comportamento precedente — 1
            // sola pagina — invece di bloccare l'upload: la stessa
            // robustezza richiesta esplicitamente per il controllo formato
            // (§2.3) si applica per coerenza anche qui.
            $pdfPageCount = PdfPageMeasurer::pageCount($file->getRealPath()) ?? 1;

            if ($pdfPageCount <= 1) {
                $this->storeUploadedPdf($page, $file, pdfPageNumber: 1, totalPdfPages: 1);
                unset($this->pendingUploads[$pageId]);

                return;
            }

            $targetPages = $this->multipageTargetPages($page, $pdfPageCount);

            $conflicting = $targetPages
                ->reject(fn (Page $p) => $p->id === $page->id)
                ->filter(fn (Page $p) => $p->files->isNotEmpty());

            $this->multipageUploadConflict = [
                'pageId' => $pageId,
                'initiatingPosition' => $page->position,
                'totalPdfPages' => $pdfPageCount,
                'availablePages' => $targetPages->count(),
                'targetPositions' => $targetPages->pluck('position')->all(),
                'conflictingPositions' => $conflicting->pluck('position')->values()->all(),
            ];

            // Riepilogo mostrato come modale (2026-07-31), non più un
            // pannello inline: su una griglia lunga il pannello poteva
            // apparire fuori dal viewport se l'utente aveva scrollato oltre
            // la pagina su cui ha caricato il file, dando l'impressione che
            // il caricamento fosse "bloccato" mentre in realtà il riepilogo
            // era pronto ma non visibile. La modale è sempre in vista,
            // indipendentemente dallo scroll.
            $this->dispatch('open-modal', 'multipage-upload-conflict');

            // Il file resta in $this->pendingUploads[$pageId] finché
            // l'utente non sceglie esplicitamente cosa fare — nessuna
            // scrittura ancora.
        } catch (\Throwable $e) {
            // Bug scoperto il 2026-07-31 (vedi HANDOFF.md, "Upload PDF
            // multipagina 'bloccato'..."): senza questo catch, un'eccezione
            // imprevista qui (es. Imagick/Ghostscript su un PDF reale con
            // struttura anomala) propagava come 500 generico di Livewire —
            // di solito visibile, ma non garantito in ogni configurazione
            // browser/proxy. Log esplicito (ora persistito, vedi
            // docker-compose.yml storage_logs) + badge visibile invece di
            // fidarsi del comportamento di default.
            report($e);

            Log::error('Caricamento PDF pagina fallito in modo imprevisto', [
                'page_id' => $pageId,
                'issue_id' => $this->issue->id,
                'file_name' => $file->getClientOriginalName(),
                'file_size_bytes' => $file->getSize(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            unset($this->pendingUploads[$pageId]);

            $this->addError(
                "pendingUploads.{$pageId}",
                'Caricamento fallito per un errore imprevisto durante la lettura del PDF. Riprova; se il problema persiste, contatta chi gestisce il server (dettagli nel log).'
            );
        }
    }

    /**
     * Le pagine del timone che un PDF multipagina caricato su $initiatingPage
     * andrebbe a occupare: da $initiatingPage in poi, fino a $count pagine
     * — meno se l'edizione finisce prima (mai un errore, semplicemente
     * $availablePages nel riepilogo risulta inferiore a $totalPdfPages).
     *
     * @return \Illuminate\Support\Collection<int, Page>
     */
    private function multipageTargetPages(Page $initiatingPage, int $count): \Illuminate\Support\Collection
    {
        return $this->issue->pages()
            ->with('files')
            ->where('position', '>=', $initiatingPage->position)
            ->where('position', '<', $initiatingPage->position + $count)
            ->orderBy('position')
            ->get();
    }

    /**
     * Annulla un caricamento multipagina in attesa di conferma: nessuna
     * scrittura era comunque ancora avvenuta, quindi basta scartare lo
     * stato e il file temporaneo.
     */
    public function cancelMultipageUpload(): void
    {
        $pageId = $this->multipageUploadConflict['pageId'] ?? null;
        $this->multipageUploadConflict = null;
        $this->dispatch('close-modal', 'multipage-upload-conflict');

        if ($pageId !== null) {
            unset($this->pendingUploads[$pageId]);
        }
    }

    /**
     * Chiusura manuale del banner di avanzamento miniature — solo
     * un'etichetta "nascosto per ora" (vedi $thumbnailProgressDismissed),
     * non tocca in alcun modo i dati reali di avanzamento.
     */
    public function dismissThumbnailProgress(): void
    {
        $this->thumbnailProgressDismissed = true;
    }

    /**
     * Applica un caricamento multipagina confermato dall'utente (§2.1).
     * $overwriteConflicts distingue le due scelte esplicite offerte nel
     * riepilogo: `false` = "salta le pagine in conflitto" (restano
     * intatte, il PDF occupa solo le pagine libere), `true` = "sovrascrivi"
     * (ogni pagina dell'intervallo riceve comunque il PDF, indipendentemente
     * da cosa avesse già). In entrambi i casi una pagina bloccata
     * nell'intervallo viene sempre saltata (mai un'eccezione, la stessa
     * guardia blockIfLocked() di ogni altra mutazione — qui applicata per
     * singola pagina invece che sull'intera operazione, coerente con
     * bulkChangeStatus(): il resto del batch va comunque a buon fine).
     *
     * Nota consapevole (documentata anche in HANDOFF.md): non è
     * implementata una terza scelta "riposiziona manualmente le pagine in
     * conflitto altrove" — costruire un vero selettore di riposizionamento
     * per un'azione così occasionale sarebbe sproporzionato (CLAUDE.md,
     * regola 3); "salta" ottiene lo stesso risultato non distruttivo,
     * lasciando all'utente la scelta manuale successiva di dove mettere le
     * pagine del PDF che non hanno trovato posto.
     */
    public function confirmMultipageUpload(bool $overwriteConflicts): void
    {
        $this->authorize('update', $this->issue);

        $conflict = $this->multipageUploadConflict;

        if ($conflict === null) {
            return;
        }

        $pageId = $conflict['pageId'];
        $file = $this->pendingUploads[$pageId] ?? null;

        $this->multipageUploadConflict = null;
        $this->dispatch('close-modal', 'multipage-upload-conflict');

        if (! $file instanceof TemporaryUploadedFile) {
            unset($this->pendingUploads[$pageId]);

            return;
        }

        $targetPages = $this->issue->pages()
            ->whereIn('position', $conflict['targetPositions'])
            ->orderBy('position')
            ->get();

        foreach ($targetPages as $index => $targetPage) {
            $isConflicting = in_array($targetPage->position, $conflict['conflictingPositions'], true);

            if ($isConflicting && ! $overwriteConflicts) {
                continue;
            }

            if ($this->blockIfLocked($targetPage, 'sovrascritta')) {
                continue;
            }

            $this->storeUploadedPdf($targetPage, $file, pdfPageNumber: $index + 1, totalPdfPages: $conflict['totalPdfPages']);
        }

        // Nessuno stato locale da tracciare per l'avanzamento del rendering
        // miniature: render() lo ricalcola da zero da App\Support\
        // ThumbnailProgressEstimator, guardando lo stato reale in database
        // di OGNI pagina del numero ancora Pending/Processing (non solo
        // quelle di questo batch) — sopravvive a un refresh della pagina,
        // a differenza di una proprietà Livewire effimera (bug segnalato
        // dall'utente il 2026-07-31, vedi HANDOFF.md).
        unset($this->pendingUploads[$pageId]);
    }

    /**
     * Scrittura effettiva di un PDF su una pagina — condivisa dal percorso
     * a pagina singola e da ciascuna pagina coinvolta in un caricamento
     * multipagina confermato. Ogni pagina riceve una copia indipendente
     * del file fisico (più semplice di un riferimento condiviso tra righe
     * PageFile: ogni riga resta proprietaria del proprio file, coerente
     * con la cascata di eliminazione e con `pagefiles:prune-orphaned` già
     * esistenti, che non sanno nulla di riferimenti condivisi) — il costo
     * in spazio disco è accettabile per PDF tipicamente di poche pagine.
     */
    private function storeUploadedPdf(Page $page, TemporaryUploadedFile $file, int $pdfPageNumber, int $totalPdfPages): void
    {
        $storedPath = $file->storeAs("pages/{$page->id}", Str::uuid().'.pdf', 'local');

        $pageFile = PageFile::create([
            'page_id' => $page->id,
            'uploaded_by' => auth()->id(),
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'thumbnail_status' => ThumbnailStatus::Pending,
            'pdf_page_number' => $pdfPageNumber,
        ]);

        // Un nuovo upload crea nuovo lavoro reale da tracciare: se il
        // banner era stato chiuso in precedenza, ricompare per questo.
        $this->thumbnailProgressDismissed = false;

        $description = $totalPdfPages > 1
            ? "PDF «{$pageFile->original_name}» (pagina {$pdfPageNumber} di {$totalPdfPages}) caricato sulla pagina {$page->position}"
            : "PDF «{$pageFile->original_name}» caricato sulla pagina {$page->position}";

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'PageFile',
            entityId: $pageFile->id,
            action: 'page.file_uploaded',
            description: $description,
            new: ['page_id' => $page->id, 'original_name' => $pageFile->original_name, 'pdf_page_number' => $pdfPageNumber],
        );

        GeneratePageFileThumbnail::dispatch($pageFile);
    }

    /**
     * Forza l'accettazione di un formato non conforme (§2.3) — un avviso,
     * non un blocco: l'utente conferma esplicitamente di aver visto la
     * non conformità e di volerla accettare per un caso limite legittimo.
     */
    public function confirmFormatOverride(int $pageFileId): void
    {
        $this->authorize('update', $this->issue);

        $pageFile = PageFile::with('page')->findOrFail($pageFileId);

        if ($pageFile->page->issue_id !== $this->issue->id) {
            return;
        }

        if ($this->blockIfLocked($pageFile->page)) {
            return;
        }

        if (! $pageFile->hasUnresolvedFormatMismatch()) {
            return;
        }

        $pageFile->update([
            'format_override_confirmed_by' => auth()->id(),
            'format_override_confirmed_at' => now(),
        ]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'PageFile',
            entityId: $pageFile->id,
            action: 'page.file_format_override_confirmed',
            description: "Formato non conforme accettato manualmente sul PDF della pagina {$pageFile->page->position}",
        );
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

        // Anteprima d'impatto calcolata solo su un valore già validato: se
        // l'utente ha lasciato il campo vuoto o con un valore non numerico
        // (in corso di digitazione, prima del blur), niente anteprima da
        // mostrare invece di passare una stringa non valida a
        // PageCountResizer::impact() (che si aspetta un int vero).
        $newTotalPagesParsed = $this->parsedNewTotalPages();

        $pageCountImpact = $this->showPageCountEditor && $newTotalPagesParsed !== null
            ? PageCountResizer::impact($pages, $this->issue->total_pages, $newTotalPagesParsed)
            : null;

        // Fase 3 (§3): le pubblicità "prenotate" (non ancora assegnate a
        // nessuna pagina) occupano comunque il carico pubblicitario nel
        // cruscotto — riusa $unassignedContents, già caricato per il
        // pannello "contenuti da assegnare" con 'advertisement' già
        // eager-loaded, nessuna query aggiuntiva.
        $reservedAdvertisements = $unassignedContents
            ->where('type', ContentType::Pubblicita)
            ->map(fn ($content) => $content->advertisement)
            ->filter()
            ->values();

        // Avanzamento reale del rendering miniature (2026-07-31, vedi
        // HANDOFF.md) — ricalcolato da zero da App\Support\
        // ThumbnailProgressEstimator ad ogni render(), leggendo solo lo
        // stato persistito in database ($pages ha già $latestFile eager-
        // loaded, 'files' => query()->latest()->limit(1) qui sopra):
        // sopravvive a un refresh della pagina, a differenza di una
        // proprietà Livewire effimera. $avgThumbnailSeconds passato anche
        // ai partial page-row/page-card per la stima PER SINGOLA CARD,
        // non solo quella aggregata.
        $pendingThumbnailFiles = $pages
            ->map(fn (Page $page) => $page->files->first())
            ->filter(fn (?PageFile $file) => $file && in_array($file->thumbnail_status, [ThumbnailStatus::Pending, ThumbnailStatus::Processing], true))
            ->values();

        $avgThumbnailSeconds = ThumbnailProgressEstimator::averageProcessingSeconds($pages->pluck('id'));

        return view('livewire.timone.grid', [
            'pages' => $pages,
            'spreads' => $this->viewMode === 'doppia' ? PageSpreadBuilder::build($pages) : null,
            'unassignedContents' => $unassignedContents,
            'reorderLogs' => $reorderLogs,
            'adLoad' => AdLoadCalculator::summarize($pages, $reservedAdvertisements),
            'adThreshold' => $this->issue->magazine->ad_threshold_percentage,
            'unassignedAdCount' => $reservedAdvertisements->count(),
            'pageCountImpact' => $pageCountImpact,
            'newTotalPagesParsed' => $newTotalPagesParsed,
            'automaticChecks' => AutomaticChecks::check($pages),
            'avgThumbnailSeconds' => $avgThumbnailSeconds,
            'thumbnailProgress' => ThumbnailProgressEstimator::aggregate($pendingThumbnailFiles, $avgThumbnailSeconds),
        ]);
    }
}
