<?php

use App\Events\ContentCreated;
use App\Jobs\GenerateContentFileThumbnail;
use App\Livewire\Timone\ContentCreate;
use App\Models\Content;
use App\Models\ContentFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// Bug 2 (2026-10-12): drop zone per il file nel form di creazione
// contenuto. Il drop lato browser chiama $wire.upload('file', ...), che
// alla fine fa esattamente ciò che fa ->set('file', ...) qui: carica il
// file temporaneo, assegna la proprietà `file` e scatena updatedFile().
// Il wiring Alpine (dragover/drop/barra di avanzamento) non è eseguibile
// senza un browser (nessun Dusk): va verificato a mano, vedi HANDOFF.md.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(function () {
    Queue::fake();
    Event::fake([ContentCreated::class]);
});

test('the form shows a drop zone backed by the same file input (click still opens the classic picker)', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->assertSeeHtml('data-testid="file-drop-zone"')
        // Revisione 2, Fase 1, bug 5: il $wire.upload('file', …) del drop è in
        // Alpine.data('fileUpload') (resources/js/timone-alpine.js).
        ->assertSeeHtml('x-data="fileUpload(')
        ->assertSeeHtml('x-ref="fileInput" wire:model="file"');
});

test('a dropped file that is too large is rejected right away with the same rule as the input, before saving', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        // Revisione 2, Fase 1, bug 5: limite 100 MB (timone.upload_max_kb), prima 20 MB.
        ->set('file', UploadedFile::fake()->create('enorme.pdf', 101 * 1024, 'application/pdf'))
        // Fermato già dall'upload temporaneo di Livewire (stesso limite): il
        // messaggio è comunque quello chiaro, con il limite in MB.
        ->assertHasErrors('file')
        ->assertSee('Il file supera il limite di 100 MB');

    expect(Content::count())->toBe(0);
});

test('a dropped 3-page pdf creates the content with 3 pages of occupancy, one file and one thumbnail job per page', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Trascinato')
        ->set('file', UploadedFile::fake()->createWithContent('trascinato.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])))
        ->assertSet('pdfPageCount', 3)
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Trascinato')->firstOrFail();

    expect($content->occupancy_twelfths)->toBe(36)
        ->and(ContentFile::where('content_id', $content->id)->orderBy('pdf_page_number')->pluck('pdf_page_number')->all())->toBe([1, 2, 3]);

    Queue::assertPushed(GenerateContentFileThumbnail::class, 3);
});
