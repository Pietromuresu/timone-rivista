<?php

use App\Enums\ThumbnailStatus;
use App\Events\ContentCreated;
use App\Events\ContentFileUploaded;
use App\Jobs\GenerateContentFileThumbnail;
use App\Livewire\Timone\ContentCreate;
use App\Models\Content;
use App\Models\ContentFile;
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
    // motivo.
    Event::fake([ContentCreated::class, ContentFileUploaded::class]);
});

test('attaching an image to a new content generates a ready thumbnail synchronously, no imagick needed', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo con foto allegata')
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

test('attaching a real pdf actually converts its first page to a ready thumbnail', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo con PDF reale')
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
        ->set('title', 'Articolo senza allegati')
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Articolo senza allegati')->first();

    expect($content->files)->toBeEmpty();
});

test('attaching a pdf dispatches the thumbnail generation job for the content file, not the page pipeline', function () {
    Queue::fake([GenerateContentFileThumbnail::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo con PDF allegato')
        ->set('file', UploadedFile::fake()->create('bozza.pdf', 200, 'application/pdf'))
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
        ->set('title', 'Articolo con file non ammesso')
        ->set('file', UploadedFile::fake()->create('note.txt', 10, 'text/plain'))
        ->assertHasErrors('file');

    expect(Content::where('title', 'Articolo con file non ammesso')->exists())->toBeFalse();
});

test('each content on a mixed page keeps its own file, resolving the page-scoped PageFile limitation', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')->set('type', 'articolo')->set('title', 'Articolo A')
        ->set('file', UploadedFile::fake()->image('a.jpg'))->call('save');

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')->set('type', 'articolo')->set('title', 'Articolo B')
        ->set('file', UploadedFile::fake()->image('b.jpg'))->call('save');

    $contentA = Content::where('title', 'Articolo A')->first();
    $contentB = Content::where('title', 'Articolo B')->first();
    $page->contents()->attach([$contentA->id => ['occupied_percentage' => 50], $contentB->id => ['occupied_percentage' => 50]]);

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
    $outsider = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Redattore]);
    $content = Content::factory()->create(['issue_id' => $issue->id]);
    $contentFile = ContentFile::factory()->create(['content_id' => $content->id, 'uploaded_by' => $owner->id]);

    $this->actingAs($outsider)->get(route('content-files.show', $contentFile))->assertForbidden();
});
