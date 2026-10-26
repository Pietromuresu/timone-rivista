<?php

use App\Enums\AdFormat;
use App\Livewire\Timone\ContentCreate;
use App\Models\Content;
use App\Models\ContentFile;
use App\Support\AdPdfPagePlan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * Fase 1, bug 8 (2026-10-26): un PDF di 3 pagine su una pubblicità
 * "Copertina (2ª/3ª/4ª)" (1 pagina) era rifiutato. Ora il modulo chiede
 * quali pagine usare. Percorso completo dal modulo: richiede ext-imagick
 * per contare le pagine (skip fuori da Docker, come
 * ContentCreateMultipageUploadTest.php). Per eseguirli in locale senza
 * imagick: vedi HANDOFF.md, sessione 2026-10-26 "Bug 8".
 * reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php;
 * threePagePdf() in ContentFreeFormPdfDistributionTest.php.
 */

beforeEach(function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    Queue::fake();
    Event::fake([\App\Events\ContentCreated::class, \App\Events\ContentAssigned::class, \App\Events\PageGroupsChanged::class]);
});

function coverAdWithThreePagePdf($issue)
{
    return Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('title', 'Inserzione copertina')
        ->set('client', 'Cliente copertina')
        ->set('format', AdFormat::CopertinaSecondaTerzaQuarta->value)
        ->set('file', UploadedFile::fake()->createWithContent('copertina.pdf', threePagePdf()));
}

test('a multi-page pdf on a one-page ad format is accepted and the page choice is shown', function () {
    $issue = reorderableIssue();

    coverAdWithThreePagePdf($issue)
        ->assertHasNoErrors()
        ->assertSet('pdfPageCount', 3)
        ->assertSee('Il PDF ha 3 pagine, il formato «Copertina (2ª/3ª/4ª)» ne occupa 1')
        ->assertSee('la prima sulla copertina, le altre sulle pagine successive');
});

test('choosing all pages creates an ad occupying 3 pages with one thumbnail per pdf page', function () {
    $issue = reorderableIssue();

    coverAdWithThreePagePdf($issue)
        ->set('pdfPageChoice', AdPdfPagePlan::ALL)
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Inserzione copertina')->firstOrFail();

    expect($content->occupancy()->pagesSpanned())->toBe(3)
        ->and(ContentFile::where('content_id', $content->id)->orderBy('pdf_page_number')->pluck('pdf_page_number')->all())->toBe([1, 2, 3]);
});

test('choosing a single page keeps the cover occupancy and stores only that pdf page', function () {
    $issue = reorderableIssue();

    coverAdWithThreePagePdf($issue)
        ->set('pdfPageChoice', AdPdfPagePlan::RANGE)
        ->set('pdfPageFrom', 3)
        ->set('pdfPageTo', 3)
        ->call('save')
        ->assertHasNoErrors();

    $content = Content::where('title', 'Inserzione copertina')->firstOrFail();

    expect($content->occupancy()->pagesSpanned())->toBe(1)
        ->and(ContentFile::where('content_id', $content->id)->pluck('pdf_page_number')->all())->toBe([3]);
});

test('an invalid range shows an inline error and creates nothing', function () {
    $issue = reorderableIssue();

    coverAdWithThreePagePdf($issue)
        ->set('pdfPageChoice', AdPdfPagePlan::RANGE)
        ->set('pdfPageFrom', 3)
        ->set('pdfPageTo', 1)
        ->call('save')
        ->assertHasErrors('pdfPageTo');

    expect(Content::count())->toBe(0);
});

test('anchored to a page, a 2-page range asks to occupy 2 timone pages, not 3', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('openForPage', $page->id)
        ->set('type', 'pubblicita')
        ->set('title', 'Inserzione ancorata')
        ->set('client', 'Cliente ancorato')
        ->set('format', AdFormat::PaginaIntera->value)
        ->set('file', UploadedFile::fake()->createWithContent('adv.pdf', threePagePdf()))
        ->set('pdfPageChoice', AdPdfPagePlan::RANGE)
        ->set('pdfPageFrom', 2)
        ->set('pdfPageTo', 3)
        ->call('save')
        ->assertSet('multipageUploadConflict.totalPdfPages', 2)
        ->assertSet('multipageUploadConflict.sourcePdfPageCount', 3)
        ->call('confirmMultipageUpload', false)
        ->assertHasNoErrors();

    $content = Content::where('title', 'Inserzione ancorata')->firstOrFail();
    $shown = $issue->pages()->whereIn('position', [2, 3])->orderBy('position')->get()
        ->map(fn ($p) => ContentFile::find($p->contents()->first()->pivot->content_file_id)->pdf_page_number)
        ->all();

    expect($content->occupancy()->pagesSpanned())->toBe(2)
        ->and($shown)->toBe([2, 3]);
});
