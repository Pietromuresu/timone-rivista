<?php

use App\Events\ContentAssigned;
use App\Events\ContentListChanged;
use App\Events\PageGroupsChanged;
use App\Jobs\GenerateContentFileThumbnail;
use App\Livewire\Timone\ContentEdit;
use App\Models\Content;
use App\Models\ContentFile;
use App\Support\ContentPlacer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Fase 4 (2026-10-27): sostituzione del materiale di stampa dalla modale di
// modifica. I casi con un PDF vero richiedono ext-imagick (conteggio
// pagine): skip fuori da Docker, come gli altri test PDF.

beforeEach(function () {
    Storage::fake('local');
    Queue::fake([GenerateContentFileThumbnail::class]);
    Event::fake([ContentListChanged::class, ContentAssigned::class, PageGroupsChanged::class]);
});

test('replacing an image removes the old file and keeps the content on its page', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id]);
    Storage::disk('local')->put('contents/old.png', 'x');
    $old = ContentFile::factory()->create(['content_id' => $content->id, 'path' => 'contents/old.png', 'disk' => 'local', 'mime_type' => 'image/png']);
    ContentPlacer::place($content, $issue->pages()->first());

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->assertSee('Attuale: '.$old->original_name)
        ->set('file', UploadedFile::fake()->image('nuova.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    expect(ContentFile::find($old->id))->toBeNull()
        ->and($content->files()->count())->toBe(1)
        ->and($content->files()->first()->original_name)->toBe('nuova.jpg')
        ->and($content->pages()->count())->toBe(1);
    Storage::disk('local')->assertMissing('contents/old.png');
    Queue::assertPushed(GenerateContentFileThumbnail::class);
});

test('a new multi-page pdf is spread over the pages the content occupies, one pdf page each', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    $issue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => 24]);
    ContentPlacer::place($content, $issue->pages()->where('position', 3)->first());
    $a4 = [mmToPt(210), mmToPt(297)];

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('file', UploadedFile::fake()->createWithContent('due.pdf', multiPagePdfBytes([$a4, $a4])))
        ->call('save')
        ->assertHasNoErrors();

    $pivots = $content->pages()->orderBy('pages.position')->get()->map(fn ($p) => ContentFile::find($p->pivot->content_file_id)?->pdf_page_number)->all();
    expect($pivots)->toBe([1, 2]);
});

test('a pdf whose page count does not match the occupancy is refused, nothing replaced', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    $issue = reorderableIssue();
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => 24]);
    $a4 = [mmToPt(210), mmToPt(297)];

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('file', UploadedFile::fake()->createWithContent('tre.pdf', multiPagePdfBytes([$a4, $a4, $a4])))
        ->call('save')
        ->assertHasErrors('file');

    expect($content->files()->count())->toBe(0);
});
