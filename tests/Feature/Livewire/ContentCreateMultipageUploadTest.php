<?php

use App\Enums\ContentType;
use App\Enums\PageContentType;
use App\Enums\ThumbnailStatus;
use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\PageGroupsChanged;
use App\Jobs\GenerateContentFileThumbnail;
use App\Livewire\Timone\ContentCreate;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\PageGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// (issue con total_pages = 6). Richiede ext-imagick perché il rilevamento
// del numero di pagine di un PDF (PdfPageMeasurer) è lo stesso di prima —
// stesso vincolo del vecchio TimoneGridMultipageUploadTest.php, di cui
// questo file è l'equivalente Punto 4 (2026-09-02): stesso identico
// ventaglio di scenari (anche il conflitto/le tre scelte non sono
// cambiate), riscritto per passare da App\Livewire\Timone\ContentCreate
// invece che da Grid::uploadPageFile() (rimosso — il PDF si carica solo
// su un contenuto ora). In più: la verifica del collegamento automatico
// (Punto 4.5), assente prima perché non esisteva ancora.

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
    Event::fake([ContentCreated::class, ContentAssigned::class, PageGroupsChanged::class]);
});

/**
 * Riempie i campi minimi validi di ContentCreate per un articolo, ancorato
 * a $page — stesso ruolo minimale del "pendingUploads" diretto di prima,
 * solo che qui bisogna anche passare dal form (titolo/tipo) perché il PDF
 * si carica sempre su un contenuto.
 */
function anchoredArticleUpload(User $user, Page $page, string $filename, string $pdfBytes)
{
    return Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $page->issue])
        ->call('openForPage', $page->id)
        ->set('title', 'Articolo di prova')
        ->set('file', UploadedFile::fake()->createWithContent($filename, $pdfBytes));
}

test('a single-page pdf anchored to a page is stored immediately with no conflict panel, same as before', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    anchoredArticleUpload($user, $page, 'sample.pdf', minimalValidPdfBytes())
        ->call('save')
        ->assertSet('multipageUploadConflict', null)
        ->assertHasNoErrors();

    $contentFile = ContentFile::first();
    expect($contentFile)->not->toBeNull()
        ->and($contentFile->pdf_page_number)->toBe(1)
        ->and($page->contents()->where('content_id', $contentFile->content_id)->exists())->toBeTrue();
});

test('a multi-page pdf with no conflicts shows a summary and occupies the following pages once confirmed, auto-linking them', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))->call('save');

    $component->assertSet('multipageUploadConflict.totalPdfPages', 3)
        ->assertSet('multipageUploadConflict.availablePages', 3)
        ->assertSet('multipageUploadConflict.conflictingPositions', [])
        ->assertDispatched('open-modal', 'multipage-upload-conflict');

    expect(ContentFile::query()->count())->toBe(0); // nessuna scrittura ancora

    $component->call('confirmMultipageUpload', false)
        ->assertSet('multipageUploadConflict', null)
        ->assertDispatched('close-modal', 'multipage-upload-conflict');

    expect(ContentFile::where('thumbnail_status', ThumbnailStatus::Pending)->count())->toBe(3);

    $content = Content::sole();
    $positions = $content->pages()->orderBy('position')->pluck('position')->all();
    expect($positions)->toBe([2, 3, 4]);

    foreach ($content->pages()->orderBy('position')->get() as $i => $targetPage) {
        $contentFile = ContentFile::find($targetPage->pivot->content_file_id);
        expect($contentFile->pdf_page_number)->toBe($i + 1);
    }

    Queue::assertPushed(GenerateContentFileThumbnail::class, 3);

    // Punto 4.5: le 3 pagine derivate dal PDF multi-pagina sono collegate
    // automaticamente tra loro, stessa funzione già usata da "🔗 Collega
    // selezionate"/Ctrl+Shift+G (App\Support\PageGroupLinker::link()).
    $group = PageGroup::sole();
    expect($content->pages()->orderBy('position')->pluck('page_group_id')->all())->toBe([$group->id, $group->id, $group->id]);
});

