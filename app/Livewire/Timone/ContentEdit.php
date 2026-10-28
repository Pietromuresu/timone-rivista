<?php

namespace App\Livewire\Timone;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\FileFit;
use App\Events\ContentListChanged;
use App\Livewire\Concerns\HasOccupancyFields;
use App\Livewire\Concerns\NormalizesEmptyNumericInput;
use App\Models\Content;
use App\Models\Issue;
use App\Support\ContentFormRules;
use App\Support\ContentUpdater;
use App\Support\PageGrid\ThumbnailFit;
use App\Support\PageOccupancy;
use App\Support\Permissions\TimoneAbilities;
use App\Support\PlacementChoice;
use App\Support\Twelfths;
use App\Support\Uploads\UploadLimits;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Modale di modifica di un contenuto esistente (Fase 4, 2026-10-27): tutti
 * i campi della creazione — titolo, tipo, rubrica, occupazione, campi
 * dell'articolo o della pubblicità, PDF/immagine di stampa (che è anche
 * l'anteprima nel timone: nessuna miniatura separata).
 *
 * Componente a sé e non un'altra modalità di ContentCreate: la creazione ha
 * già i suoi flussi (pagina d'ancoraggio, modale di conflitto multipagina,
 * scelta delle pagine del PDF) che in modifica non esistono. Ciò che i due
 * moduli hanno in comune è condiviso davvero: regole (ContentFormRules),
 * campi dell'occupazione (HasOccupancyFields), partial dell'occupazione. La
 * scrittura è in App\Support\ContentUpdater; qui solo orchestrazione
 * (autorizzazione, broadcast, apertura/chiusura).
 *
 * Si apre con l'evento browser `open-content-edit` ({contentId}) dal ✏️ sul
 * chip dei contenuti da assegnare e sulla riga di un contenuto in pagina.
 */
class ContentEdit extends Component
{
    use HasOccupancyFields;
    use NormalizesEmptyNumericInput;
    use WithFileUploads {
        _uploadErrored as livewireUploadErrored;
    }

    public Issue $issue;

    public ?int $contentId = null;

    public bool $showModal = false;

    public string $type = 'articolo';

    public string $title = '';

    public ?int $section_id = null;

    public string $author = '';

    public string $editorial_status = 'da_scrivere';

    public ?int $expected_length = null;

    public string $client = '';

    public string $agency = '';

    public string $format = '';

    /** Stringa + parsing, come in ContentCreate (campo facoltativo svuotabile). */
    public string $preferred_position = '';

    public string $confirmation_status = 'in_trattativa';

    public string $commercial_notes = '';

    /**
     * Revisione 2, Fase 2.4: come mostrare il file nel suo rettangolo —
     * '' automatico (dalle proporzioni), 'full_page' o 'format'
     * (App\Enums\FileFit).
     */
    public string $file_fit = '';

    /**
     * Revisione 2, Fase 2.5: «Posizione nel timone» (App\Support\PlacementChoice);
     * all'apertura la prima pagina occupata. Cambiandola il contenuto si
     * sposta da lì (tutto o niente); vuota = torna «da assegnare».
     */
    public string $placement = '';

