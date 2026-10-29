<?php

namespace App\Livewire\Timone;

use App\Enums\ContentType;
use App\Events\ContentCreated;
use App\Models\Content;
use App\Models\Issue;
use App\Support\BulkContentImporter;
use App\Support\ContentFormRules;
use App\Support\Permissions\TimoneAbilities;
use App\Support\Timeline\TimelineActionService;
use App\Support\Uploads\UploadLimits;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Caricamento massivo di PDF/immagini (revisione 2, Fase 3.2, 2026-10-28):
 * un contenuto "da assegnare" per file. Componente a sé (non un'altra
 * modalità di ContentCreate, già oltre le mille righe) e volutamente
 * sottile: la coda, l'avanzamento e i tentativi stanno nel browser
 * (Alpine.data('bulkUpload') in resources/js/timone-alpine.js), la
 * creazione in App\Support\BulkContentImporter.
 *
 * Perché un file alla volta: il browser carica il file con $wire.upload
 * nella proprietà $upload e poi chiama importUploaded(); solo dopo passa al
 * successivo. Un lotto unico in una sola richiesta supererebbe qualunque
 * limite del server (post_max_size, nginx) già con due PDF da 100 MB, e un
 * file rifiutato farebbe fallire tutti gli altri.
 */
class BulkUpload extends Component
{
    use WithFileUploads;

    public Issue $issue;

    /** File appena caricato dal browser, in attesa di importUploaded(). */
    public ?TemporaryUploadedFile $upload = null;

    /**
     * Motivo dell'ultimo upload temporaneo rifiutato (vedi
     * _uploadErrored()): il browser lo legge per la riga del file, perché
     * la callback di errore di $wire.upload non riceve messaggi.
     */
    public ?string $uploadError = null;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    /**
     * Crea il contenuto dal file appena caricato. Ritorna l'esito al
     * browser invece di aggiungere errori al componente: ogni file ha la
     * sua riga e il suo motivo, e un file scartato non ferma la coda.
     *
     * $batch (2026-10-28): stesso valore per tutti i file di un lotto, così
     * annulla/ripeti lo tratta come un passo solo.
     *
     * @return array{ok: bool, message?: string, title?: string, duplicateOf?: string|null}
     */
    public function importUploaded(string $type, ?string $batch = null): array
    {
        // Annulla/ripeti (2026-10-28): passo registrato da TimelineActionService.
        // $contentId: solo le righe del contenuto importato finiscono nel passo
        // (vedi TimelineDiff::onlyContents(): niente scritture dei colleghi).
        $contentId = null;

        return app(TimelineActionService::class)->run($this->issue, auth()->user(), 'content.create', function () use ($type, &$contentId) {
            // Revisione 2, Fase 4.1: il Commerciale carica solo pubblicità.
            $this->authorize('create', [Content::class, $this->issue, ContentType::tryFrom($type) ?? $type]);

            $file = $this->upload;
            $this->reset('upload', 'uploadError');
            $contentType = ContentType::tryFrom($type);

            if ($contentType === null) {
                return ['ok' => false, 'message' => 'Tipo non valido: scegli Articoli o Pubblicità.'];
            }

            if (! $file instanceof TemporaryUploadedFile) {
                return ['ok' => false, 'message' => 'Il file non è arrivato al server: riprova.'];
            }

            try {
                $result = BulkContentImporter::import($this->issue, $contentType, $file, auth()->id());
            } catch (ValidationException $e) {
                return ['ok' => false, 'message' => collect($e->errors())->flatten()->first() ?? 'File non accettato.'];
            } finally {
                // Il file è già stato copiato (o scartato): la copia temporanea
                // di Livewire resterebbe fino a 24 ore, e un lotto di PDF da
                // 100 MB riempirebbe livewire-tmp.
                $file->delete();
            }

            $content = $result->content;
            $contentId = $content->id;

            // Stessa propagazione di ContentCreate::createContentAndFinish():
            // broadcast/polling per gli altri utenti, eventi locali per Grid
            // (pannello "Contenuti da assegnare") e per il banner miniature.
            broadcast(new ContentCreated(
                issueId: $this->issue->id,
                contentId: $content->id,
                title: $content->title,
                createdByUserId: auth()->id(),
                createdByUserName: auth()->user()->name,
            ))->toOthers();
            $this->issue->markPagesUpdated();
            $this->dispatch('content-created', contentId: $content->id);
            $this->dispatch('thumbnail-work-started');

            return [
                'ok' => true,
                'title' => $content->title,
                'duplicateOf' => $result->duplicateOf,
                'occupancy' => $content->occupancy()?->label(),
            ];
        }, batchKey: $batch !== null ? mb_substr($batch, 0, 64) : null, transactional: false, scope: function () use (&$contentId) {
            return array_filter([$contentId]);
        });
    }

    /**
     * Upload temporaneo rifiutato (dimensione/tipo secondo le regole di
     * Livewire, o il server che ha respinto la richiesta): stesso
     * messaggio chiaro di ContentFormRules per il file troppo grande, voce
     * «upload_failed» nel log come in ContentCreate. Non lancia: il motivo
     * va in $uploadError e la coda passa al file successivo.
     */
    public function _uploadErrored($name, $errorsInJson, $isMultiple)
    {
        Log::warning('upload_failed', [
            'component' => static::class,
            'property' => $name,
            'issue_id' => $this->issue->id,
            'user_id' => auth()->id(),
            'validation_errors' => $errorsInJson,
        ]);

        $errors = json_decode((string) $errorsInJson, true);

        $this->uploadError = ContentFormRules::temporaryUploadMessage($errorsInJson)
            ?? (is_array($errors) ? collect($errors)->flatten()->first() : null)
            ?? 'Il file non è arrivato al server: il caricamento è stato rifiutato. Riprova; se succede di nuovo avvisa l\'amministratore (nel log c\'è una voce «upload_failed»).';

        $this->dispatch('upload:errored', name: $name)->self();
    }

    public function render(): View
    {
        return view('livewire.timone.bulk-upload', [
            'uploadLimit' => UploadLimits::effective(ContentFormRules::maxKilobytes()),
            // Revisione 2, Fase 4.1: il Commerciale carica solo pubblicità.
            'onlyAds' => ! TimoneAbilities::for(auth()->user(), $this->issue)->editsTimone(),
        ]);
    }
}
