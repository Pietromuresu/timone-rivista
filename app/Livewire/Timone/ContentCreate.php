<?php

namespace App\Livewire\Timone;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\ThumbnailStatus;
use App\Events\ContentCreated;
use App\Jobs\GenerateContentFileThumbnail;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
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

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;

        if ($this->showForm) {
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
    }

    public function save(): void
    {
        $this->authorize('update', $this->issue);

        if ($this->occupied_percentage_override === '') {
            $this->occupied_percentage_override = null;
        }

        $validated = $this->validate([
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
            // Stesso ventaglio mimes già usato da Grid::uploadPageFile()
            // (solo lì è pdf-only, perché quell'upload è sempre un PDF di
            // pagina) più le immagini, qui ammesse fin da subito (Fase 4.1).
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
        ]);

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

        if ($this->file !== null) {
            $this->storeContentFile($content, $this->file);
        }

        broadcast(new ContentCreated(
            issueId: $this->issue->id,
            contentId: $content->id,
            title: $content->title,
            createdByUserId: auth()->id(),
            createdByUserName: auth()->user()->name,
        ))->toOthers();

        $this->showForm = false;
    }

    /**
     * Fase 4.1 — fuori dalla transazione di Content::create() sopra: lo
     * storage su disco e il dispatch del job non sono operazioni
     * transazionali, stesso trattamento già riservato a
     * Grid::storeUploadedPdf() per i file di pagina.
     */
    private function storeContentFile(Content $content, TemporaryUploadedFile $file): void
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
        ]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'ContentFile',
            entityId: $contentFile->id,
            action: 'content.file_uploaded',
            description: "File «{$contentFile->original_name}» allegato al contenuto «{$content->title}»",
            new: ['content_id' => $content->id, 'original_name' => $contentFile->original_name],
        );

        GenerateContentFileThumbnail::dispatch($contentFile);
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

    #[On('echo-presence:issue.{issue.id},ContentCreated')]
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
