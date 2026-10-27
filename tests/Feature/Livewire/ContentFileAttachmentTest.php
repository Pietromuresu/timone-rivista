<?php

use App\Enums\PageContentType;
use App\Enums\ThumbnailStatus;
use App\Enums\UserRole;
use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\ContentFileUploaded;
use App\Jobs\GenerateContentFileThumbnail;
use App\Livewire\Timone\ContentCreate;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.
// A differenza di TimoneGridMultipageUploadTest.php, i test sulle IMMAGINI
// qui sotto NON richiedono ext-imagick: GenerateContentFileThumbnail copia
// semplicemente il file per un'immagine (nessuna rasterizzazione Ghostscript
// necessaria, vedi il job), quindi girano per davvero anche in locale.

beforeEach(function () {
    // save() broadcasta ContentCreated (->toOthers()) — stesso Event::fake()
    // già usato da ContentCreateTest.php, altrimenti tenta una vera chiamata
    // a Reverb (irraggiungibile fuori da Docker) e fallisce il test.
    // ContentFileUploaded: il job di miniatura per un'immagine gira per
    // davvero qui (nessun Queue::fake(), a differenza dei test PDF sotto),
    // quindi arriva fino al broadcast finale — va fakato per lo stesso
    // motivo. ContentAssigned: broadcastato quando il file (immagine, non
    // richiede imagick) si assegna subito alla pagina di ancoraggio — vedi
    // i test "anchored" sotto (Punto 4, 2026-09-02).
    Event::fake([ContentCreated::class, ContentFileUploaded::class, ContentAssigned::class]);
});

test('attaching an image to a new content generates a ready thumbnail synchronously, no imagick needed', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo con foto allegata')->set('whole_pages', 1)
        ->set('file', UploadedFile::fake()->image('foto.jpg', 100, 100))
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Articolo con foto allegata')->first();
    $contentFile = $content->files->first();

    expect($contentFile)->not->toBeNull()
        ->and($contentFile->mime_type)->toStartWith('image/')
        ->and($contentFile->thumbnail_status)->toBe(ThumbnailStatus::Ready)
        ->and($contentFile->thumbnail_path)->not->toBeNull();

    Storage::disk('local')->assertExists($contentFile->thumbnail_path);
});

// Punto 4 del prompt (2026-09-02): il bottone "📎 Carica materiale" di una
// card/riga apre questo stesso componente ancorato a quella pagina
// (ContentCreate::openForPage()) — il materiale caricato si assegna
// SUBITO alla pagina di partenza, stessa immediatezza percepita del
// vecchio caricamento diretto su pagina (rimosso), ma passando sempre da
// un Content. Un'immagine non richiede imagick, gira per davvero qui.
test('opening the form anchored to a page and uploading an image attaches the content to that page immediately', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('openForPage', $page->id)
        ->assertSet('anchorPageId', $page->id)
        ->assertSet('showForm', true)
        ->set('type', 'articolo')
        ->set('title', 'Foto ancorata alla pagina')->set('whole_pages', 1)
        ->set('file', UploadedFile::fake()->image('foto.jpg', 100, 100))
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Foto ancorata alla pagina')->first();
    $contentFile = $content->files->first();

    expect($page->contents()->where('content_id', $content->id)->exists())->toBeTrue()
        ->and($page->contents()->first()->pivot->content_file_id)->toBe($contentFile->id)
        ->and($page->fresh()->content_type)->toBe(PageContentType::Editoriale)
        ->and($page->fresh()->previewFile()->id)->toBe($contentFile->id);

    Event::assertDispatched(ContentAssigned::class, fn (ContentAssigned $e) => $e->pageId === $page->id && $e->contentId === $content->id);
});

test('a content created from the free-standing "+ Nuovo contenuto" form (no anchor) is not auto-assigned to any page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->assertSet('anchorPageId', null)
        ->set('type', 'articolo')
        ->set('title', 'Foto non ancorata')->set('whole_pages', 1)
        ->set('file', UploadedFile::fake()->image('foto.jpg', 100, 100))
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Foto non ancorata')->first();

    expect($content->isAssigned())->toBeFalse();

    Event::assertNotDispatched(ContentAssigned::class);
});

test('anchored upload reports insufficient space instead of failing silently, the content is still created', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    // La pagina è già piena (100%): non c'è spazio per il nuovo contenuto.
    $existingContent = Content::factory()->create(['issue_id' => $issue->id]);
    $page->contents()->attach($existingContent->id, ['occupied_twelfths' => 12]);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('openForPage', $page->id)
        ->set('type', 'articolo')
        ->set('title', 'Foto senza spazio')->set('whole_pages', 1)
        ->set('file', UploadedFile::fake()->image('foto.jpg', 100, 100))
        ->call('save')
        ->assertHasErrors('anchor');

    $content = Content::where('title', 'Foto senza spazio')->first();

    expect($content)->not->toBeNull()
        ->and($content->files->first())->not->toBeNull()
        ->and($content->isAssigned())->toBeFalse();
});