test('conflicting pages are detected and can be skipped, leaving the existing content untouched', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $conflictingPage = $issue->pages()->where('position', 3)->first();
    $existingFile = PageFile::factory()->for($conflictingPage)->create();

    $component = anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))->call('save');

    $component->assertSet('multipageUploadConflict.conflictingPositions', [3]);

    $component->call('confirmMultipageUpload', false);

    expect($page->contents()->exists())->toBeTrue()
        // La pagina in conflitto (PageFile legacy) non è stata toccata: né
        // il suo PageFile è sparito, né le è stato attaccato un contenuto.
        ->and(PageFile::where('id', $existingFile->id)->first()->page_id)->toBe($conflictingPage->id)
        ->and($conflictingPage->contents()->exists())->toBeFalse();
});

test('conflicting pages can be overwritten explicitly, adding the new content on top', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $conflictingPage = $issue->pages()->where('position', 3)->first();
    PageFile::factory()->for($conflictingPage)->create();

    anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))
        ->call('save')
        ->call('confirmMultipageUpload', true);

    // Il PageFile legacy resta (nessuna cancellazione), ma la pagina ha
    // ORA ANCHE il nuovo contenuto sopra — "sovrascrivi" per il percorso
    // pagina-diretta significava "un secondo PageFile"; qui, dato che il
    // vecchio percorso è stato rimosso, il nuovo materiale passa sempre
    // dal contenuto: previewFile() lo preferirà al PageFile legacy (vedi
    // Page::previewFile()).
    expect($conflictingPage->contents()->exists())->toBeTrue()
        ->and($conflictingPage->fresh()->previewFile())->toBeInstanceOf(ContentFile::class);
});

test('a locked page inside the range is skipped even when overwriting', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $lockedPage = $issue->pages()->where('position', 3)->first();
    $lockedPage->update(['locked_at' => now(), 'locked_by' => $user->id]);

    anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))
        ->call('save')
        ->call('confirmMultipageUpload', true);

    expect($page->contents()->exists())->toBeTrue()
        ->and($lockedPage->contents()->exists())->toBeFalse();
});

test('cancelling a multi-page upload discards it without writing anything', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))
        ->call('save')
        ->call('cancelMultipageUpload')
        ->assertSet('multipageUploadConflict', null)
        ->assertSet('file', null)
        ->assertDispatched('close-modal', 'multipage-upload-conflict');

    expect(Content::query()->count())->toBe(0)
        ->and(ContentFile::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('a pdf reaching past the end of the issue is capped to the pages actually available', function () {
    $issue = reorderableIssue(); // total_pages = 6
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 5)->first();

    $component = anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))->call('save');

    $component->assertSet('multipageUploadConflict.totalPdfPages', 4)
        ->assertSet('multipageUploadConflict.availablePages', 2); // solo le posizioni 5 e 6 esistono

    $component->call('confirmMultipageUpload', false);

    // Bug 1 (2026-10-12): il contenuto conserva TUTTE le pagine del PDF
    // (App\Support\ContentCreator crea un ContentFile per pagina sorgente,
    // a prescindere da dove verrà posizionato) — prima ne nascevano solo
    // tante quante le pagine raggiunte. Solo le 2 pagine esistenti
    // (posizioni 5 e 6) ricevono il contenuto.
    $content = Content::sole();
    expect(ContentFile::query()->count())->toBe(4)
        ->and($content->pages()->orderBy('position')->pluck('position')->all())->toBe([5, 6]);
});

