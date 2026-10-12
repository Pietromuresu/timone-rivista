<?php

use App\Jobs\GenerateContentFileThumbnail;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use App\Models\ContentFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// Bug 1 (2026-10-12): un PDF multipagina creato da "+ Nuovo contenuto"
// (form libero, nessuna pagina d'ancoraggio) e poi trascinato su una pagina
// finiva tutto su UNA sola pagina, mentre lo stesso PDF caricato da
// "📎 Carica materiale" si distribuiva su N pagine consecutive.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php (issue
// da 6 pagine). Richiede ext-imagick (conteggio pagine via
// PdfPageMeasurer) — skip fuori da Docker, stesso vincolo di
// ContentCreateMultipageUploadTest.php.

beforeEach(function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    Queue::fake();
    // Questi test erano sempre "skip" in locale (niente imagick) e non
    // erano mai stati eseguiti davvero: in Docker .env punta a Reverb e
    // ogni broadcast() reale falliva con "Could not resolve host".
    // Event::fake() intercetta anche i broadcast (vedi HANDOFF.md,
    // "Decisioni architetturali"), stesso pattern degli altri test.
    Event::fake([\App\Events\ContentCreated::class, \App\Events\ContentAssigned::class, \App\Events\PageGroupsChanged::class]);
});

function threePagePdf(): string
{
    return multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]);
}

test('bug 1: a 3-page pdf created from "Nuovo contenuto" occupies 3 consecutive pages when dropped, one thumbnail per pdf page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Servizio di tre pagine')
        ->set('file', UploadedFile::fake()->createWithContent('servizio.pdf', threePagePdf()))
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Servizio di tre pagine')->firstOrFail();
    $dropPage = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('assignContent', $content->id, $dropPage->id)
        ->assertHasNoErrors();

    $pages = $content->fresh()->pages()->orderBy('position')->get();

    expect($pages->pluck('position')->all())->toBe([2, 3, 4]);

    // "Thumbnail corretta per ciascuna": ogni pagina punta al ContentFile
    // della propria pagina sorgente del PDF, non tutte alla pagina 1.
    foreach ($pages->values() as $index => $page) {
        $file = ContentFile::find($page->pivot->content_file_id);
        expect($file)->not->toBeNull()
            ->and($file->pdf_page_number)->toBe($index + 1);
    }

    Queue::assertPushed(GenerateContentFileThumbnail::class, 3);
});

test('bug 1: the same 3-page pdf from "Carica materiale" (anchored) lands on the same 3 pages with the same per-page files', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $anchor = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('openForPage', $anchor->id)
        ->set('title', 'Servizio ancorato')
        ->set('file', UploadedFile::fake()->createWithContent('servizio.pdf', threePagePdf()))
        ->call('save')
        ->assertSet('multipageUploadConflict.totalPdfPages', 3)
        ->call('confirmMultipageUpload', false)
        ->assertHasNoErrors();

    $content = Content::where('title', 'Servizio ancorato')->firstOrFail();
    $pages = $content->pages()->orderBy('position')->get();

    expect($pages->pluck('position')->all())->toBe([2, 3, 4])
        ->and($content->page_occupancy)->toBe('3.00');

    foreach ($pages->values() as $index => $page) {
        expect(ContentFile::find($page->pivot->content_file_id)->pdf_page_number)->toBe($index + 1);
    }

    Queue::assertPushed(GenerateContentFileThumbnail::class, 3);
});

test('bug 5: choosing a pdf proposes its page count as occupancy, visibly, without overwriting an explicit choice', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Proposta')
        ->set('file', UploadedFile::fake()->createWithContent('servizio.pdf', threePagePdf()))
        ->assertSet('pdfPageCount', 3)
        ->assertSet('page_occupancy', '3')
        ->assertSee('Il PDF allegato ha 3 pagine');

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Scelta esplicita')
        ->set('page_occupancy', '0.50')
        ->set('file', UploadedFile::fake()->createWithContent('servizio.pdf', threePagePdf()))
        ->assertSet('page_occupancy', '0.50');
});

test('bug 5: an occupancy that contradicts the pdf page count is rejected and nothing is created', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Incoerente')
        ->set('page_occupancy', '1')
        ->set('file', UploadedFile::fake()->createWithContent('servizio.pdf', threePagePdf()))
        ->call('save')
        ->assertHasErrors('page_occupancy');

    expect(Content::count())->toBe(0)
        ->and(ContentFile::count())->toBe(0);
});

test('bug 1: an unreadable pdf is rejected with a clear message instead of being treated as one page', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    // Header PDF valido (passa mimes:pdf) ma corpo non interpretabile —
    // stesso fixture già usato per il percorso "thumbnail Failed".
    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'PDF rotto')
        ->set('page_occupancy', '1')
        ->set('file', UploadedFile::fake()->createWithContent('rotto.pdf', "%PDF-1.4\nnon un vero pdf\n%%EOF"))
        ->assertHasErrors('file')
        ->call('save')
        ->assertHasErrors('file');

    expect(Content::count())->toBe(0);
});
