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
use App\Livewire\Concerns\HasOccupancyFields;
use App\Livewire\Concerns\NormalizesEmptyNumericInput;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\PageGroup;
use App\Support\ActivityLogger;
use App\Support\AdPdfPagePlan;
use App\Support\ContentCreator;
use App\Support\ContentFormRules;
use App\Support\ContentPageAttacher;
use App\Support\ContentPlacer;
use App\Support\PageGroupLinker;
use App\Support\PageInserter;
use App\Support\Permissions\TimoneAbilities;
use App\Support\PlacementChoice;
use App\Support\Timeline\TimelineActionService;
use App\Support\Twelfths;
use App\Support\Uploads\UploadEnvironmentInspector;
use App\Support\Uploads\UploadLimits;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

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
    use HasOccupancyFields;
    use NormalizesEmptyNumericInput;
    use WithFileUploads {
        _uploadErrored as livewireUploadErrored;
    }

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

    // Fase 3: occupazione come "Pagine intere" ($whole_pages) + "Frazione"
    // ($fraction_twelfths), dal trait HasOccupancyFields (condiviso con
    // ContentEdit). Per un articolo partono vuoti: nessun default
    // silenzioso; per una pubblicità li precompila il formato.

    /**
     * Pagine del PDF appena selezionato (null se nessun PDF), mostrate nel
     * form come proposta di occupazione da confermare — vedi updatedFile().
     */
    public ?int $pdfPageCount = null;

    /**
     * Fase 1, bug 8 (2026-10-26): quali pagine di un PDF pubblicitario
     * usare quando il PDF ne ha più di quante ne occupa il formato —
     * AdPdfPagePlan::ALL ("usa tutte") o ::RANGE ("solo da X a Y"). Usate
     * solo se AdPdfPagePlan::needsChoice(); `?int` con default null per i
     * campi svuotabili (vedi NormalizesEmptyNumericInput).
     */
    public string $pdfPageChoice = AdPdfPagePlan::ALL;

    public ?int $pdfPageFrom = null;

    public ?int $pdfPageTo = null;

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
     * Annulla/ripeti (2026-10-28): id del contenuto appena creato, letto da
     * TimelineActionService::run($scope) per tenere nel passo solo le
     * righe di questo contenuto (creazione senza transazione: le scritture
     * dei colleghi nel frattempo non devono finire nel mio Ctrl+Z).
     * Privata: vale solo dentro la richiesta, non va al browser.
     */
    private ?int $createdContentId = null;

    /**
     * Revisione 2, Fase 2.5 (2026-10-27): «Posizione nel timone» scelta già
     * creando il contenuto ('' = da assegnare, 'p:N', 'c:II'…:
     * App\Support\PlacementChoice). Se non c'è spazio il contenuto NON
     * viene creato. Ignorata quando il modulo è aperto da una pagina
     * («📎 Carica materiale», $anchorPageId), che ha già la sua pagina.
     */
    public string $placement = '';

    /**
     * Stesso ventaglio mimes già usato prima del Punto 4 (solo lì pdf-only,
     * perché quell'upload era sempre un PDF di pagina) più le immagini,
     * ammesse da Fase 4.1. La stessa regola (ContentFormRules::fileRule())
     * serve sia al salvataggio sia alla validazione immediata del file appena
     * scelto o trascinato (updatedFile(), bug 2): un solo punto da cambiare.
     * Il limite di dimensione è timone.upload_max_kb (revisione 2, Fase 1,
     * bug 5), letto anche da App\Support\Uploads\UploadLimits e da
     * `timone:diagnose-upload`.
     */

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
            'client', 'agency', 'preferred_position', 'commercial_notes',
            'file', 'showSectionForm', 'editingSectionId', 'sectionName',
            'whole_pages', 'fraction_twelfths', 'pdfPageCount', 'pdfPageChoice', 'pdfPageFrom', 'pdfPageTo',
        ]);
        // Revisione 2, Fase 4.1: chi lavora solo sulle pubblicità parte da lì.
        $this->type = $this->abilities()->editsTimone() ? 'articolo' : 'pubblicita';
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

        $this->validateOnly('file', ['file' => ContentFormRules::fileRule()], ContentFormRules::messages());

        try {
            $this->pdfPageCount = ContentCreator::pdfPageCount($this->file);
        } catch (ValidationException $e) {
            $this->addError('file', $e->validator->errors()->first('file'));

            return;
        }

        // Bug 8: proposta iniziale per la scelta delle pagine di una
        // pubblicità — "usa tutte", con l'intervallo già riempito sull'intero
        // PDF se l'utente passa a "solo alcune".
        $this->pdfPageChoice = AdPdfPagePlan::ALL;
        $this->pdfPageFrom = $this->pdfPageCount !== null ? 1 : null;
        $this->pdfPageTo = $this->pdfPageCount;

        $this->proposeOccupancyFromPdf();
    }

    /**
     * Se l'utente passa ad "Articolo" dopo aver già scelto un PDF, la
     * proposta di occupazione va applicata ora (updatedFile() l'aveva
     * saltata perché il tipo era "pubblicità").
     */
    public function updatedType(): void
    {
        if ($this->type === ContentType::Pubblicita->value) {
            $this->updatedFormat();

            return;
        }

        $this->proposeOccupancyFromPdf();
    }

    /**
     * Articolo con PDF e occupazione ancora vuota: propone tante pagine
     * intere quante quelle del PDF (mostrato nel form come proposta).
     */
    private function proposeOccupancyFromPdf(): void
    {
        if ($this->type !== ContentType::Articolo->value || $this->pdfPageCount === null) {
            return;
        }

        if ($this->whole_pages === null && $this->fraction_twelfths === '0') {
            $this->whole_pages = $this->pdfPageCount;
        }
    }

    public function save(): void
    {
        // Annulla/ripeti (2026-10-28): passo registrato da TimelineActionService.
        $this->createdContentId = null;

        app(TimelineActionService::class)->run($this->issue, auth()->user(), 'content.create', function () {
            // Revisione 2, Fase 4.1: il Commerciale crea solo pubblicità.
            $this->authorize('create', [Content::class, $this->issue, $this->type]);

            $validated = $this->validatedFields();
            $validated['preferred_position'] = $this->parsedPreferredPosition();

            // Stesso servizio per entrambi i percorsi (bug 1): misura del PDF e
            // vincoli occupazione/PDF verificati PRIMA di qualunque scrittura o
            // modale di conflitto. Un ValidationException qui finisce nel form.
            $pdfPageCount = $this->file !== null ? ContentCreator::pdfPageCount($this->file) : null;
            $validated += $this->chosenPdfPages($validated, $pdfPageCount);
            $occupancy = ContentCreator::resolveOccupancy($validated, $pdfPageCount);

            // Fase 2.5: posizione scelta nel modulo → verificata PRIMA di creare
            // qualunque cosa (stesso controllo dell'assegnazione manuale,
            // ContentPlacer); se non ci sta, errore e nessun salvataggio.
            $placementPage = null;

            if ($this->anchorPageId === null && $this->placement !== '') {
                $placementPage = PlacementChoice::page($this->issue, $this->placement);

                if ($placementPage === null) {
                    $this->addError('placement', 'Posizione non valida: scegli una pagina o una copertina dall\'elenco.');

                    return;
                }

                $label = trim((string) ($validated['client'] ?? '')) !== '' ? trim($validated['client']) : $validated['title'];

                if (($problem = ContentPlacer::check($occupancy, $placementPage, $label)) !== null) {
                    $this->addError('placement', $problem);

                    return;
                }
            }

            // Bug 8: le pagine del timone da occupare sono quelle SCELTE del PDF
            // (es. 2 su 3), non sempre tutte quelle del file.
            $usedPdfPages = ContentCreator::pagePlan($validated, $pdfPageCount)?->pageCount();

            $anchorPage = $this->anchorPageId !== null ? Page::find($this->anchorPageId) : null;

            // Fase 2: ancorato a una COPERTINA niente modale di conflitto (che
            // ragiona per posizioni interne consecutive): le regole copertina +
            // pagine accanto sono in ContentPlacer::place(), usato da
            // placeOnAnchorPage().
            if ($anchorPage !== null && $anchorPage->issue_id === $this->issue->id && ! $anchorPage->isCover() && ($usedPdfPages ?? 0) > 1) {
                $this->openMultipageConflict($anchorPage, $usedPdfPages, $validated, $pdfPageCount);

                return;
            }

            $this->createContentAndFinish($validated, $pdfPageCount, targetPages: null, placementPage: $placementPage);
        }, transactional: false, scope: fn () => array_filter([$this->createdContentId]));
    }

    /**
     * Fase 1, bug 8: per una pubblicità con un PDF più lungo del formato,
     * traduce la scelta del modulo in `pdf_page_from`/`pdf_page_to` per
     * ContentCreator (che resta l'unico punto in cui si decide quali pagine
     * salvare). Chiavi semplici e non un oggetto: finiscono anche nello
     * stato serializzato di Livewire della modale di conflitto.
     *
     * @param  array<string, mixed>  $validated
     * @return array{pdf_page_from?: int, pdf_page_to?: int}
     */
    private function chosenPdfPages(array $validated, ?int $pdfPageCount): array
    {
        $format = AdFormat::tryFrom((string) ($validated['format'] ?? ''));

        if ($validated['type'] !== ContentType::Pubblicita->value || $format === null || ! AdPdfPagePlan::needsChoice($format, $pdfPageCount)) {
            return [];
        }

        $plan = AdPdfPagePlan::fromChoice($pdfPageCount, $this->pdfPageChoice, $this->pdfPageFrom, $this->pdfPageTo);

        return ['pdf_page_from' => $plan->from, 'pdf_page_to' => $plan->to];
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
    private function openMultipageConflict(Page $anchorPage, int $pdfPageCount, array $validated, ?int $sourcePdfPageCount = null): void
    {
        $targetPages = $this->multipageTargetPages($anchorPage, $pdfPageCount);

        $conflicting = $targetPages
            ->reject(fn (Page $p) => $p->id === $anchorPage->id)
            ->filter(fn (Page $p) => $p->previewFile() !== null);

        $this->multipageUploadConflict = [
            'anchorPageId' => $anchorPage->id,
            'initiatingPosition' => $anchorPage->position,
            // Pagine del PDF USATE (bug 8: possono essere meno di quelle
            // del file) — è il numero di pagine del timone da occupare.
            'totalPdfPages' => $pdfPageCount,
            // Pagine del file intero, che ContentCreator rimisura come
            // riferimento per l'intervallo scelto.
            'sourcePdfPageCount' => $sourcePdfPageCount ?? $pdfPageCount,
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
        $this->createdContentId = null;

        // Annulla/ripeti (2026-10-28): passo registrato da TimelineActionService.
        app(TimelineActionService::class)->run($this->issue, auth()->user(), 'content.create', function () use ($overwriteConflicts) {
            $this->authorize('create', [Content::class, $this->issue, $this->multipageUploadConflict['validated']['type'] ?? $this->type]);

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

            $this->createContentAndFinish($conflict['validated'], $conflict['sourcePdfPageCount'] ?? $conflict['totalPdfPages'], $selectedPages, $file, $conflict['initiatingPosition']);
        }, transactional: false, scope: fn () => array_filter([$this->createdContentId]));
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

        $this->createContentAndFinish($conflict['validated'], $conflict['sourcePdfPageCount'] ?? $conflict['totalPdfPages'], $selectedPages, $file, $conflict['initiatingPosition']);
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
                'occupied_twelfths' => $content->pivot->occupied_twelfths,
                'content_file_id' => $content->pivot->content_file_id,
                'position' => $content->pivot->position,
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
            $this->addError('locked', 'La '.$page->label().' è bloccata e non può essere sovrascritta: sbloccala prima di procedere.');

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
    private function createContentAndFinish(array $validated, ?int $pdfPageCount, ?Collection $targetPages, ?TemporaryUploadedFile $file = null, ?int $initiatingPosition = null, ?Page $placementPage = null): void
    {
        $file ??= $this->file;

        $content = ContentCreator::create($this->issue, $validated, $file, auth()->id(), $pdfPageCount);
        $this->createdContentId = $content->id;

        if ($targetPages !== null) {
            $this->attachConfirmedPages($content, $targetPages, $initiatingPosition);
        } elseif ($this->anchorPageId !== null) {
            $this->placeOnAnchorPage($content);
        } elseif ($placementPage !== null) {
            // Fase 2.5: già verificato in save(); stesso servizio del drag.
            $this->placeOnPage($content, $placementPage);
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

        // Fase 1, bug 3 (2026-10-26): il broadcast sopra è `toOthers()` e
        // Grid (che mostra "Contenuti da assegnare" e le pagine) è un
        // componente DIVERSO da questo — Livewire ri-renderizza solo il
        // componente che ha ricevuto la richiesta, quindi chi creava il
        // contenuto non lo vedeva comparire finché non ricaricava la
        // pagina. Evento Livewire locale (stessa pagina, nessun giro
        // server/Reverb), ascoltato da Grid e AdReservations.
        $this->dispatch('content-created', contentId: $content->id);

        $this->showForm = false;
        $this->anchorPageId = null;
        $this->reset('file', 'pdfPageCount', 'placement');
    }

    private function placeOnAnchorPage(Content $content): void
    {
        $anchorPage = Page::find($this->anchorPageId);

        if ($anchorPage === null || $anchorPage->issue_id !== $this->issue->id) {
            return;
        }

        $this->placeOnPage($content, $anchorPage);
    }

    /**
     * Posiziona dalla pagina indicata con ContentPlacer (stesso servizio
     * dell'assegnazione manuale) e avvisa gli altri utenti.
     */
    private function placeOnPage(Content $content, Page $anchorPage): void
    {
        $result = ContentPlacer::place($content, $anchorPage);

        if (is_string($result)) {
            // Il contenuto (con i suoi file) resta comunque creato — solo
            // non posizionato, finisce tra i "contenuti da assegnare":
            // segnalato perché l'utente si aspettava un'assegnazione
            // immediata (il badge "📌" in cima al form la prometteva).
            $this->addError('anchor', $result.' Il contenuto è stato creato ma non posizionato: lo trovi tra i "Contenuti da assegnare".');

            return;
        }

        foreach ($result['pages']->values() as $index => $page) {
            broadcast(new ContentAssigned(
                issueId: $this->issue->id,
                pageId: $page->id,
                contentId: $content->id,
                twelfths: $result['twelfths'][$index],
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
        // Per posizione nell'ordine delle pagine PDF salvate (bug 8: possono
        // essere un intervallo che non parte da 1), stesso criterio di
        // ContentPlacer::place().
        $filesInPdfOrder = $content->files()->get()->keyBy('pdf_page_number')->sortKeys()->values();
        $initiatingPosition ??= $targetPages->min('position');
        $skippedForSpace = 0;
        // Fase 3: stessa distribuzione di ContentPlacer (12, 12, 6…), per
        // posizione rispetto alla pagina di partenza come i file.
        $distribution = $content->occupancy()?->distribution() ?? [];

        foreach ($targetPages->values() as $targetPage) {
            $offset = $targetPage->position - $initiatingPosition;
            $contentFile = $filesInPdfOrder->get($offset);
            $twelfths = ContentPageAttacher::attach($content, $targetPage, $contentFile?->id, $distribution[$offset] ?? null);

            if (is_string($twelfths)) {
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
                twelfths: $twelfths,
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

    /**
     * Regole condivise con ContentEdit (App\Support\ContentFormRules).
     */
    private function validatedFields(): array
    {
        return $this->validate(ContentFormRules::rules($this->issue), ContentFormRules::messages());
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

    /**
     * Fase 1, bug 9 (2026-10-26): chiamato dal JS di Livewire quando
     * l'upload temporaneo fallisce. Prima un fallimento "prima della
     * validazione" (401 firma non valida, 413 file oltre il limite di
     * nginx/PHP, 500 permessi) arrivava qui senza dettagli e diventava il
     * generico "The file failed to upload." — nessuna traccia nel log, nessun
     * indizio per chi gestisce il server. Ora: voce «upload_failed» nel log
     * con il contesto utile (indirizzo reale vs APP_URL, limiti PHP,
     * permessi dei dischi, vedi UploadEnvironmentInspector) e un messaggio
     * comprensibile per l'utente. Gli errori di validazione veri (422: tipo
     * o dimensione secondo le regole Livewire) restano quelli di Livewire,
     * già tradotti in lang/it.
     */
    public function _uploadErrored($name, $errorsInJson, $isMultiple)
    {
        Log::warning('upload_failed', [
            'component' => static::class,
            'property' => $name,
            'issue_id' => $this->issue->id,
            'user_id' => auth()->id(),
            'validation_errors' => $errorsInJson,
            'request_url' => request()->fullUrl(),
            'x_forwarded_proto' => request()->header('X-Forwarded-Proto'),
            'environment' => collect(UploadEnvironmentInspector::inspect(request(), ContentFormRules::maxKilobytes(), probePdf: false))
                ->mapWithKeys(fn (array $check) => [$check['check'] => strtoupper($check['status']).' — '.$check['detail']])
                ->all(),
        ]);

        if (($tooLarge = ContentFormRules::temporaryUploadMessage($errorsInJson)) !== null) {
            $this->dispatch('upload:errored', name: $name)->self();

            throw ValidationException::withMessages([$name => $tooLarge]);
        }

        if ($errorsInJson !== null) {
            return $this->livewireUploadErrored($name, $errorsInJson, $isMultiple);
        }

        $this->dispatch('upload:errored', name: $name)->self();

        throw ValidationException::withMessages([
            $name => 'Il file non è arrivato al server: il caricamento è stato rifiutato prima di poterlo controllare. '
                .'Riprova; se succede di nuovo avvisa l\'amministratore indicando l\'ora del tentativo '
                .'(nel log dell\'applicazione c\'è una voce «upload_failed» con la causa).',
        ]);
    }

    /**
     * Stato della scelta "quali pagine del PDF" per la vista (bug 8), null
     * se non serve: articolo, nessun PDF, formato non ancora scelto, o PDF
     * non più lungo del formato.
     *
     * @return array{pdfPageCount: int, formatSpan: int, formatLabel: string, isCover: bool, usedPages: int|null}|null
     */
    private function pdfChoiceState(): ?array
    {
        $format = AdFormat::tryFrom($this->format);

        if ($this->type !== ContentType::Pubblicita->value || $format === null || ! AdPdfPagePlan::needsChoice($format, $this->pdfPageCount)) {
            return null;
        }

        try {
            $usedPages = AdPdfPagePlan::fromChoice($this->pdfPageCount, $this->pdfPageChoice, $this->pdfPageFrom, $this->pdfPageTo)->pageCount();
        } catch (ValidationException) {
            $usedPages = null; // intervallo in corso di digitazione: nessun riepilogo
        }

        return [
            'pdfPageCount' => $this->pdfPageCount,
            'formatSpan' => AdPdfPagePlan::formatSpan($format),
            'formatLabel' => $format->label(),
            'isCover' => $format === AdFormat::CopertinaSecondaTerzaQuarta,
            'usedPages' => $usedPages,
        ];
    }

    /** Revisione 2, Fase 4.1: stesse regole delle policy, per la vista. */
    private function abilities(): TimoneAbilities
    {
        return TimoneAbilities::for(auth()->user(), $this->issue);
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
            'editsTimone' => $this->abilities()->editsTimone(),
            // Fase 2.5: solo a modulo aperto (sono una query per le pagine).
            'placementOptions' => $this->showForm ? PlacementChoice::options($this->issue) : [],
            'sections' => $this->issue->magazine->sections()->orderBy('name')->get(),
            'editorialStatuses' => EditorialStatus::cases(),
            'adFormats' => AdFormat::cases(),
            'confirmationStatuses' => AdConfirmationStatus::cases(),
            'fractionOptions' => Twelfths::FRACTIONS,
            // "Occupa 2 e 1/2 pagine" sotto i campi: stessa risoluzione del
            // salvataggio (HasOccupancyFields), non un ricalcolo nel Blade.
            'occupancyPreview' => $this->occupancyPreview(),
            // Fase 1, bug 9: limite VERO (il più stretto tra PHP, Livewire e
            // questo modulo), controllato già nel browser prima dell'invio.
            'uploadLimit' => UploadLimits::effective(ContentFormRules::maxKilobytes()),
            'pdfChoice' => $this->pdfChoiceState(),
        ]);
    }
}
