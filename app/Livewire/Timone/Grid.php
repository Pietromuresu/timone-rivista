<?php

namespace App\Livewire\Timone;

use App\Enums\ContentType;
use App\Enums\CoverSlot;
use App\Enums\PageStatus;
use App\Enums\ThumbnailStatus;
use App\Enums\PageContentType;
use App\Events\ContentAssigned;
use App\Events\ContentListChanged;
use App\Events\CoversChanged;
use App\Events\ContentUnassigned;
use App\Events\IssuePageCountUpdated;
use App\Events\PageGroupsChanged;
use App\Events\PageLocked;
use App\Events\PageMoved;
use App\Events\PagesBlockMoved;
use App\Events\PagesSwapped;
use App\Events\PageStatusUpdated;
use App\Events\PageUnlocked;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\PageGroup;
use App\Models\PageReorderLog;
use App\Support\ActivityLogger;
use App\Support\AdLoadCalculator;
use App\Support\AutomaticChecks;
use App\Support\IssueCovers;
use App\Support\ContentPageAttacher;
use App\Support\ContentPlacer;
use App\Support\PageCountResizer;
use App\Support\PageGroupLinker;
use App\Support\PageGroupResolver;
use App\Support\PageInserter;
use App\Support\PagePercentageAllocator;
use App\Support\PageReorderer;
use App\Support\PageSpreadBuilder;
use App\Support\ThumbnailProgressEstimator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class Grid extends Component
{
    public Issue $issue;

    /**
     * griglia (flat plan a card), doppia (pagine affiancate come aperte
     * nella rivista stampata) o lista (una riga per pagina).
     */
    public string $viewMode = 'griglia';

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
     * Ultimo valore di `issues.pages_updated_at` visto da QUESTO client
     * (2026-09-01, fallback a polling — vedi checkForUpdates()) — stringa
     * grezza, mai un Carbon: va confrontata byte-per-byte contro il valore
     * letto da una query a colonna singola, non formattata/manipolata come
     * data altrove. `null` finché non è mai stato toccato nulla su questa
     * issue (caso raro, issue appena creata senza alcuna mutazione).
     */
    public ?string $lastKnownPagesUpdatedAt = null;

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
     * Fase 4.3 — pannello "contenuti archiviati" dentro il pannello
     * "contenuti da assegnare" esistente (stesso pattern chiuso-di-default
     * di showReorderLog/showPageCountEditor: la query gira solo quando
     * aperto, per non appesantire ogni render/poll).
     */
    public bool $showArchivedContents = false;

    /**
     * Id del contenuto non assegnato per cui è in corso la conferma di
     * eliminazione definitiva — stesso schema "due passaggi" già usato da
     * resizePages()/PageCountResizer (Punto 6): il primo click mostra
     * l'avviso, un secondo click esplicito conferma davvero.
     */
    public ?int $confirmDeleteContentId = null;

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
     * Selezione multipla e azioni di massa (spec §6.4/§19): oltre alla
     * checkbox dedicata su ogni card/riga, dal 2026-09-02 (shortcut da
     * tastiera) si seleziona anche con click/Ctrl+click sull'intera card —
     * vedi clickPage()/ctrlClickPage() sotto. Quando la modalità scambio è
     * attiva, il click sulla card resta esclusivamente suo (vedi
     * page-card.blade.php/page-row.blade.php: il ramo `@if ($swapMode...)`
     * ha sempre priorità), quindi le due modalità possono restare attive
     * insieme senza conflitto solo tramite la checkbox in quel caso.
     * $selectedPageIds contiene solo id di pagine di *questa* issue
     * (verificato ad ogni azione bulk, mai fidandosi del solo stato client).
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
        $this->lastKnownPagesUpdatedAt = $issue->getRawOriginal('pages_updated_at');
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
        $this->issue->markPagesUpdated();
        // Pannello Peso (Timone\WeightPanel): IssuePageCountUpdated va solo
        // agli ALTRI client (toOthers), questo evento aggiorna anche il proprio.
        $this->dispatch('issue-page-count-changed');

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

        PageInserter::makeRoom($this->issue, $insertBefore, $countToAdd, $currentTotal);
    }

    // Il "." davanti al nome evento (qui e in tutti gli altri #[On('echo...')]
    // di questo file/ContentCreate.php) NON è un refuso: senza, Livewire
    // interpreta un nome "nudo" come alias di App\Events\{Nome} e si mette
    // in ascolto di QUEL nome sul canale — ma i nostri eventi (vedi
    // broadcastAs() su ognuno, es. App\Events\PageMoved) trasmettono con il
    // nome corto ("PageMoved", non "App\Events\PageMoved"), quindi senza il
    // punto il listener non riceve MAI l'evento: nessun errore visibile,
    // nessuna eccezione, il frame WebSocket arriva regolarmente (verificato
    // via WS reale) ma non trova nessun callback ad aspettarlo. Bug reale
    // scoperto il 2026-09-01 indagando la segnalazione "il polling non
    // funziona": né il realtime né il fallback a polling se ne accorgevano,
    // perché la connessione Echo restava "connected" a tutti gli effetti
    // (il fallback si attiva solo quando la CONNESSIONE cade, non quando un
    // singolo evento non trova ascoltatori) — un problema di nome, non di
    // trasporto. Vedi doc Livewire: "prefix the event name with a dot to
    // indicate it shouldn't be namespaced".
    #[On('echo-presence:issue.{issue.id},.IssuePageCountUpdated')]
    public function onIssuePageCountUpdated(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // pagine e total_pages aggiornati dal database.
        $this->notifyRemoteChange('Numero pagine aggiornato da un altro utente.');
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

    /**
     * Scadenza stampa dell'issue (Fase 5.1) — modificabile qui, non solo in
     * creazione (Issues\Create), perché un'issue esistente deve poter
     * spostare la propria scadenza. Azzera sempre deadline_reminder_sent_at:
     * una scadenza nuova/spostata deve poter far scattare un nuovo
     * sollecito, altrimenti la deduplica di DeadlineReminderChecker la
     * bloccherebbe per sempre pensando fosse già stata avvisata per QUESTA
     * data (il campo tiene traccia di "avvisato per la scadenza attuale",
     * non di "mai più avvisare questa issue").
     */
    public function updatePrintDeadline(?string $value): void
    {
        $this->authorize('update', $this->issue);

        $oldDeadline = $this->issue->print_deadline_at;

        if ($value === null || trim($value) === '') {
            $this->issue->update(['print_deadline_at' => null, 'deadline_reminder_sent_at' => null]);
            $this->logPrintDeadlineChange($oldDeadline, null);

            return;
        }

        try {
            $newDeadline = Carbon::parse($value);
        } catch (\Exception) {
            return;
        }

        $this->issue->update(['print_deadline_at' => $newDeadline, 'deadline_reminder_sent_at' => null]);
        $this->logPrintDeadlineChange($oldDeadline, $newDeadline);
    }

    private function logPrintDeadlineChange(?Carbon $old, ?Carbon $new): void
    {
        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Issue',
            entityId: $this->issue->id,
            action: 'issue.print_deadline_changed',
            description: 'Scadenza stampa cambiata da '.($old?->toDateString() ?? 'nessuna').' a '.($new?->toDateString() ?? 'nessuna'),
            old: ['print_deadline_at' => $old?->toDateString()],
            new: ['print_deadline_at' => $new?->toDateString()],
        );
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

        $page = Page::find($pageId);

        if ($page === null || $page->issue_id !== $this->issue->id) {
            return;
        }

        // Fase 2 (2026-10-26): una copertina non entra nella sequenza delle
        // pagine interne (e una pagina interna non può diventare
        // copertina: il drag&drop di Sortable vive solo nel contenitore
        // delle interne). Tra copertine si scambia con swapCovers().
        if ($page->isCover()) {
            $this->addError('reorder', 'Le copertine non si spostano tra le pagine interne: trascina una copertina su un\'altra copertina per scambiarle.');

            return;
        }

        // Fase 2.3: se la pagina trascinata fa parte di un gruppo
        // collegato, l'intero gruppo si sposta insieme come blocco unico —
        // stesso identico meccanismo di moveSelectedBlock() (riusato via
        // moveBlockOfPages(), mai duplicato), senza che l'utente debba
        // rifare la selezione multipla ogni volta. Guardia sulle pagine
        // bloccate diversa da blockIfLocked() sotto: qui, come in
        // moveSelectedBlock(), una sola pagina bloccata nel gruppo rifiuta
        // l'intero spostamento.
        if ($page->page_group_id !== null) {
            $groupPages = Page::where('issue_id', $this->issue->id)
                ->where('page_group_id', $page->page_group_id)
                ->get();

            if ($groupPages->count() > 1) {
                if ($groupPages->contains(fn (Page $p) => $p->isLocked())) {
                    $this->addError('locked', 'Una pagina del gruppo collegato è bloccata: sbloccala prima di spostare il gruppo.');

                    return;
                }

                $this->moveBlockOfPages(
                    $groupPages->pluck('id')->all(),
                    $pageId,
                    $toPosition,
                    $groupPages->count().' pagine spostate insieme (gruppo collegato)',
                );

                return;
            }
        }

        if ($this->blockIfLocked($page, 'spostata')) {
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
        $this->issue->markPagesUpdated();
    }

    /**
     * Spostamento di un passo con le frecce (Fase 1, bug 4, 2026-10-26) —
     * page-card/page-row. Il calcolo della destinazione è in
     * PageReorderer::nudgeTarget() (puro, testato a sé): per una pagina di
     * un gruppo collegato la destinazione è l'inizio del blocco, che
     * movePage() poi sposta intero esattamente come per un drag&drop.
     */
    public function nudgePage(int $pageId, int $delta): void
    {
        $page = Page::where('issue_id', $this->issue->id)->find($pageId);

        if ($page === null || $delta === 0 || $page->isCover()) {
            return;
        }

        $positions = $this->issue->pages()->pluck('position', 'id')->all();
        $blockPageIds = $page->page_group_id !== null
            ? Page::where('issue_id', $this->issue->id)->where('page_group_id', $page->page_group_id)->pluck('id')->all()
            : [$page->id];

        $target = PageReorderer::nudgeTarget($positions, $blockPageIds, $delta);

        if ($target !== null) {
            $this->movePage($pageId, $target);
        }
    }

    private function isCoverPage(int $pageId): bool
    {
        return Page::whereKey($pageId)->whereNotNull('cover_slot')->exists();
    }

    /**
     * Fase 2 (2026-10-26): trascinando una copertina su un'altra si
     * scambiano gli slot (es. la pubblicità passa dalla II alla III).
     * Regole in App\Support\IssueCovers::swap().
     */
    public function swapCovers(int $coverIdA, int $coverIdB): void
    {
        $this->authorize('update', $this->issue);

        $a = $this->issue->covers()->find($coverIdA);
        $b = $this->issue->covers()->find($coverIdB);

        if ($a === null || $b === null) {
            return;
        }

        $labels = [$a->label(), $b->label()];
        $error = IssueCovers::swap($a, $b);

        if ($error !== null) {
            $this->addError('covers', $error);

            return;
        }

        if ($coverIdA === $coverIdB) {
            return;
        }

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Page',
            entityId: $a->id,
            action: 'cover.swapped',
            description: "Copertine scambiate: {$labels[0]} ↔ {$labels[1]}",
            new: ['pageIds' => [$a->id, $b->id]],
        );

        $this->broadcastCoversChanged('swapped');
    }

    /** Fase 2: bandella (6 pagine di copertina invece di 4). */
    public function addBandella(): void
    {
        $this->authorize('update', $this->issue);

        $error = IssueCovers::addBandella($this->issue);

        if ($error !== null) {
            $this->addError('covers', $error);

            return;
        }

        ActivityLogger::log(issue: $this->issue, entityType: 'Issue', entityId: $this->issue->id, action: 'cover.bandella_added', description: 'Bandella aggiunta alle copertine');
        $this->broadcastCoversChanged('bandella_added');
    }

    public function removeBandella(): void
    {
        $this->authorize('update', $this->issue);

        $error = IssueCovers::removeBandella($this->issue);

        if ($error !== null) {
            $this->addError('covers', $error);

            return;
        }

        ActivityLogger::log(issue: $this->issue, entityType: 'Issue', entityId: $this->issue->id, action: 'cover.bandella_removed', description: 'Bandella tolta dalle copertine');
        $this->broadcastCoversChanged('bandella_removed');
    }

    /**
     * Avvisa gli altri utenti (Reverb + pages_updated_at per il polling) e
     * il pannello Peso di questa pagina (la bandella cambia le pagine di
     * copertina del peso), senza toast "da un altro utente" per noi stessi.
     */
    private function broadcastCoversChanged(string $action): void
    {
        broadcast(new CoversChanged(issueId: $this->issue->id, action: $action))->toOthers();
        $this->issue->markPagesUpdated();
        $this->lastKnownPagesUpdatedAt = $this->issue->fresh()->getRawOriginal('pages_updated_at');
        $this->dispatch('issue-page-count-changed');
    }

    #[On('echo-presence:issue.{issue.id},.CoversChanged')]
    public function onCoversChanged(): void
    {
        $this->notifyRemoteChange('Le copertine sono state modificate da un altro utente.');
    }

    #[On('echo-presence:issue.{issue.id},.PageMoved')]
    public function onPageMoved(): void
    {
        // Il prossimo render() ripesca già le pagine ordinate dal database:
        // qui serve solo riallineare la versione del lock ottimistico,
        // altrimenti un successivo movePage() di questo client verrebbe
        // rifiutato come conflitto anche se la sua vista è ormai aggiornata.
        $this->reorderVersion = $this->issue->fresh()->reorder_version;
        $this->notifyRemoteChange('Una pagina è stata spostata da un altro utente.');
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

        if (! $this->swapMode || $this->isCoverPage($pageId)) {
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
        $this->issue->markPagesUpdated();
    }

    #[On('echo-presence:issue.{issue.id},.PagesSwapped')]
    public function onPagesSwapped(): void
    {
        $this->reorderVersion = $this->issue->fresh()->reorder_version;
        $this->notifyRemoteChange('Due pagine sono state scambiate da un altro utente.');
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

        // Fase 2.3: se una pagina selezionata fa parte di un gruppo
        // collegato ma il resto del gruppo non è stato incluso nella
        // selezione esplicita, il gruppo si estende automaticamente —
        // scelta presa esplicitamente (vedi HANDOFF.md, "Decisioni
        // architetturali da ricordare"): mai un'operazione bloccata o
        // silenziosamente parziale che spaccherebbe un gruppo.
        $groupIdByPageId = Page::where('issue_id', $this->issue->id)->pluck('page_group_id', 'id')->all();
        $expandedIds = PageGroupResolver::expand($groupIdByPageId, $selected->pluck('id')->all());
        $groupExpanded = count($expandedIds) !== $selected->count();

        $blockPages = $groupExpanded
            ? Page::whereIn('id', $expandedIds)->where('issue_id', $this->issue->id)->get()
            : $selected;

        $lockedSelected = $blockPages->filter(fn (Page $page) => $page->isLocked());

        if ($lockedSelected->isNotEmpty()) {
            $positions = $lockedSelected->pluck('position')->sort()->values()->implode(', ');
            $this->addError('locked', $lockedSelected->count() === 1
                ? "La pagina {$positions} è bloccata: sbloccala prima di spostare l'intera selezione."
                : "Le pagine {$positions} sono bloccate: sbloccale prima di spostare l'intera selezione.");

            return;
        }

        $blockPageIds = $blockPages->pluck('id')->all();

        $this->moveBlockOfPages(
            $blockPageIds,
            $anchorPageId,
            $toPosition,
            count($blockPageIds).' pagine spostate insieme come blocco unico'
                .($groupExpanded ? ' (selezione estesa automaticamente a un gruppo collegato)' : ''),
        );
    }

    /**
     * Nucleo transazionale condiviso tra moveSelectedBlock() (selezione
     * multipla esplicita) e il redirect di movePage() quando la pagina
     * trascinata fa parte di un gruppo collegato (Fase 2.3) — stesso
     * algoritmo (PageReorderer::moveBlock()), stesso lock ottimistico su
     * reorder_version, un solo evento PagesBlockMoved per l'intera
     * operazione: evitare che le due chiamate finiscano per divergere.
     */
    private function moveBlockOfPages(array $blockPageIds, int $anchorPageId, int $toPosition, string $activityDescription): void
    {
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
            description: $activityDescription,
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
        $this->issue->markPagesUpdated();
    }

    #[On('echo-presence:issue.{issue.id},.PagesBlockMoved')]
    public function onPagesBlockMoved(): void
    {
        $this->reorderVersion = $this->issue->fresh()->reorder_version;
        $this->notifyRemoteChange('Un blocco di pagine è stato spostato da un altro utente.');
    }

    /**
     * Collega la selezione multipla corrente in un gruppo permanente (Fase
     * 2.3): da qui in avanti trascinare anche una sola pagina del gruppo
     * sposta tutte le altre insieme (vedi movePage()), senza dover rifare
     * la selezione multipla ogni volta.
     */
    public function linkSelectedPages(): void
    {
        $this->authorize('update', $this->issue);

        if (! $this->selectionMode || count($this->selectedPageIds) < 2) {
            return;
        }

        $pages = Page::whereIn('id', $this->selectedPageIds)
            ->where('issue_id', $this->issue->id)
            ->get();

        if ($pages->count() < 2) {
            return;
        }

        $result = PageGroupLinker::link($this->issue, $pages);

        if ($result === PageGroupLinker::ERROR_LOCKED) {
            $this->addError('locked', 'Una o più pagine selezionate sono bloccate: sbloccale prima di collegarle in un gruppo.');

            return;
        }

        // Scelta pragmatica: fondere un gruppo esistente con un altro
        // aggiungerebbe una casistica ("unione di due gruppi") non richiesta
        // dalla fase — va scollegato esplicitamente prima con unlinkGroup().
        if ($result === PageGroupLinker::ERROR_ALREADY_GROUPED) {
            $this->addError('link', 'Una o più pagine selezionate fanno già parte di un altro gruppo: scollegale prima di crearne uno nuovo.');

            return;
        }

        $group = $result;

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'PageGroup',
            entityId: $group->id,
            action: 'page_group.linked',
            description: $pages->count().' pagine collegate in un gruppo',
            new: ['pageIds' => $pages->pluck('id')->all()],
        );

        broadcast(new PageGroupsChanged(issueId: $this->issue->id))->toOthers();
        $this->issue->markPagesUpdated();
    }

    /**
     * Scollega l'intero gruppo a cui appartiene $pageId, non solo quella
     * pagina: un gruppo con una sola pagina rimasta non avrebbe senso, quindi
     * "scollega" dissolve sempre il gruppo per intero (bottone "🔓" sul
     * badge del gruppo, non sulla singola pagina). Per rimuovere solo
     * alcune pagine da un gruppo più grande, lasciando le altre collegate,
     * vedi unlinkSelectedFromGroup() sotto.
     */
    public function unlinkGroup(int $pageId): void
    {
        $this->authorize('update', $this->issue);

        $page = Page::find($pageId);

        if ($page === null || $page->issue_id !== $this->issue->id || $page->page_group_id === null) {
            return;
        }

        $groupId = $page->page_group_id;
        $result = PageGroupLinker::unlink($groupId);

        if ($result === PageGroupLinker::ERROR_LOCKED) {
            $this->addError('locked', 'Una pagina del gruppo è bloccata: sbloccala prima di scollegare il gruppo.');

            return;
        }

        $groupPages = $result;

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'PageGroup',
            entityId: $groupId,
            action: 'page_group.unlinked',
            description: $groupPages->count().' pagine scollegate dal gruppo',
            old: ['pageIds' => $groupPages->pluck('id')->all()],
        );

        broadcast(new PageGroupsChanged(issueId: $this->issue->id))->toOthers();
        $this->issue->markPagesUpdated();
    }

    /**
     * Scollegamento selettivo (Punto 4.6 del prompt, 2026-09-02, deciso
     * esplicitamente con l'utente): rimuove SOLO le pagine attualmente
     * selezionate dal proprio gruppo, lasciando le altre pagine di quel
     * gruppo ancora collegate tra loro — a differenza di unlinkGroup()
     * sopra (bottone 🔗 sul badge, tutto il gruppo). Riusa
     * PageGroupLinker::removeFromGroup(), generale e non specifico ai
     * gruppi creati da un contenuto PDF multi-pagina (Punto 4.5): utile
     * per QUALSIASI gruppo, anche quelli collegati manualmente dal
     * bottone "🔗 Collega selezionate"/Ctrl+Shift+G (Punto 2).
     */
    public function unlinkSelectedFromGroup(): void
    {
        $this->authorize('update', $this->issue);

        if (! $this->selectionMode || $this->selectedPageIds === []) {
            return;
        }

        $pages = Page::whereIn('id', $this->selectedPageIds)
            ->where('issue_id', $this->issue->id)
            ->get();

        $result = PageGroupLinker::removeFromGroup($pages);

        if ($result === PageGroupLinker::ERROR_LOCKED) {
            $this->addError('locked', 'Una o più pagine selezionate sono bloccate: sbloccale prima di scollegarle dal gruppo.');

            return;
        }

        $removed = $result;

        if ($removed->isEmpty()) {
            $this->addError('link', 'Nessuna delle pagine selezionate fa parte di un gruppo.');

            return;
        }

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'PageGroup',
            entityId: null,
            action: 'page_group.pages_removed',
            description: $removed->count().' pagine scollegate individualmente dal proprio gruppo, il resto di ciascun gruppo resta collegato',
            old: ['pageIds' => $removed->pluck('id')->all()],
        );

        broadcast(new PageGroupsChanged(issueId: $this->issue->id))->toOthers();
        $this->issue->markPagesUpdated();
    }

    /**
     * Shortcut da tastiera Ctrl+Shift+G (2026-09-02): collega o scollega la
     * selezione multipla corrente, riusando esattamente linkSelectedPages()
     * e unlinkGroup() — nessuna logica di collegamento/scollegamento
     * duplicata qui, solo la decisione di quale delle due chiamare.
     *
     * "Già collegate tra loro" = tutte le pagine selezionate condividono
     * lo stesso page_group_id non nullo (un gruppo può avere altre pagine
     * non selezionate: si scollega comunque l'intero gruppo, stesso
     * comportamento di unlinkGroup() ovunque nell'app — nessuna semantica
     * "parziale" diversa introdotta qui). In ogni altro caso si tenta di
     * collegarle: se il tentativo fallisce per una regola già esistente
     * (pagina bloccata, pagina già in un altro gruppo), linkSelectedPages()
     * stesso popola l'errore 'locked'/'link' già mostrato in barra — non è
     * un "fallimento silenzioso", solo riuso del meccanismo di feedback
     * esistente invece di inventarne uno nuovo per lo shortcut.
     */
    public function toggleLinkForSelection(): void
    {
        if (! $this->selectionMode || count($this->selectedPageIds) < 2) {
            $this->addError('link', 'Seleziona almeno 2 pagine per collegarle o scollegarle.');

            return;
        }

        $pages = Page::whereIn('id', $this->selectedPageIds)
            ->where('issue_id', $this->issue->id)
            ->get();

        $groupIds = $pages->pluck('page_group_id')->unique();

        if ($pages->count() === count($this->selectedPageIds) && $groupIds->count() === 1 && $groupIds->first() !== null) {
            $this->unlinkGroup($this->selectedPageIds[0]);

            return;
        }

        $this->linkSelectedPages();
    }

    #[On('echo-presence:issue.{issue.id},.PageGroupsChanged')]
    public function onPageGroupsChanged(): void
    {
        // render() ripesca già la relazione 'group' su ogni pagina — corpo
        // vuoto, stesso pattern di tutti gli altri listener broadcast.
        $this->notifyRemoteChange('Un gruppo di pagine è stato modificato da un altro utente.');
    }

    /**
     * Fallback quando i websocket non sono disponibili (2026-09-01,
     * sostituisce il precedente `pollRefresh()`): richiamato da un
     * elemento `wire:poll.1s` in grid.blade.php, montato dal client SOLO
     * mentre `$store.realtimeFallback.connected` è false — non un poll
     * sempre attivo, altrimenti ogni client pagherebbe una richiesta
     * Livewire completa al secondo anche con Reverb perfettamente
     * funzionante, vanificando il vantaggio del modello a broadcast.
     *
     * Controllo deliberatamente leggero (richiesta esplicita: "non
     * ricaricare sempre tutto"): una SELECT a singola colonna
     * (`issues.pages_updated_at`, toccata da Issue::markPagesUpdated() in
     * ogni punto del codice che già trasmette un evento broadcast — vedi
     * HANDOFF.md) confrontata come stringa grezza contro l'ultimo valore
     * visto da questo client. Se non è cambiato nulla, `skipRender()`
     * evita del tutto il costo delle query più pesanti di render()
     * (pagine con contenuti/file eager-loaded, cruscotto pubblicitario,
     * ecc.) per questo giro di poll — la richiesta HTTP/Livewire resta
     * comunque (limite noto di `wire:poll`, non evitabile lato server),
     * ma non fa nulla di più di un `SELECT` a colonna singola.
     */
    public function checkForUpdates(): void
    {
        $latest = DB::table('issues')->where('id', $this->issue->id)->value('pages_updated_at');

        if ($latest === null || $latest === $this->lastKnownPagesUpdatedAt) {
            $this->skipRender();

            return;
        }

        $this->lastKnownPagesUpdatedAt = $latest;
        // Fallback senza Reverb: il pannello Peso non ha un proprio polling.
        $this->dispatch('issue-page-count-changed');
        // Stesso motivo di onPageMoved()/pollRefresh() (versione precedente):
        // un altro utente potrebbe aver riordinato le pagine nel frattempo,
        // il lock ottimistico va riallineato o un successivo movePage() di
        // questo client verrebbe rifiutato come conflitto inesistente.
        $this->reorderVersion = $this->issue->fresh()->reorder_version;
        $this->notifyRemoteChange('Il timone è stato aggiornato da un altro utente.');
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
            $this->addError('pdfRequired', 'La '.$page->label()." non ha ancora un PDF caricato: non può essere segnata «Ok stampa».");

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
    /**
     * Bug reale trovato durante il Punto 4 (2026-09-02): questo controllo
     * guardava solo $page->files() (PageFile diretto) — con il PDF che si
     * carica ora solo su un contenuto, ogni pagina caricata col nuovo
     * flusso non avrebbe mai potuto passare a «Ok stampa», nonostante
     * avesse un'anteprima perfettamente valida. Page::previewFile() copre
     * entrambi i casi (contenuto assegnato con file, o PageFile legacy
     * non ancora migrato).
     */
    private function pageHasFile(Page $page): bool
    {
        return $page->previewFile() !== null;
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
            description: "Stato della {$page->label()} cambiato da «{$oldStatus->label()}» a «{$newStatus->label()}»",
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
        $this->issue->markPagesUpdated();
    }

    #[On('echo-presence:issue.{issue.id},.PageStatusUpdated')]
    public function onPageStatusUpdated(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // lo stato aggiornato delle pagine dal database.
        $this->notifyRemoteChange('Lo stato di una pagina è cambiato da un altro utente.');
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
                description: ucfirst($page->label())." sbloccata",
            );

            broadcast(new PageUnlocked(
                issueId: $this->issue->id,
                pageId: $page->id,
                unlockedByUserId: auth()->id(),
                unlockedByUserName: auth()->user()->name,
            ))->toOthers();
            $this->issue->markPagesUpdated();

            return;
        }

        $page->update(['locked_at' => now(), 'locked_by' => auth()->id()]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Page',
            entityId: $page->id,
            action: 'page.locked',
            description: ucfirst($page->label())." bloccata",
        );

        broadcast(new PageLocked(
            issueId: $this->issue->id,
            pageId: $page->id,
            lockedByUserId: auth()->id(),
            lockedByUserName: auth()->user()->name,
        ))->toOthers();
        $this->issue->markPagesUpdated();
    }

    #[On('echo-presence:issue.{issue.id},.PageLocked')]
    #[On('echo-presence:issue.{issue.id},.PageUnlocked')]
    public function onPageLockChanged(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // locked_at/locked_by dal database.
        $this->notifyRemoteChange('Una pagina è stata bloccata/sbloccata da un altro utente.');
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
        // Fase 2: le copertine non entrano nella selezione multipla (le sue
        // azioni — spostamento a blocco, collegamento — sono da pagine interne).
        if (! $this->selectionMode || $this->isCoverPage($pageId)) {
            return;
        }

        if (in_array($pageId, $this->selectedPageIds, true)) {
            $this->selectedPageIds = array_values(array_diff($this->selectedPageIds, [$pageId]));

            return;
        }

        $this->selectedPageIds[] = $pageId;
    }

    /**
     * Shortcut da tastiera (2026-09-02): Ctrl/Cmd+click su una card/riga,
     * fuori dalla modalità scambio (vedi wiring in page-card.blade.php/
     * page-row.blade.php). A differenza di togglePageSelection() (che è
     * no-op se la selezione multipla non è già attiva — usata dalla
     * checkbox, sempre nascosta finché non lo è), qui il primo Ctrl+click
     * la attiva automaticamente: coerente col comportamento "tipo file
     * manager" richiesto, dove Ctrl+click funziona subito senza dover
     * prima premere il pulsante "☑️ Selezione multipla" in barra.
     */
    public function ctrlClickPage(int $pageId): void
    {
        if (! $this->selectionMode) {
            $this->selectionMode = true;
            $this->selectedPageIds = [];
            $this->bulkResultMessage = null;
        }

        $this->togglePageSelection($pageId);
    }

    /**
     * Shortcut da tastiera (2026-09-02): click semplice (senza Ctrl) su una
     * card/riga, solo mentre la selezione multipla è già attiva — comportamento
     * "tipo file manager": azzera la selezione corrente e seleziona solo
     * questa pagina. Se la selezione multipla non è attiva, no-op: un click
     * semplice fuori da quella modalità non deve iniziarla da solo (a
     * differenza di Ctrl+click, vedi ctrlClickPage()), altrimenti un
     * normale click sulla card diventerebbe indistinguibile da un click
     * "a vuoto" per chi non sta usando le scorciatoie.
     */
    public function clickPage(int $pageId): void
    {
        if (! $this->selectionMode || $this->isCoverPage($pageId)) {
            return;
        }

        $this->selectedPageIds = [$pageId];
    }

    /**
     * Ctrl+A da tastiera e pulsante "Seleziona tutte" in barra: entrambi
     * riusano questo stesso metodo. Attiva anche la modalità selezione se
     * non lo è già (rilevante solo per lo shortcut: il pulsante in barra è
     * visibile solo a modalità già attiva, quindi per quel chiamante è un
     * no-op) — senza, Ctrl+A premuto a freddo selezionerebbe le pagine lato
     * server senza che checkbox/toolbar compaiano mai a mostrarlo.
     */
    public function selectAllPages(): void
    {
        $this->selectionMode = true;
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
     * Fase 4.3 — archivia/ripristina/elimina un contenuto NON ASSEGNATO.
     * Tutti e tre operano solo su contenuti che compaiono nel pannello
     * "contenuti da assegnare" (whereDoesntHave('pages') già garantito da
     * Issue::unassignedContents()): un contenuto già su una pagina non è
     * raggiungibile da questi metodi, niente guardia esplicita in più
     * necessaria oltre a filtrare la query su quella stessa relazione.
     */
    public function archiveContent(int $contentId): void
    {
        $this->authorize('update', $this->issue);

        $content = $this->issue->unassignedContents()->find($contentId);

        if ($content === null) {
            return;
        }

        $content->update(['archived_at' => now()]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $content->id,
            action: 'content.archived',
            description: "Contenuto «{$content->title}» archiviato",
        );

        $this->broadcastContentListChanged($content->id, 'archived');
    }

    public function unarchiveContent(int $contentId): void
    {
        $this->authorize('update', $this->issue);

        $content = $this->issue->contents()->whereDoesntHave('pages')->whereNotNull('archived_at')->find($contentId);

        if ($content === null) {
            return;
        }

        $content->update(['archived_at' => null]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $content->id,
            action: 'content.unarchived',
            description: "Contenuto «{$content->title}» ripristinato dall'archivio",
        );

        $this->broadcastContentListChanged($content->id, 'unarchived');
    }

    /**
     * Richiede la conferma (primo passaggio) — non elimina ancora nulla,
     * solo mostra l'avviso nel pannello. Stesso schema a due passaggi di
     * resizePages()/PageCountResizer.
     */
    public function requestDeleteContent(int $contentId): void
    {
        $this->confirmDeleteContentId = $contentId;
    }

    public function cancelDeleteContent(): void
    {
        $this->confirmDeleteContentId = null;
    }

    /**
     * Eliminazione DEFINITIVA (secondo passaggio, $confirmed=true dal
     * bottone "Conferma eliminazione" nel pannello + un confirm() JS in più
     * come rete di sicurezza, stesso schema di resizePages()). Sicura per
     * costruzione sul solo cascade footprint di un Content non assegnato:
     * nessuna riga page_content (non ha pagine, garantito dalla query),
     * quindi l'unica cascata reale è article/advertisement (cascadeOnDelete
     * dalla migrazione originale) + i ContentFile eventualmente allegati
     * (Fase 4.1, stesso cascadeOnDelete) — footprint molto più piccolo del
     * caso generale "elimina un Content già assegnato/con storico" lasciato
     * volutamente fuori scope nel Punto 6 (vedi HANDOFF.md, gestione
     * utenti/ruoli, stessa cautela lì applicata alla cancellazione utenti).
     */
    public function deleteUnassignedContent(int $contentId, bool $confirmed = false): void
    {
        $this->authorize('update', $this->issue);

        if (! $confirmed) {
            return;
        }

        $content = $this->issue->unassignedContents()->find($contentId);

        if ($content === null) {
            $this->confirmDeleteContentId = null;

            return;
        }

        $title = $content->title;
        $content->delete();

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $contentId,
            action: 'content.deleted',
            description: "Contenuto «{$title}» eliminato definitivamente",
        );

        $this->broadcastContentListChanged($contentId, 'deleted');
        $this->confirmDeleteContentId = null;
    }

    /**
     * Fase 1, bug 3 (2026-10-26): archiviare/ripristinare/eliminare un
     * contenuto non assegnato non avvisava gli altri utenti (né Reverb né
     * il polling di fallback, che guarda `pages_updated_at`). Questo
     * client si ri-renderizza da solo (l'azione è su questo componente):
     * riallineiamo anche il riferimento del polling per non mostrarci un
     * toast "da un altro utente" per una modifica nostra.
     */
    private function broadcastContentListChanged(int $contentId, string $action): void
    {
        broadcast(new ContentListChanged(issueId: $this->issue->id, contentId: $contentId, action: $action))->toOthers();
        $this->issue->markPagesUpdated();
        $this->lastKnownPagesUpdatedAt = $this->issue->fresh()->getRawOriginal('pages_updated_at');
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
            $this->addError('locked', 'La '.$page->label()." è bloccata e non può essere {$verb}: sbloccala prima di procedere.");

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

        // Pagina di partenza bloccata: stesso errore "locked" di sempre (il
        // banner dedicato in griglia); le pagine successive di un contenuto
        // multipagina le controlla ContentPlacer, con il proprio messaggio.
        if ($this->blockIfLocked($page)) {
            return;
        }

        // Bug 1 (2026-10-12): il drop occupa tante pagine consecutive quante
        // ne dichiara l'occupazione del contenuto (un PDF di 3 pagine → 3
        // pagine, ciascuna con la propria miniatura), tutto o niente — vedi
        // App\Support\ContentPlacer. Prima agganciava sempre una sola pagina.
        $result = ContentPlacer::place($content, $page);

        if (is_string($result)) {
            $this->addError('percentage', $result);

            return;
        }

        foreach ($result['pages'] as $targetPage) {
            ActivityLogger::log(
                issue: $this->issue,
                entityType: 'Content',
                entityId: $content->id,
                action: 'content.assigned',
                description: "Contenuto «{$content->displayLabel()}» assegnato alla {$targetPage->label()} ({$result['percentage']}%)",
                new: ['page_id' => $targetPage->id, 'occupied_percentage' => $result['percentage']],
            );

            broadcast(new ContentAssigned(
                issueId: $this->issue->id,
                pageId: $targetPage->id,
                contentId: $content->id,
                percentage: (float) $result['percentage'],
                assignedByUserId: auth()->id(),
                assignedByUserName: auth()->user()->name,
            ))->toOthers();
        }

        if ($result['group'] !== null) {
            ActivityLogger::log(
                issue: $this->issue,
                entityType: 'PageGroup',
                entityId: $result['group']->id,
                action: 'page_group.linked',
                description: $result['pages']->count()." pagine collegate automaticamente posizionando il contenuto multipagina «{$content->displayLabel()}»",
                new: ['pageIds' => $result['pages']->pluck('id')->all()],
            );
            broadcast(new PageGroupsChanged(issueId: $this->issue->id))->toOthers();
        }

        $this->issue->markPagesUpdated();
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

        $percentage = ContentPageAttacher::attach($content, $page);

        if ($percentage === null) {
            $this->addError($errorKey, 'Spazio insufficiente su questa pagina per assegnare il contenuto.');

            return;
        }

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $content->id,
            action: 'content.assigned',
            description: "Contenuto «{$content->displayLabel()}» assegnato alla {$page->label()} ({$percentage}%)",
            new: ['page_id' => $page->id, 'occupied_percentage' => $percentage],
        );

        broadcast(new ContentAssigned(
            issueId: $this->issue->id,
            pageId: $page->id,
            contentId: $content->id,
            percentage: (float) $percentage,
            assignedByUserId: auth()->id(),
            assignedByUserName: auth()->user()->name,
        ))->toOthers();
        $this->issue->markPagesUpdated();
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
            description: "Percentuale del contenuto sulla {$page->label()} cambiata da {$oldPercentage}% a {$percentage}%",
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
        $this->issue->markPagesUpdated();
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
        ContentPageAttacher::syncPageContentType($page);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $contentId,
            action: 'content.unassigned',
            description: "Contenuto rimosso dalla {$page->label()}",
            old: ['page_id' => $page->id],
        );

        broadcast(new ContentUnassigned(
            issueId: $this->issue->id,
            pageId: $page->id,
            contentId: $contentId,
            unassignedByUserId: auth()->id(),
            unassignedByUserName: auth()->user()->name,
        ))->toOthers();
        $this->issue->markPagesUpdated();
    }

    #[On('echo-presence:issue.{issue.id},.ContentAssigned')]
    public function onContentAssigned(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // pagine e contenuti non assegnati dal database.
        $this->notifyRemoteChange('Un contenuto è stato assegnato da un altro utente.');
    }

    #[On('echo-presence:issue.{issue.id},.ContentUnassigned')]
    public function onContentUnassigned(): void
    {
        // idem
        $this->notifyRemoteChange('Un contenuto è stato rimosso da un altro utente.');
    }

    /**
     * Fase 1, bug 3 (2026-10-26): contenuto creato/archiviato/eliminato da
     * QUESTO utente in un altro componente della stessa pagina
     * (ContentCreate, AdReservations) — evento Livewire locale, vedi
     * ContentCreate::createContentAndFinish(). Il re-render (che ripesca
     * pagine e contenuti) lo fa Livewire da solo dopo ogni listener; il
     * corpo serve solo a riallineare il polling: `pages_updated_at` è
     * appena stato toccato da noi, senza questo il prossimo
     * checkForUpdates() lo scambierebbe per una modifica di un altro
     * utente e mostrerebbe un toast falso.
     */
    #[On('content-created')]
    #[On('content-list-changed')]
    public function onLocalContentListChanged(): void
    {
        $this->lastKnownPagesUpdatedAt = $this->issue->fresh()->getRawOriginal('pages_updated_at');
    }

    #[On('echo-presence:issue.{issue.id},.ContentListChanged')]
    public function onContentListChanged(): void
    {
        $this->notifyRemoteChange('L\'elenco dei contenuti è stato aggiornato da un altro utente.');
    }

    #[On('echo-presence:issue.{issue.id},.ContentCreated')]
    public function onContentCreated(): void
    {
        // Nessuno stato locale da aggiornare: il prossimo render() ripesca
        // il nuovo contenuto tra i "non assegnati" dal database. Creato
        // da App\Livewire\Timone\ContentCreate, un componente a sé — vedi
        // "Decisioni architetturali da ricordare" in HANDOFF.md.
        $this->notifyRemoteChange('Un nuovo contenuto è stato creato da un altro utente.');
    }

    /**
     * Fase 4.1: stesso pattern di onPageFileUploaded() — corpo vuoto, il
     * prossimo render() ripesca già `files` su $unassignedContents (appena
     * eager-loaded sopra), che basta per far comparire la miniatura sul
     * chip senza refresh (Fase 1.1, riusato qui pari pari).
     */
    #[On('echo-presence:issue.{issue.id},.ContentFileUploaded')]
    public function onContentFileUploaded(): void
    {
        // idem
        $this->notifyRemoteChange('Un file è stato caricato su un contenuto da un altro utente.');
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
     * Punto 4 (2026-09-02): il caricamento file vive ora su ContentCreate,
     * un componente diverso — non può impostare direttamente la proprietà
     * $thumbnailProgressDismissed di QUESTO componente, quindi glielo
     * segnala con un dispatch() puro Livewire (non Echo: entrambi montati
     * sulla stessa pagina, nessun giro broadcast/server necessario).
     * Stesso comportamento di prima: un nuovo upload fa ricomparire il
     * banner anche se era stato chiuso in precedenza.
     */
    #[On('thumbnail-work-started')]
    public function resetThumbnailProgressDismissed(): void
    {
        $this->thumbnailProgressDismissed = false;
    }

    /**
     * Forza l'accettazione di un formato non conforme (§2.3) — un avviso,
     * non un blocco: l'utente conferma esplicitamente di aver visto la
     * non conformità e di volerla accettare per un caso limite legittimo.
     * Punto 4 del prompt (2026-09-02): opera ora su ContentFile invece
     * che PageFile, stesso identico significato — il PDF è allegato a un
     * contenuto invece che direttamente alla pagina, ma il controllo
     * formato/l'override restano concettualmente pagina-per-pagina (vedi
     * App\Jobs\GenerateContentFileThumbnail::checkFormat()).
     */
    public function confirmFormatOverride(int $contentFileId): void
    {
        $this->authorize('update', $this->issue);

        $contentFile = ContentFile::with('content.issue', 'content.pages')->findOrFail($contentFileId);

        if ($contentFile->content->issue_id !== $this->issue->id) {
            return;
        }

        // Un contenuto può essere su più pagine (o su nessuna, se non
        // ancora assegnato): basta che UNA sia bloccata per bloccare
        // anche questo — stesso principio di blockIfLocked() applicato a
        // ogni pagina coinvolta invece che a una sola, dato che qui non
        // c'è più un'unica pagina di riferimento come per il vecchio
        // PageFile.
        if ($contentFile->content->pages->contains(fn (Page $p) => $p->isLocked())) {
            $this->addError('locked', 'Una pagina su cui è assegnato questo contenuto è bloccata: sbloccala prima di accettare il formato.');

            return;
        }

        if (! $contentFile->hasUnresolvedFormatMismatch()) {
            return;
        }

        $contentFile->update([
            'format_override_confirmed_by' => auth()->id(),
            'format_override_confirmed_at' => now(),
        ]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'ContentFile',
            entityId: $contentFile->id,
            action: 'content.file_format_override_confirmed',
            description: "Formato non conforme accettato manualmente sul file del contenuto «{$contentFile->content->displayLabel()}»",
        );
    }

    /**
     * Eager load comuni a pagine interne e copertine (stessa card, stessi
     * dati mostrati) — estratti da render() con la Fase 2 per non
     * duplicarli tra le due query.
     *
     * @return array<int|string, mixed>
     */
    private function pageEagerLoads(): array
    {
        return [
            'contents.article',
            'contents.advertisement',
            // Per il badge "×N pagine" (Grid::extendToPage()) basterebbe
            // solo l'id; la posizione serve in più ad
            // App\Support\AutomaticChecks per segnalare i contenuti su
            // pagine non contigue (cover_slot per escludere le copertine).
            'contents.pages:pages.id,pages.position,pages.cover_slot',
            // Page::previewFile() legge TUTTI i file del contenuto: il pivot
            // può puntare a una specifica pagina di un PDF multi-pagina.
            'contents.files',
            // 'files' diretto sulla pagina: fallback per le pagine legacy
            // non ancora migrate (vedi Page::previewFile()).
            'files' => fn ($query) => $query->latest()->limit(1),
            'lockedBy:id,name',
            'group:id,color',
        ];
    }

    public function render(): View
    {
        $pages = $this->issue->pages()
            ->with($this->pageEagerLoads())
            ->get();

        // Fase 2 (2026-10-26): copertine caricate a parte (stessi eager
        // load: sono righe di `pages`, si mostrano con la stessa card) e
        // fuori da ogni conteggio delle pagine interne qui sotto.
        $covers = $this->issue->covers()
            ->with($this->pageEagerLoads())
            ->get()
            ->sortBy(fn (Page $cover) => $cover->cover_slot->order())
            ->values();

        $unassignedContents = $this->issue->unassignedContents()
            ->with(['article', 'advertisement', 'files' => fn ($query) => $query->limit(1)])
            ->when($this->contentSearch !== '', fn ($query) => $query->where('title', 'like', '%'.$this->contentSearch.'%'))
            ->orderBy('created_at')
            ->get();

        $reorderLogs = $this->showReorderLog
            ? $this->issue->reorderLogs()->with(['page', 'user'])->latest()->limit(50)->get()
            : null;

        // Fase 4.3 — stesso pattern "query solo se il pannello è aperto"
        // di $reorderLogs sopra: nessuno guarda gli archiviati alla
        // maggior parte dei render/poll, non vale la pena caricarli sempre.
        $archivedContents = $this->showArchivedContents
            ? $this->issue->contents()->whereDoesntHave('pages')->whereNotNull('archived_at')
                ->with(['article', 'advertisement'])->latest('archived_at')->get()
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
        // stato persistito in database: sopravvive a un refresh della
        // pagina, a differenza di una proprietà Livewire effimera.
        // $avgThumbnailSeconds passato anche ai partial page-row/page-card
        // per la stima PER SINGOLA CARD, non solo quella aggregata.
        //
        // Punto 4 (2026-09-02): Page::previewFile() può tornare un
        // PageFile (pagine legacy non ancora migrate, Punto 8) o un
        // ContentFile (nuovo percorso via contenuto) — entrambi hanno lo
        // stesso identico ventaglio di thumbnail_status, quindi il filtro
        // sotto funziona identico per entrambi senza doverli distinguere.
        $pendingThumbnailFiles = $pages->concat($covers)
            ->map(fn (Page $page) => $page->previewFile())
            ->filter(fn (PageFile|ContentFile|null $file) => $file && in_array($file->thumbnail_status, [ThumbnailStatus::Pending, ThumbnailStatus::Processing], true))
            ->values();

        // Media calcolata sulle pagine legacy (PageFile) se ce ne sono di
        // recenti, altrimenti sui contenuti (ContentFile) — scelta
        // pragmatica (CLAUDE.md, regola 3): una media "combinata" vera
        // richiederebbe interrogare e pesare due tabelle insieme per un
        // beneficio marginale (la stima resta comunque onesta, solo
        // basata su un campione via l'altra fonte quando la prima non ne
        // ha), non vale la complessità aggiuntiva.
        $avgThumbnailSeconds = ThumbnailProgressEstimator::averageProcessingSeconds($pages->pluck('id'))
            ?? ThumbnailProgressEstimator::averageProcessingSecondsForContents(
                $pages->flatMap(fn (Page $page) => $page->contents->pluck('id'))->unique()
            );

        // Fase 2 (2026-10-26): le pagine interne sono numerate dalla loro
        // posizione (1…N, le copertine non ne fanno più parte), quindi non
        // serve più un'etichetta calcolata (ex App\Support\PageNumberer).
        // Le miniature in elaborazione delle copertine contano comunque nel
        // banner di avanzamento.

        // Fase 1, bug 7 (2026-10-26): le miniature seguono le proporzioni
        // del formato reale del numero (base × altezza del pannello Peso);
        // 3/4 solo se il formato non è ancora stato impostato.
        $pageAspectRatio = $this->issue->formato_base_mm && $this->issue->formato_altezza_mm
            ? $this->issue->formato_base_mm.' / '.$this->issue->formato_altezza_mm
            : '3 / 4';

        return view('livewire.timone.grid', [
            'pages' => $pages,
            // Tutte le copertine in un'unica fascia in cima, in ordine di
            // sfoglio (I, bandella, II, III, IV) — richiesta dell'utente dopo
            // la prima versione della Fase 2, che metteva III e IV in una
            // seconda fascia dopo l'ultima pagina (lontana su un numero lungo).
            'covers' => $covers,
            'hasBandella' => $covers->contains(fn (Page $cover) => $cover->cover_slot === CoverSlot::Bandella),
            'pageAspectRatio' => $pageAspectRatio,
            'spreads' => $this->viewMode === 'doppia' ? PageSpreadBuilder::build($pages) : null,
            'unassignedContents' => $unassignedContents,
            'archivedContents' => $archivedContents,
            'reorderLogs' => $reorderLogs,
            // Solo pagine interne: le copertine hanno un conteggio a parte.
            'adLoad' => AdLoadCalculator::summarize($pages, $reservedAdvertisements),
            'adThreshold' => $this->issue->magazine->ad_threshold_percentage,
            'unassignedAdCount' => $reservedAdvertisements->count(),
            'pageCountImpact' => $pageCountImpact,
            'newTotalPagesParsed' => $newTotalPagesParsed,
            'automaticChecks' => AutomaticChecks::check($pages->concat($covers)),
            'avgThumbnailSeconds' => $avgThumbnailSeconds,
            'thumbnailProgress' => ThumbnailProgressEstimator::aggregate($pendingThumbnailFiles, $avgThumbnailSeconds),
        ]);
    }

    /**
     * Indicatore visivo leggero per un cambiamento arrivato da un altro
     * utente (richiesta esplicita: "toast o evidenziazione temporanea",
     * senza essere invasivo) — chiamato da ognuno dei listener broadcast
     * `on...()` qui sotto, mai dal fallback di polling (`pollRefresh()`):
     * un poll non è "un altro utente ha appena fatto qualcosa", è solo
     * questo client che si allinea, un toast lì sarebbe rumore falso.
     * `$this->dispatch()` qui invia un CustomEvent al browser DOPO che
     * Livewire ha già rifatto il round-trip innescato dall'evento Echo:
     * l'Alpine in grid.blade.php lo ascolta e mostra/nasconde il toast da
     * solo (nessuno stato server-side, effimero come il badge "chi sta
     * modificando cosa" già esistente).
     */
    private function notifyRemoteChange(string $message): void
    {
        $this->dispatch('remote-change', message: $message);
    }
}
