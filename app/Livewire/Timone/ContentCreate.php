<?php

namespace App\Livewire\Timone;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\PageGroupsChanged;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\PageGroup;
use App\Support\ActivityLogger;
use App\Support\ContentPageAttacher;
use App\Support\PageGroupLinker;
use App\Support\PageInserter;
use App\Support\PageRoleAssigner;
use App\Support\ContentCreator;
use App\Support\ContentPlacer;
use App\Support\PageOccupancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Componente Livewire a sé, non un'altra proprietà di Grid.php: prima
 * applicazione della regola "nuove funzionalità non-realtime vanno in un
 * componente dedicato" decisa dopo la valutazione SOLID su Grid.php (vedi
 * HANDOFF.md, "Decisioni architetturali da ricordare"). L'unico punto di
 * contatto con Grid è l'evento broadcast ContentCreated, ricevuto da un
 * listener a corpo vuoto — stesso pattern già usato per tutti gli altri
 * eventi realtime del progetto.
 *
 * Punto 4 del prompt (2026-09-02): un contenuto è ora l'UNICO oggetto a cui
 * si può allegare un PDF — il caricamento diretto su pagina (che viveva in
 * Grid::uploadPageFile() e affini) è stato rimosso, e l'intero meccanismo
 * di rilevazione/conflitto multi-pagina si è spostato qui, adattato per
 * scrivere ContentFile+pivot invece di PageFile. Vedi $anchorPageId per
 * come questo componente sa "per quale pagina" l'utente sta caricando
 * materiale quando aperto dal bottone su una card/riga specifica.
 */
class ContentCreate extends Component
{
    use WithFileUploads;

    public Issue $issue;

    public bool $showForm = false;

    public string $type = 'articolo';

    public string $title = '';

    public ?int $section_id = null;

    /**
     * Fase 4.2 — CRUD rubrica inline: un form leggero dentro questo stesso
     * componente (non un Livewire\Users\Index-style Index/Create/Edit a sé,
     * il prompt esplicitamente non lo richiede per un modello a due campi:
     * "non serve un pannello admin separato se il pattern del progetto non
     * lo prevede già" — CLAUDE.md, regola 3, pragmatismo). $editingSectionId
     * null = si sta creando una nuova rubrica, valorizzato = si sta
     * rinominando quella esistente.
     */
    public bool $showSectionForm = false;

    public ?int $editingSectionId = null;

    public string $sectionName = '';

    /**
     * Fase 4.1 — file opzionale allegato al contenuto in creazione. Solo
     * PDF/immagine (stesso ventaglio già supportato dalla pipeline
     * miniature esistente), non richiesto: un contenuto può restare senza
     * file, esattamente come oggi.
     */
    public ?TemporaryUploadedFile $file = null;

    /**
     * Bug 5 (2026-10-12): occupazione di pagina dell'articolo, obbligatoria
     * — valore di App\Support\PageOccupancy::options() (select, non input
     * libero). Stringa vuota = non ancora scelta: nessun default silenzioso.
     * Per la pubblicità non si usa: l'occupazione deriva dal formato.
     */
    public string $page_occupancy = '';

    /**
     * Pagine del PDF appena selezionato (null se nessun PDF), mostrate nel
     * form come proposta di occupazione da confermare — vedi updatedFile().
     */
    public ?int $pdfPageCount = null;

    /**
     * Punto 4 (2026-09-02): valorizzata quando il form si apre dal bottone
     * "carica materiale" di una card/riga specifica (vedi openForPage()),
     * null quando si apre dal bottone libero "+ Nuovo contenuto" in cima
     * alla griglia. Determina sia l'assegnazione immediata alla pagina di
     * partenza (percorso a file singolo) sia il punto di partenza per la
     * distribuzione di un PDF multi-pagina sulle pagine successive (stesso
     * significato della pagina "cliccata" nel vecchio Grid::uploadPageFile()).
     */
    public ?int $anchorPageId = null;

    /**
     * Stesso ventaglio mimes già usato prima del Punto 4 (solo lì pdf-only,
     * perché quell'upload era sempre un PDF di pagina) più le immagini,
     * ammesse da Fase 4.1. Costante perché la stessa regola serve sia al
     * salvataggio sia alla validazione immediata del file appena scelto o
     * trascinato (updatedFile(), bug 2): un solo punto da cambiare.
     */
    private const FILE_RULES = 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:20480';