test('shifting conflicting pages relocates their existing content instead of losing it, then attaches the new content in place', function () {
    $issue = reorderableIssue(); // total_pages = 6
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $conflictingPage = $issue->pages()->where('position', 3)->first();
    $existingFile = PageFile::factory()->for($conflictingPage)->create();
    $existingContent = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Articolo]);
    $conflictingPage->contents()->attach($existingContent->id, ['occupied_twelfths' => 5]);
    $conflictingPage->update(['notes' => 'nota originale']);

    $component = anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))->call('save');

    $component->assertSet('multipageUploadConflict.conflictingPositions', [3]);
    $component->call('confirmMultipageUploadWithShift');

    // La pagina 3 originale (rimasta fisicamente in posizione 3: lo shift
    // sposta le pagine SUCCESSIVE all'intervallo occupato dal nuovo
    // contenuto, non quella in conflitto stessa) ha il vecchio contenuto
    // "traslato dopo" ed è ora quella che riceve il nuovo contenuto PDF —
    // a differenza del vecchio percorso PageFile (indipendente dal
    // content_type), qui l'attach del nuovo contenuto aggiorna anche
    // content_type tramite ContentPageAttacher::syncPageContentType().
    // whereKeyNot: il test crea anche $existingContent, un sole() nudo
    // troverebbe 2 righe (mai emerso finché il file era sempre "skip").
    $newContent = Content::whereKeyNot($existingContent->id)->sole();
    expect($conflictingPage->fresh()->notes)->toBeNull()
        ->and($conflictingPage->fresh()->content_type)->toBe(PageContentType::Editoriale)
        ->and($conflictingPage->contents()->pluck('content_id')->all())->toBe([$newContent->id])
        ->and($page->contents()->pluck('content_id')->all())->toBe([$newContent->id])
        ->and(PageFile::where('id', $existingFile->id)->first()->page_id)->not->toBe($conflictingPage->id);

    // Il totale pagine si è esteso di 1 (una sola pagina in conflitto): la
    // nuova pagina bianca (che riceve il VECCHIO contenuto traslato) è
    // inserita SUBITO DOPO l'intervallo occupato dal contenuto nuovo
    // (posizione 4, non in fondo al numero).
    $issue->refresh();
    expect($issue->total_pages)->toBe(7);

    $newPage = $issue->pages()->where('position', 4)->first();
    expect($newPage)->not->toBeNull()
        ->and($newPage->notes)->toBe('nota originale')
        ->and($newPage->contents()->count())->toBe(1)
        ->and($newPage->contents()->first()->id)->toBe($existingContent->id)
        ->and($newPage->contents()->first()->pivot->occupied_twelfths)->toBe(5)
        ->and(PageFile::where('id', $existingFile->id)->first()->page_id)->toBe($newPage->id);
});

test('shifting when the conflict reaches the last page auto-expands total_pages, no gap is left', function () {
    $issue = reorderableIssue(); // total_pages = 6
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 5)->first();
    $conflictingPage = $issue->pages()->where('position', 6)->first();
    PageFile::factory()->for($conflictingPage)->create();

    anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))
        ->call('save')
        ->call('confirmMultipageUploadWithShift');

    $issue->refresh();
    expect($issue->total_pages)->toBe(7)
        ->and($issue->pages()->count())->toBe(7)
        ->and($issue->pages()->pluck('position')->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6, 7]);
});

test('a locked conflicting page is never relocated by shift, and the new content skips that position', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $lockedPage = $issue->pages()->where('position', 3)->first();
    $lockedFile = PageFile::factory()->for($lockedPage)->create();
    $lockedPage->update(['locked_at' => now(), 'locked_by' => $user->id, 'notes' => 'nota bloccata']);

    anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))
        ->call('save')
        ->call('confirmMultipageUploadWithShift');

    // La pagina bloccata resta esattamente com'era (nota inclusa) e il suo
    // file originale non si è spostato altrove — mai toccata da nessuna
    // parte dell'operazione di shift.
    expect($lockedPage->fresh()->notes)->toBe('nota bloccata')
        ->and($page->contents()->exists())->toBeTrue()
        ->and(PageFile::where('id', $lockedFile->id)->first()->page_id)->toBe($lockedPage->id);
});

test('a guest cannot shift pages to resolve an upload conflict', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))->call('save');

    $conflict = $component->get('multipageUploadConflict');
    $totalBefore = $issue->total_pages;

    Livewire::test(ContentCreate::class, ['issue' => $issue])
        ->set('multipageUploadConflict', $conflict)
        ->call('confirmMultipageUploadWithShift');

    expect(ContentFile::query()->count())->toBe(0)
        ->and($issue->fresh()->total_pages)->toBe($totalBefore);
});

test('a guest cannot confirm a multi-page upload', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = anchoredArticleUpload($user, $page, 'multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]))->call('save');

    $conflict = $component->get('multipageUploadConflict');

    Livewire::test(ContentCreate::class, ['issue' => $issue])
        ->set('multipageUploadConflict', $conflict)
        ->call('confirmMultipageUpload', false);

    expect(ContentFile::query()->count())->toBe(0);
});