test('attaching a real pdf actually converts its first page to a ready thumbnail', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo con PDF reale')->set('whole_pages', 1)
        ->set('file', UploadedFile::fake()->createWithContent('bozza.pdf', minimalValidPdfBytes()))
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Articolo con PDF reale')->first();
    $contentFile = $content->files->first();

    expect($contentFile->thumbnail_status)->toBe(ThumbnailStatus::Ready)
        ->and($contentFile->thumbnail_path)->toEndWith('.png');

    Storage::disk('local')->assertExists($contentFile->thumbnail_path);
});

test('a content can be created with no file attached, exactly as before this phase', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo senza allegati')->set('whole_pages', 1)
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Articolo senza allegati')->first();

    expect($content->files)->toBeEmpty();
});

test('attaching a pdf dispatches the thumbnail generation job for the content file, not the page pipeline', function () {
    // Bug 1 (2026-10-12): il numero di pagine di un PDF è ora sempre letto
    // (App\Support\ContentCreator) e un PDF illeggibile viene rifiutato
    // invece di essere trattato come "1 pagina" — serve un PDF vero, e
    // quindi ext-imagick (solo in Docker).
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    Queue::fake([GenerateContentFileThumbnail::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo con PDF allegato')->set('whole_pages', 1)
        ->set('file', UploadedFile::fake()->createWithContent('bozza.pdf', minimalValidPdfBytes()))
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Articolo con PDF allegato')->first();
    $contentFile = $content->files->first();

    expect($contentFile)->not->toBeNull()
        ->and($contentFile->mime_type)->toBe('application/pdf')
        ->and($contentFile->thumbnail_status)->toBe(ThumbnailStatus::Pending);

    Queue::assertPushed(GenerateContentFileThumbnail::class, fn ($job) => $job->contentFile->id === $contentFile->id);
});

test('a file with a disallowed mime type is rejected at upload time, before save is even attempted', function () {
    // Rifiutato dalla config globale temporary_file_upload.rules
    // (config/livewire.php) durante lo stesso ->set(), non dalla
    // validazione di save() — che infatti a questo punto vedrebbe $file
    // ancora null (upload mai completato) e lo lascerebbe passare come
    // "nessun file", nullable. Stessa UX di un vero browser: l'errore
    // compare subito alla selezione del file, prima ancora di premere
    // "Crea contenuto" — verificato qui subito dopo il ->set(), non dopo
    // save().
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo con file non ammesso')->set('whole_pages', 1)
        ->set('file', UploadedFile::fake()->create('note.txt', 10, 'text/plain'))
        ->assertHasErrors('file');

    expect(Content::where('title', 'Articolo con file non ammesso')->exists())->toBeFalse();
});

test('each content on a mixed page keeps its own file, resolving the page-scoped PageFile limitation', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')->set('type', 'articolo')->set('title', 'Articolo A')->set('whole_pages', 1)
        ->set('file', UploadedFile::fake()->image('a.jpg'))->call('save');

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')->set('type', 'articolo')->set('title', 'Articolo B')->set('whole_pages', 1)
        ->set('file', UploadedFile::fake()->image('b.jpg'))->call('save');

    $contentA = Content::where('title', 'Articolo A')->first();
    $contentB = Content::where('title', 'Articolo B')->first();
    $page->contents()->attach([$contentA->id => ['occupied_twelfths' => 6], $contentB->id => ['occupied_twelfths' => 6]]);

    expect($contentA->files->first()->original_name)->toBe('a.jpg')
        ->and($contentB->files->first()->original_name)->toBe('b.jpg')
        ->and($contentA->files->first()->id)->not->toBe($contentB->files->first()->id);
});

test('a guest cannot download a content file', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->create(['issue_id' => $issue->id]);
    $contentFile = ContentFile::factory()->create(['content_id' => $content->id, 'uploaded_by' => $user->id]);

    $this->get(route('content-files.show', $contentFile))->assertRedirect(route('login'));
});

test('a user without access to the magazine cannot download a content file', function () {
    $issue = reorderableIssue();
    $owner = editorFor($issue);
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);
    $content = Content::factory()->create(['issue_id' => $issue->id]);
    $contentFile = ContentFile::factory()->create(['content_id' => $content->id, 'uploaded_by' => $owner->id]);

    $this->actingAs($outsider)->get(route('content-files.show', $contentFile))->assertForbidden();
});