    /**
     * Stato del riepilogo di conflitto per un caricamento PDF multipagina
     * (§2.1, spostato qui dal Punto 4) in attesa di conferma dall'utente —
     * null quando non c'è nessun caricamento multipagina in sospeso. Il
     * file resta comunque in $this->file (Livewire lo mantiene tra una
     * richiesta e l'altra) finché l'utente non conferma/annulla; 'validated'
     * porta con sé i campi del form già validati al primo submit, così la
     * conferma non deve rivalidarli da capo (l'utente non può modificarli
     * mentre la modale è aperta).
     *
     * @var array{anchorPageId: int, initiatingPosition: int, totalPdfPages: int, availablePages: int, targetPositions: list<int>, conflictingPositions: list<int>, validated: array}|null
     */
    public ?array $multipageUploadConflict = null;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;

        if ($this->showForm) {
            $this->resetFormFields();
            $this->anchorPageId = null;
        }
    }

    /**
     * Apre il form ancorato a una pagina specifica (Punto 4, 2026-09-02) —
     * bottone "carica materiale" su una card/riga, dispatch('open-content-
     * create-for-page', {pageId}) da page-card.blade.php/page-row.blade.php.
     * Sostituisce il vecchio trigger di upload diretto: ora anche un
     * caricamento "rapido" su una pagina passa sempre da qui, creando (o
     * riusando, per il caso multi-pagina) un Content invece di un PageFile
     * nudo.
     */
    #[On('open-content-create-for-page')]
    public function openForPage(int $pageId): void
    {
        $this->resetFormFields();
        $this->anchorPageId = $pageId;
        $this->showForm = true;
    }

    private function resetFormFields(): void
    {
        $this->reset([
            'title', 'section_id', 'author', 'expected_length',
            'client', 'agency', 'preferred_position', 'occupied_percentage_override', 'commercial_notes',
            'file', 'showSectionForm', 'editingSectionId', 'sectionName',
            'page_occupancy', 'pdfPageCount',
        ]);
        $this->type = 'articolo';
        $this->editorial_status = 'da_scrivere';
        // Bug 5: nessun formato preselezionato — il formato È l'occupazione
        // di una pubblicità, quindi va scelto esplicitamente come quella di
        // un articolo (prima partiva da "pagina intera" in silenzio).
        $this->format = '';
        $this->confirmation_status = 'in_trattativa';
    }

    // Campi articolo
    public string $author = '';

    public string $editorial_status = 'da_scrivere';

    public ?int $expected_length = null;

    // Campi pubblicità
    public string $client = '';

    public string $agency = '';

    public string $format = '';

    /**
     * Posizione di pagina preferita (Fase 3, §3) — puramente informativa,
     * indicata al momento della prenotazione prima che esista una vera
     * assegnazione. Tipizzata `string` per lo stesso motivo già
     * documentato in Grid.php per i campi numerici opzionali: un campo
     * `?int` andrebbe in errore su "" (stringa vuota, non è `null`).
     */
    public string $preferred_position = '';

    public ?string $occupied_percentage_override = null;

    public string $confirmation_status = 'in_trattativa';

    public string $commercial_notes = '';

    /**
     * Bug 5 (2026-10-12): appena l'utente sceglie (o trascina, Fase 4) un
     * file, se è un PDF ne leggiamo subito il numero di pagine e, per un
     * articolo senza occupazione ancora scelta, lo proponiamo come
     * occupazione — mostrato nel form come proposta da confermare, mai
     * scelto in silenzio al salvataggio. Un PDF illeggibile viene segnalato
     * qui, prima ancora di premere "Crea".
     */
    public function updatedFile(): void
    {
        $this->pdfPageCount = null;
        $this->resetErrorBag('file');

        if (! $this->file instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validateOnly('file', ['file' => self::FILE_RULES]);

        try {
            $this->pdfPageCount = ContentCreator::pdfPageCount($this->file);
        } catch (ValidationException $e) {
            $this->addError('file', $e->validator->errors()->first('file'));

            return;
        }

        if ($this->pdfPageCount !== null && $this->type === ContentType::Articolo->value && $this->page_occupancy === '') {
            $this->page_occupancy = PageOccupancy::from($this->pdfPageCount)->value;
        }
    }

    /**
     * Se l'utente passa ad "Articolo" dopo aver già scelto un PDF, la
     * proposta di occupazione va applicata ora (updatedFile() l'aveva
     * saltata perché il tipo era "pubblicità").
     */
    public function updatedType(): void
    {
        if ($this->type === ContentType::Articolo->value && $this->pdfPageCount !== null && $this->page_occupancy === '') {
            $this->page_occupancy = PageOccupancy::from($this->pdfPageCount)->value;
        }
    }

    public function save(): void
    {
        $this->authorize('update', $this->issue);

        $validated = $this->validatedFields();
        $validated['preferred_position'] = $this->parsedPreferredPosition();

        // Stesso servizio per entrambi i percorsi (bug 1): misura del PDF e
        // vincoli occupazione/PDF verificati PRIMA di qualunque scrittura o
        // modale di conflitto. Un ValidationException qui finisce nel form.
        $pdfPageCount = $this->file !== null ? ContentCreator::pdfPageCount($this->file) : null;
        ContentCreator::resolveOccupancy($validated, $pdfPageCount);

        $anchorPage = $this->anchorPageId !== null ? Page::find($this->anchorPageId) : null;

        if ($anchorPage !== null && $anchorPage->issue_id === $this->issue->id && ($pdfPageCount ?? 0) > 1) {
            $this->openMultipageConflict($anchorPage, $pdfPageCount, $validated);

            return;
        }

        $this->createContentAndFinish($validated, $pdfPageCount, targetPages: null);
    }

    /**
     * Le pagine del timone che il PDF andrebbe a occupare a partire da
     * $initiatingPage — stessa logica di Grid::multipageTargetPages()
     * (spostata qui, non duplicata: quel metodo non esiste più).
     */
    private function multipageTargetPages(Page $initiatingPage, int $count): Collection
    {
        return $this->issue->pages()
            ->with(['contents.files', 'files' => fn ($q) => $q->latest()->limit(1)])
            ->where('position', '>=', $initiatingPage->position)
            ->where('position', '<', $initiatingPage->position + $count)
            ->orderBy('position')
            ->get();
    }

    /**
     * "Conflitto" = la pagina target ha già un'anteprima visibile (via
     * Page::previewFile(), che copre sia le pagine legacy con un PageFile
     * diretto sia quelle già su un contenuto) — stesso significato di
     * "$p->files->isNotEmpty()" nel vecchio Grid::uploadPageFile(), esteso
     * al nuovo concetto di anteprima.
     */
    private function openMultipageConflict(Page $anchorPage, int $pdfPageCount, array $validated): void
    {
        $targetPages = $this->multipageTargetPages($anchorPage, $pdfPageCount);

        $conflicting = $targetPages
            ->reject(fn (Page $p) => $p->id === $anchorPage->id)
            ->filter(fn (Page $p) => $p->previewFile() !== null);

        $this->multipageUploadConflict = [
            'anchorPageId' => $anchorPage->id,
            'initiatingPosition' => $anchorPage->position,
            'totalPdfPages' => $pdfPageCount,
            'availablePages' => $targetPages->count(),
            'targetPositions' => $targetPages->pluck('position')->all(),
            'conflictingPositions' => $conflicting->pluck('position')->values()->all(),
            'validated' => $validated,
        ];

        $this->dispatch('open-modal', 'multipage-upload-conflict');
    }

    /**
     * Annulla un caricamento multipagina in attesa di conferma: nessuna
     * scrittura era comunque ancora avvenuta, quindi basta scartare lo
     * stato e il file temporaneo.
     */
    public function cancelMultipageUpload(): void
    {
        $this->multipageUploadConflict = null;
        $this->dispatch('close-modal', 'multipage-upload-conflict');
        $this->reset('file');
    }

    /**
     * Applica un caricamento multipagina confermato dall'utente (§2.1,
     * spostato qui dal Punto 4). $overwriteConflicts distingue le due
     * scelte esplicite offerte nel riepilogo: `false` = "salta le pagine
     * in conflitto", `true` = "sovrascrivi" (ogni pagina dell'intervallo
     * riceve comunque il PDF). Una pagina bloccata nell'intervallo viene
     * sempre saltata (mai un'eccezione, stesso principio di
     * bulkChangeStatus() in Grid.php: il resto del batch va comunque a
     * buon fine).
     */
    public function confirmMultipageUpload(bool $overwriteConflicts): void
    {
        $this->authorize('update', $this->issue);

        $conflict = $this->multipageUploadConflict;

        if ($conflict === null) {
            return;
        }

        $file = $this->file;
        $this->multipageUploadConflict = null;
        $this->dispatch('close-modal', 'multipage-upload-conflict');

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $targetPages = $this->issue->pages()
            ->whereIn('position', $conflict['targetPositions'])
            ->orderBy('position')
            ->get();

        $selectedPages = collect();

        foreach ($targetPages as $targetPage) {
            $isConflicting = in_array($targetPage->position, $conflict['conflictingPositions'], true);

            if ($isConflicting && ! $overwriteConflicts) {
                continue;
            }

            if ($this->blockIfLocked($targetPage)) {
                continue;
            }

            $selectedPages->push($targetPage);
        }

        if ($selectedPages->isEmpty()) {
            $this->reset('file');

            return;
        }

        $this->createContentAndFinish($conflict['validated'], $conflict['totalPdfPages'], $selectedPages, $file, $conflict['initiatingPosition']);
    }

    /**
     * Terza scelta del riepilogo conflitto ("sposta le pagine esistenti
     * dopo") — spostata qui dal Punto 4, stessa meccanica di
     * Grid::confirmMultipageUploadWithShift() (App\Support\PageInserter,
     * estratto apposta perché serve identico sia al pannello "pagine
     * totali" di Grid sia qui).
     */
    public function confirmMultipageUploadWithShift(): void
    {
        $this->authorize('update', $this->issue);

        $conflict = $this->multipageUploadConflict;

        if ($conflict === null) {
            return;
        }

        $file = $this->file;
        $this->multipageUploadConflict = null;
        $this->dispatch('close-modal', 'multipage-upload-conflict');

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $conflictingPositions = $conflict['conflictingPositions'];
        $shiftCount = count($conflictingPositions);

        if ($shiftCount > 0) {
            $currentTotal = $this->issue->total_pages;
            $insertBefore = max($conflict['targetPositions']) + 1;

            DB::transaction(function () use ($insertBefore, $shiftCount, $currentTotal, $conflictingPositions) {
                PageInserter::makeRoom($this->issue, $insertBefore, $shiftCount, $currentTotal);
                $this->issue->update(['total_pages' => $currentTotal + $shiftCount]);

                $displacedPages = $this->issue->pages()->whereIn('position', $conflictingPositions)->orderBy('position')->get();
                $newSlots = $this->issue->pages()->where('position', '>=', $insertBefore)->orderBy('position')->limit($shiftCount)->get();

                foreach ($displacedPages->values() as $index => $oldPage) {
                    if ($oldPage->isLocked()) {
                        continue;
                    }

                    $this->relocatePageContent($oldPage, $newSlots[$index]);
                }
            });

            PageRoleAssigner::syncCoverRoles($this->issue);

            ActivityLogger::log(
                issue: $this->issue,
                entityType: 'Issue',
                entityId: $this->issue->id,
                action: 'issue.pages_shifted_for_upload',
                description: "{$shiftCount} pagine in conflitto traslate dopo la posizione ".max($conflict['targetPositions']).' per fare spazio a un caricamento PDF multipagina su un contenuto',
                new: ['shiftedPositions' => $conflictingPositions, 'insertedFrom' => $insertBefore],
            );
        }

        $targetPages = $this->issue->pages()
            ->whereIn('position', $conflict['targetPositions'])
            ->orderBy('position')
            ->get();

        $selectedPages = collect();

        foreach ($targetPages as $targetPage) {
            if ($this->blockIfLocked($targetPage)) {
                continue;
            }

            $selectedPages->push($targetPage);
        }

        if ($selectedPages->isEmpty()) {
            $this->reset('file');

            return;
        }

        $this->createContentAndFinish($conflict['validated'], $conflict['totalPdfPages'], $selectedPages, $file, $conflict['initiatingPosition']);
    }

    /**
     * Trasferisce il "contenuto" editoriale di una pagina su un'altra —
     * spostata qui identica da Grid::relocatePageContent() (Punto 4): il
     * pivot ora porta con sé anche content_file_id, e un eventuale
     * PageFile legacy diretto (pagine non ancora migrate, Punto 8) viene
     * comunque spostato — "fare spazio" non deve mai far perdere
     * materiale già presente sulla pagina spostata, indipendentemente da
     * quale dei due meccanismi lo possiede.
     */
    private function relocatePageContent(Page $from, Page $to): void
    {
        $to->update([
            'content_type' => $from->content_type,
            'status' => $from->status,
            'notes' => $from->notes,
        ]);

        foreach ($from->contents as $content) {
            $to->contents()->attach($content->id, [
                'occupied_percentage' => $content->pivot->occupied_percentage,
                'content_file_id' => $content->pivot->content_file_id,
            ]);
        }
        $from->contents()->detach();

        PageFile::where('page_id', $from->id)->update(['page_id' => $to->id]);

        $from->update([
            'content_type' => PageContentType::Bianca->value,
            'status' => PageStatus::DaAssegnare->value,
            'notes' => null,
        ]);
    }

    private function blockIfLocked(Page $page): bool
    {
        if ($page->isLocked()) {
            $this->addError('locked', "La pagina {$page->position} è bloccata e non può essere sovrascritta: sbloccala prima di procedere.");

            return true;
        }

        return false;
    }

    /**
     * Crea il contenuto tramite App\Support\ContentCreator (unico punto di
     * creazione, bug 1 — un ContentFile per pagina del PDF in entrambi i
     * percorsi) e poi lo posiziona: sulle pagine confermate nella modale
     * di conflitto (PDF multipagina ancorato), oppure con ContentPlacer a
     * partire dalla pagina d'ancoraggio — la stessa logica del drop dal
     * pannello, non una sua copia. Senza ancoraggio resta tra i "contenuti
     * da assegnare", invariato.
     *
     * @param  Collection<int, Page>|null  $targetPages
     */
    private function createContentAndFinish(array $validated, ?int $pdfPageCount, ?Collection $targetPages, ?TemporaryUploadedFile $file = null, ?int $initiatingPosition = null): void
    {
        $file ??= $this->file;

        $content = ContentCreator::create($this->issue, $validated, $file, auth()->id(), $pdfPageCount);

        if ($targetPages !== null) {
            $this->attachConfirmedPages($content, $targetPages, $initiatingPosition);
        } elseif ($this->anchorPageId !== null) {
            $this->placeOnAnchorPage($content);
        }

        broadcast(new ContentCreated(
            issueId: $this->issue->id,
            contentId: $content->id,
            title: $content->title,
            createdByUserId: auth()->id(),
            createdByUserName: auth()->user()->name,
        ))->toOthers();
        $this->issue->markPagesUpdated();
        // Un nuovo upload crea nuovo lavoro reale da tracciare — se il
        // banner di avanzamento miniature (su Grid, un componente
        // diverso) era stato chiuso in precedenza, ricompare per questo.
        // dispatch() puro Livewire (non Echo): entrambi i componenti sono
        // montati sulla stessa pagina, non serve un giro broadcast/server.
        if ($file !== null) {
            $this->dispatch('thumbnail-work-started');
        }

        $this->showForm = false;
        $this->anchorPageId = null;
        $this->reset('file', 'pdfPageCount');
    }

    private function placeOnAnchorPage(Content $content): void
    {
        $anchorPage = Page::find($this->anchorPageId);

        if ($anchorPage === null || $anchorPage->issue_id !== $this->issue->id) {
            return;
        }

        $result = ContentPlacer::place($content, $anchorPage);

        if (is_string($result)) {
            // Il contenuto (con i suoi file) resta comunque creato — solo
            // non posizionato, finisce tra i "contenuti da assegnare":
            // segnalato perché l'utente si aspettava un'assegnazione
            // immediata (il badge "📌" in cima al form la prometteva).
            $this->addError('anchor', $result.' Il contenuto è stato creato ma non posizionato: lo trovi tra i "Contenuti da assegnare".');

            return;
        }

        foreach ($result['pages'] as $page) {
            broadcast(new ContentAssigned(
                issueId: $this->issue->id,
                pageId: $page->id,
                contentId: $content->id,
                percentage: (float) $result['percentage'],
                assignedByUserId: auth()->id(),
                assignedByUserName: auth()->user()->name,
            ))->toOthers();
        }

        if ($result['group'] !== null) {
            broadcast(new PageGroupsChanged(issueId: $this->issue->id))->toOthers();
        }
    }

    /**
     * Pagine scelte nella modale di conflitto di un PDF multipagina
     * ancorato (salta/sovrascrivi/sposta): ognuna riceve il ContentFile
     * della pagina sorgente che le corrisponde per POSIZIONE rispetto alla
     * pagina di partenza — una pagina saltata non fa "scalare" le
     * successive sulla pagina PDF sbagliata (prima l'indice era quello
     * nella lista delle sole pagine selezionate).
     *
     * @param  Collection<int, Page>  $targetPages
     */
    private function attachConfirmedPages(Content $content, Collection $targetPages, ?int $initiatingPosition): void
    {
        $filesByPdfPage = $content->files()->get()->keyBy('pdf_page_number');
        $initiatingPosition ??= $targetPages->min('position');
        $skippedForSpace = 0;

        foreach ($targetPages->values() as $targetPage) {
            $contentFile = $filesByPdfPage->get($targetPage->position - $initiatingPosition + 1);
            $percentage = ContentPageAttacher::attach($content, $targetPage, $contentFile?->id);

            if ($percentage === null) {
                // Caso limite raro: una pagina dell'intervallo aveva già
                // altro materiale (scelta "sovrascrivi") — l'anteprima
                // esiste comunque, solo non collegata a quella pagina.
                $skippedForSpace++;

                continue;
            }

            broadcast(new ContentAssigned(
                issueId: $this->issue->id,
                pageId: $targetPage->id,
                contentId: $content->id,
                percentage: (float) $percentage,
                assignedByUserId: auth()->id(),
                assignedByUserName: auth()->user()->name,
            ))->toOthers();
        }

        if ($skippedForSpace > 0) {
            $this->addError('anchor', $skippedForSpace === 1
                ? '1 pagina è stata saltata per spazio insufficiente: la sua anteprima esiste comunque, ma non è collegata a quella pagina.'
                : "{$skippedForSpace} pagine sono state saltate per spazio insufficiente: le loro anteprime esistono comunque, ma non sono collegate a quelle pagine.");
        }

        // Punto 4.5: collegamento automatico delle pagine derivate da un
        // PDF multi-pagina — riusa PageGroupLinker::link(), la stessa
        // funzione già dietro "🔗 Collega selezionate"/Ctrl+Shift+G.
        // Meno di 2 pagine non ha nulla da collegare; un fallimento (pagina
        // già in un gruppo) non annulla la creazione già avvenuta.
        if ($targetPages->count() < 2) {
            return;
        }

        $result = PageGroupLinker::link($this->issue, $targetPages);

        if (! $result instanceof PageGroup) {
            return;
        }

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'PageGroup',
            entityId: $result->id,
            action: 'page_group.linked',
            description: $targetPages->count().' pagine collegate automaticamente da un PDF multi-pagina caricato sul contenuto «'.$content->title.'»',
            new: ['pageIds' => $targetPages->pluck('id')->all()],
        );

        broadcast(new PageGroupsChanged(issueId: $this->issue->id))->toOthers();
    }

    private function validatedFields(): array
    {
        if ($this->occupied_percentage_override === '') {
            $this->occupied_percentage_override = null;
        }

        return $this->validate([
            'type' => 'required|in:articolo,pubblicita',
            'title' => 'required|string|max:255',
            'section_id' => 'nullable|exists:sections,id',
            'author' => 'nullable|string|max:180',
            'editorial_status' => 'required|in:'.implode(',', array_column(EditorialStatus::cases(), 'value')),
            'expected_length' => 'nullable|integer|min:1',
            'client' => 'required_if:type,pubblicita|nullable|string|max:180',
            'agency' => 'nullable|string|max:180',
            'format' => 'required_if:type,pubblicita|nullable|in:'.implode(',', array_column(AdFormat::cases(), 'value')),
            'occupied_percentage_override' => 'nullable|numeric|min:0.1|max:100',
            'confirmation_status' => 'required_if:type,pubblicita|nullable|in:'.implode(',', array_column(AdConfirmationStatus::cases(), 'value')),
            'commercial_notes' => 'nullable|string|max:2000',
            // Bug 5: obbligatoria per un articolo e solo tra i valori della
            // select (PageOccupancy::options()); per la pubblicità la deriva
            // il formato, già obbligatorio sopra. Il secondo livello del
            // vincolo è in ContentCreator::resolveOccupancy().
            'page_occupancy' => ['required_if:type,articolo', 'nullable', Rule::in(array_keys(PageOccupancy::options($this->issue->total_pages)))],
            'file' => self::FILE_RULES,
        ], [
            'page_occupancy.required_if' => "Scegli l'occupazione di pagina dell'articolo.",
            'page_occupancy.in' => 'Occupazione non valida: scegline una dall\'elenco.',
            'format.required_if' => 'Scegli il formato della pubblicità.',
        ]);
    }

    /**
     * Fase 4.2 — CRUD rubrica. Autorizzazione riusata identica a quella di
     * save() sopra (update sull'Issue): creare/rinominare una rubrica è
     * un'azione redazionale sullo stesso numero, non serve una policy
     * dedicata per un modello a due campi senza altre regole di accesso.
     */
    public function startCreateSection(): void
    {
        $this->editingSectionId = null;
        $this->sectionName = '';
        $this->showSectionForm = true;
    }

    public function startEditSection(int $sectionId): void
    {
        $section = $this->issue->magazine->sections()->find($sectionId);

        if ($section === null) {
            return;
        }

        $this->editingSectionId = $section->id;
        $this->sectionName = $section->name;
        $this->showSectionForm = true;
    }

    public function cancelSectionForm(): void
    {
        $this->showSectionForm = false;
        $this->editingSectionId = null;
        $this->sectionName = '';
    }

    public function saveSection(): void
    {
        $this->authorize('update', $this->issue);

        $magazine = $this->issue->magazine;

        $validated = $this->validate([
            'sectionName' => [
                'required', 'string', 'max:255',
                Rule::unique('sections', 'name')
                    ->where('magazine_id', $magazine->id)
                    ->ignore($this->editingSectionId),
            ],
        ], [], ['sectionName' => 'nome rubrica']);

        if ($this->editingSectionId !== null) {
            $section = $magazine->sections()->findOrFail($this->editingSectionId);
            $section->update(['name' => $validated['sectionName']]);
        } else {
            $section = $magazine->sections()->create(['name' => $validated['sectionName']]);
        }

        // Selezionata subito nel form principale: chi crea/rinomina una
        // rubrica lo sta facendo per usarla nel contenuto che sta per
        // creare, non solo per gestirla in astratto.
        $this->section_id = $section->id;

        $this->cancelSectionForm();
    }

    /**
     * Normalizza il campo testuale $preferred_position in un intero
     * positivo valido, o null — stesso pattern già stabilito in
     * Grid::parsedNewTotalPages() per i campi numerici opzionali "in corso
     * di digitazione". Campo puramente informativo (§3): un valore non
     * numerico viene semplicemente ignorato (nessuna preferenza), non
     * vale la pena di un errore di validazione dedicato per questo.
     */
    private function parsedPreferredPosition(): ?int
    {
        $trimmed = trim($this->preferred_position);

        return $trimmed !== '' && ctype_digit($trimmed) ? (int) $trimmed : null;
    }

    #[On('echo-presence:issue.{issue.id},.ContentCreated')]
    public function onContentCreated(): void
    {
        // Nessuno stato proprio da aggiornare qui: è Grid.php (il pannello
        // "contenuti da assegnare") a dover ripescare i contenuti dal
        // database — ha il suo listener gemello a corpo vuoto.
    }

    public function render(): View
    {
        return view('livewire.timone.content-create', [
            'sections' => $this->issue->magazine->sections()->orderBy('name')->get(),
            'editorialStatuses' => EditorialStatus::cases(),
            'adFormats' => AdFormat::cases(),
            'confirmationStatuses' => AdConfirmationStatus::cases(),
            'occupancyOptions' => PageOccupancy::options($this->issue->total_pages),
        ]);
    }
}
