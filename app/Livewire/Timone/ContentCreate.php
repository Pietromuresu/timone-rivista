<?php

namespace App\Livewire\Timone;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Events\ContentCreated;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\Issue;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

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
    public Issue $issue;

    public bool $showForm = false;

    public string $type = 'articolo';

    public string $title = '';

    public ?int $section_id = null;

    // Campi articolo
    public string $author = '';

    public string $editorial_status = 'da_scrivere';

    public ?int $expected_length = null;

    // Campi pubblicità
    public string $client = '';

    public string $agency = '';

    public string $format = 'pagina_intera';

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
                'client', 'agency', 'occupied_percentage_override', 'commercial_notes',
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
        ]);

        $content = DB::transaction(function () use ($validated) {
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

        broadcast(new ContentCreated(
            issueId: $this->issue->id,
            contentId: $content->id,
            title: $content->title,
            createdByUserId: auth()->id(),
            createdByUserName: auth()->user()->name,
        ))->toOthers();

        $this->showForm = false;
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
