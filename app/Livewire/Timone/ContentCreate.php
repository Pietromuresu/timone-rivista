<?php

namespace App\Livewire\Timone;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Enums\ThumbnailStatus;
use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\PageGroupsChanged;
use App\Jobs\GenerateContentFileThumbnail;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\PageGroup;
use App\Support\ActivityLogger;
use App\Support\ContentPageAttacher;
use App\Support\PageGroupLinker;
use App\Support\PageInserter;
use App\Support\PageRoleAssigner;
use App\Support\PdfPageMeasurer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        ]);
        $this->type = 'articolo';
        $this->editorial_status = 'da_scrivere';
        $this->format = 'pagina_intera';
        $this->confirmation_status = 'in_trattativa';
    }

    // Campi articolo
    public string $author = '';

    public string $editorial_status = 'da_scrivere';

    public ?int $expected_length = null;

    // Campi pubblicità
    public string $client = '';

    public string $agency = '';

    public string $format = 'pagina_intera';

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

    public function save(): void
    {
        $this->authorize('update', $this->issue);

        $validated = $this->validatedFields();

        if ($this->file !== null && $this->anchorPageId !== null && $this->fileIsPdf($this->file)) {
            $anchorPage = Page::find($this->anchorPageId);

            if ($anchorPage !== null && $anchorPage->issue_id === $this->issue->id) {
                // Un PDF illeggibile (pageCount() torna null, es. ext-imagick
                // assente in locale) degrada al comportamento precedente — 1
                // sola pagina — invece di bloccare l'upload, stessa
                // robustezza già richiesta per il controllo formato.
                $pdfPageCount = PdfPageMeasurer::pageCount($this->file->getRealPath()) ?? 1;

                if ($pdfPageCount > 1) {
                    $this->openMultipageConflict($anchorPage, $pdfPageCount, $validated);

                    return;
                }
            }
        }

        $this->createContentAndFinish($validated, pdfPageCount: 1, targetPages: null);
    }

    private function fileIsPdf(TemporaryUploadedFile $file): bool
    {
        return $file->getMimeType() === 'application/pdf';
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

        $this->createContentAndFinish($conflict['validated'], $conflict['totalPdfPages'], $selectedPages, $file);
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

        $this->createContentAndFinish($conflict['validated'], $conflict['totalPdfPages'], $selectedPages, $file);
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
     * Crea il Content (+ Article/Advertisement) e, se presente un file, lo
     * allega — a una sola pagina (percorso a file singolo, $targetPages
     * null) o a N pagine con un ContentFile per pagina (PDF multi-pagina
     * confermato, Punto 4/4.5), collegandole automaticamente tra loro con
     * PageGroupLinker::link() — la stessa funzione già usata dal bottone
     * "🔗 Collega selezionate"/Ctrl+Shift+G (Punto 2), non una copia.
     *
     * @param  Collection<int, Page>|null  $targetPages
     */
    private function createContentAndFinish(array $validated, int $pdfPageCount, ?Collection $targetPages, ?TemporaryUploadedFile $file = null): void
    {
        $file ??= $this->file;
        $preferredPosition = $this->parsedPreferredPosition();

        $content = DB::transaction(function () use ($validated, $preferredPosition) {
            $content = Content::create([
                'issue_id' => $this->issue->id,
                'section_id' => $validated['section_id'],
                'type' => $validated['type'],
                'title' => $validated['title'],
            ]);

            if ($validated['type'] === ContentType::Articolo->value) {
                Article::create([
                    'content_id' => $content->id,
                    'author' => $validated['author'],
                    'editorial_status' => $validated['editorial_status'],
                    'expected_length' => $validated['expected_length'],
                ]);
            } else {
                Advertisement::create([
                    'content_id' => $content->id,
                    'client' => $validated['client'],
                    'agency' => $validated['agency'],
                    'format' => $validated['format'],
                    'preferred_position' => $preferredPosition,
                    'occupied_percentage_override' => $validated['occupied_percentage_override'],
                    'confirmation_status' => $validated['confirmation_status'],
                    'commercial_notes' => $validated['commercial_notes'],
                ]);
            }

            return $content;
        });

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $content->id,
            action: 'content.created',
            description: "Contenuto «{$content->title}» creato (".($validated['type'] === 'articolo' ? 'articolo' : 'pubblicità').')',
            new: ['type' => $validated['type'], 'title' => $content->title],
        );

        if ($file !== null) {
            if ($targetPages !== null) {
                $this->attachMultipageFile($content, $file, $pdfPageCount, $targetPages);
            } else {
                $this->attachSingleFile($content, $file);
            }
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
        $this->dispatch('thumbnail-work-started');

        $this->showForm = false;
        $this->anchorPageId = null;
        $this->reset('file');
    }

    private function attachSingleFile(Content $content, TemporaryUploadedFile $file): void
    {
        $contentFile = $this->storeContentFile($content, $file, pdfPageNumber: 1, totalPdfPages: 1);

        if ($this->anchorPageId === null) {
            return;
        }

        $anchorPage = Page::find($this->anchorPageId);

        if ($anchorPage === null || $anchorPage->issue_id !== $this->issue->id || $anchorPage->isLocked()) {
            return;
        }

        $percentage = ContentPageAttacher::attach($content, $anchorPage, $contentFile->id);

        if ($percentage === null) {
            // Il contenuto (con il suo file) resta comunque creato — solo
            // non assegnato alla pagina di partenza, finisce tra i
            // "contenuti da assegnare": stesso comportamento di
            // Grid::attachContentToPage() quando lo spazio non basta,
            // segnalato qui perché l'utente si aspettava un'assegnazione
            // immediata (il badge "📌" in cima al form la prometteva).
            $this->addError('anchor', 'Spazio insufficiente sulla pagina di partenza: il contenuto è stato creato ma non assegnato lì — puoi assegnarlo a mano dal pannello "Contenuti da assegnare".');

            return;
        }

        broadcast(new ContentAssigned(
            issueId: $this->issue->id,
            pageId: $anchorPage->id,
            contentId: $content->id,
            percentage: (float) $percentage,
            assignedByUserId: auth()->id(),
            assignedByUserName: auth()->user()->name,
        ))->toOthers();
    }

    /**
     * @param  Collection<int, Page>  $targetPages
     */
    private function attachMultipageFile(Content $content, TemporaryUploadedFile $file, int $pdfPageCount, Collection $targetPages): void
    {
        $skippedForSpace = 0;

        foreach ($targetPages->values() as $index => $targetPage) {
            $contentFile = $this->storeContentFile($content, $file, pdfPageNumber: $index + 1, totalPdfPages: $pdfPageCount);
            $percentage = ContentPageAttacher::attach($content, $targetPage, $contentFile->id);

            if ($percentage === null) {
                // Caso limite raro: le pagine target di un PDF multi-pagina
                // sono quasi sempre bianche/libere (freeSpace() su una
                // pagina vuota è sempre 100), capita solo se una di esse
                // aveva già altro materiale assegnato prima di questo
                // caricamento — la relativa anteprima resta comunque
                // generata (ContentFile creato sopra), solo non collegata
                // a quella pagina specifica.
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
        // funzione già dietro "🔗 Collega selezionate"/Ctrl+Shift+G
        // (Punto 2), non una sua copia. Meno di 2 pagine (es. tutte le
        // altre erano bloccate/in conflitto-salta) non ha nulla da
        // collegare, nessun errore: un contenuto su una sola pagina è
        // comunque valido, solo senza gruppo.
        if ($targetPages->count() < 2) {
            return;
        }

        $result = PageGroupLinker::link($this->issue, $targetPages);

        // Un fallimento qui (pagina già in un gruppo/bloccata — caso limite
        // raro per pagine coinvolte in un caricamento appena confermato,
        // dato che blockIfLocked() le ha già filtrate sopra) non annulla
        // la creazione del contenuto già avvenuta: solo il collegamento
        // automatico non si applica, l'utente può comunque collegarle a
        // mano in seguito (☑️ Selezione multipla + 🔗/Ctrl+Shift+G).
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
     * Scrittura effettiva di un file su un contenuto — condivisa dal
     * percorso a file singolo e da ciascuna pagina coinvolta in un PDF
     * multi-pagina confermato (Punto 4, 2026-09-02: prima esisteva solo
     * la versione "un file, sempre pagina 1" di Fase 4.1). Ogni pagina
     * riceve una copia indipendente del file fisico, stesso principio già
     * documentato per PageFile — più semplice di un riferimento condiviso
     * tra righe ContentFile.
     */
    private function storeContentFile(Content $content, TemporaryUploadedFile $file, int $pdfPageNumber, int $totalPdfPages): ContentFile
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $storedPath = $file->storeAs("contents/{$content->id}", Str::uuid().'.'.$extension, 'local');

        $contentFile = ContentFile::create([
            'content_id' => $content->id,
            'uploaded_by' => auth()->id(),
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'thumbnail_status' => ThumbnailStatus::Pending,
            'pdf_page_number' => $pdfPageNumber,
        ]);

        $description = $totalPdfPages > 1
            ? "PDF «{$contentFile->original_name}» (pagina {$pdfPageNumber} di {$totalPdfPages}) allegato al contenuto «{$content->title}»"
            : "File «{$contentFile->original_name}» allegato al contenuto «{$content->title}»";

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'ContentFile',
            entityId: $contentFile->id,
            action: 'content.file_uploaded',
            description: $description,
            new: ['content_id' => $content->id, 'original_name' => $contentFile->original_name, 'pdf_page_number' => $pdfPageNumber],
        );

        GenerateContentFileThumbnail::dispatch($contentFile);

        return $contentFile;
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
            // Stesso ventaglio mimes già usato prima del Punto 4 (solo lì
            // pdf-only, perché quell'upload era sempre un PDF di pagina)
            // più le immagini, qui ammesse fin da subito (Fase 4.1).
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
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
        ]);
    }
}