    /** Nuovo materiale di stampa: sostituisce quello attuale. */
    public ?TemporaryUploadedFile $file = null;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    #[On('open-content-edit')]
    public function open(int $contentId): void
    {
        // Revisione 2, Fase 4.1: permesso sul contenuto (il Commerciale apre
        // solo le pubblicità), non più `update` sul numero.
        $this->authorize('update', $this->findContent($contentId));

        $content = $this->findContent($contentId);
        $this->resetErrorBag();
        $this->reset('file');

        $this->contentId = $content->id;
        $this->type = $content->type->value;
        $this->title = $content->title;
        $this->section_id = $content->section_id;
        $this->author = $content->article?->author ?? '';
        $this->editorial_status = $content->article?->editorial_status?->value ?? 'da_scrivere';
        $this->expected_length = $content->article?->expected_length;
        $this->client = $content->advertisement?->client ?? '';
        $this->agency = $content->advertisement?->agency ?? '';
        $this->format = $content->advertisement?->format?->value ?? '';
        $this->preferred_position = (string) ($content->advertisement?->preferred_position ?? '');
        $this->confirmation_status = $content->advertisement?->confirmation_status?->value ?? 'in_trattativa';
        $this->commercial_notes = $content->advertisement?->commercial_notes ?? '';
        $this->file_fit = $content->file_fit?->value ?? '';
        $this->placement = PlacementChoice::valueFor($content);
        $this->fillOccupancyFields(PageOccupancy::fromTwelfths(max(1, (int) $content->occupancy_twelfths)));

        $this->showModal = true;
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->contentId = null;
        $this->reset('file');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorize('update', $this->findContent((int) $this->contentId));

        $content = $this->findContent((int) $this->contentId);

        $validated = $this->validate(ContentFormRules::rules($this->issue), ContentFormRules::messages());
        // Revisione 2, Fase 4.1: cambiando tipo serve il permesso sul tipo
        // nuovo (il Commerciale non può trasformare una pubblicità in articolo).
        $this->authorize('create', [Content::class, $this->issue, $validated['type']]);
        $trimmed = trim($this->preferred_position);
        $validated['preferred_position'] = $trimmed !== '' && ctype_digit($trimmed) ? (int) $trimmed : null;
        $validated['file_fit'] = FileFit::tryFrom($this->file_fit)?->value;

        // Fase 2.5: posizione cambiata rispetto a quella attuale?
        $moveTo = null;

        if ($this->placement !== PlacementChoice::valueFor($content)) {
            $moveTo = $this->placement === '' ? false : PlacementChoice::page($this->issue, $this->placement);

            if ($moveTo === null) {
                $this->addError('placement', 'Posizione non valida: scegli una pagina o una copertina dall\'elenco.');

                return;
            }
        }

        ContentUpdater::update(
            $content,
            $validated,
            $this->file instanceof TemporaryUploadedFile ? $this->file : null,
            auth()->id(),
            $moveTo,
        );

        // Gli altri utenti ripescano dal database (Reverb, o il polling via
        // pages_updated_at); questa pagina lo fa con l'evento locale, come
        // per la creazione (Fase 1, bug 3).
        broadcast(new ContentListChanged(issueId: $this->issue->id, contentId: $content->id, action: 'updated'))->toOthers();
        $this->issue->markPagesUpdated();
        $this->dispatch('content-list-changed');

        $this->close();
    }

    private function findContent(int $contentId): Content
    {
        $content = Content::with(['article', 'advertisement', 'files'])->findOrFail($contentId);

        abort_unless($content->issue_id === $this->issue->id, 404);

        return $content;
    }

    /**
     * File oltre il limite rifiutato dall'upload temporaneo di Livewire:
     * stesso messaggio chiaro del modulo di creazione (revisione 2, Fase 1,
     * bug 5, ContentFormRules::temporaryUploadMessage()); gli altri errori
     * restano quelli di Livewire.
     */
    public function _uploadErrored($name, $errorsInJson, $isMultiple)
    {
        if (($tooLarge = ContentFormRules::temporaryUploadMessage($errorsInJson)) !== null) {
            $this->dispatch('upload:errored', name: $name)->self();

            throw ValidationException::withMessages([$name => $tooLarge]);
        }

        return $this->livewireUploadErrored($name, $errorsInJson, $isMultiple);
    }

    public function render(): View
    {
        $content = $this->showModal && $this->contentId !== null
            ? Content::with(['files', 'pages'])->find($this->contentId)
            : null;

        return view('livewire.timone.content-edit', [
            // Revisione 2, Fase 4.1: il Commerciale non sceglie «Articolo».
            'editsTimone' => TimoneAbilities::for(auth()->user(), $this->issue)->editsTimone(),
            'content' => $content,
            'sections' => $this->issue->magazine->sections()->orderBy('name')->get(),
            'editorialStatuses' => EditorialStatus::cases(),
            'adFormats' => AdFormat::cases(),
            'confirmationStatuses' => AdConfirmationStatus::cases(),
            'fractionOptions' => Twelfths::FRACTIONS,
            'occupancyPreview' => $this->occupancyPreview(),
            'isAd' => $this->type === ContentType::Pubblicita->value,
            // Revisione 2, Fase 1, bug 5: limite vero mostrato e controllato
            // nel browser prima dell'invio, come in ContentCreate.
            'uploadLimit' => UploadLimits::effective(ContentFormRules::maxKilobytes()),
            // Fase 2.4: cosa è stato rilevato dalle proporzioni del file, per
            // la voce «Automatico» dell'override.
            'detectedFit' => ($first = $content?->files->sortBy('pdf_page_number')->first()) !== null
                ? ThumbnailFit::resolve($first->aspect_ratio, null, $this->issue->pageAspectRatio())
                : null,
            'fileFits' => FileFit::cases(),
            'placementOptions' => $content !== null ? PlacementChoice::options($this->issue) : [],
        ]);
    }
}
