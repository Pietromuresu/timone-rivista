<?php

use App\Livewire\Timone\Grid;
use App\Models\PageFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// (issue con total_pages = 6). Richiede ext-imagick perché il rilevamento
// del numero di pagine di un PDF (Grid::uploadPageFile()) usa
// App\Support\PdfPageMeasurer — stesso vincolo di GeneratePageFileThumbnailTest.

beforeEach(function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    Queue::fake();
});

test('a single-page pdf is stored immediately with no conflict panel, same as before', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('sample.pdf', minimalValidPdfBytes()))
        ->assertSet('multipageUploadConflict', null);

    $pageFile = PageFile::where('page_id', $page->id)->first();
    expect($pageFile)->not->toBeNull()
        ->and($pageFile->pdf_page_number)->toBe(1);
});

test('a multi-page pdf with no conflicts shows a summary and occupies the following pages once confirmed', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])));

    $component->assertSet('multipageUploadConflict.totalPdfPages', 3)
        ->assertSet('multipageUploadConflict.availablePages', 3)
        ->assertSet('multipageUploadConflict.conflictingPositions', [])
        // 2026-07-31: il riepilogo è ora un modale, non un pannello inline
        // (poteva finire fuori dal viewport, vedi HANDOFF.md) — verifica
        // che l'apertura sia effettivamente scattata.
        ->assertDispatched('open-modal', 'multipage-upload-conflict');

    expect(PageFile::query()->count())->toBe(0); // nessuna scrittura ancora

    $component->call('confirmMultipageUpload', false)
        ->assertSet('multipageUploadConflict', null)
        ->assertDispatched('close-modal', 'multipage-upload-conflict')
        // Barra di avanzamento miniature (2026-07-31, ridisegnata per
        // sopravvivere a un refresh — vedi TimoneGridThumbnailProgressTest.php
        // per la copertura di App\Support\ThumbnailProgressEstimator):
        // ricalcolata da render() dallo stato reale in database, qui basta
        // verificare che il banner compaia nell'HTML per le 3 pagine appena
        // create, tutte ancora Pending.
        ->assertSee('3 pagine in elaborazione');

    expect(PageFile::where('thumbnail_status', \App\Enums\ThumbnailStatus::Pending)->count())->toBe(3);

    $positions = $issue->pages()->whereIn('id', PageFile::pluck('page_id'))->pluck('position')->sort()->values()->all();
    expect($positions)->toBe([2, 3, 4]);

    foreach ([2, 3, 4] as $i => $position) {
        $pf = PageFile::whereHas('page', fn ($q) => $q->where('position', $position))->first();
        expect($pf->pdf_page_number)->toBe($i + 1);
    }

    Queue::assertPushed(\App\Jobs\GeneratePageFileThumbnail::class, 3);
});

test('conflicting pages are detected and can be skipped, leaving the existing file untouched', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $conflictingPage = $issue->pages()->where('position', 3)->first();
    $existingFile = PageFile::factory()->for($conflictingPage)->create();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])));

    $component->assertSet('multipageUploadConflict.conflictingPositions', [3]);

    $component->call('confirmMultipageUpload', false);

    expect(PageFile::where('page_id', $page->id)->exists())->toBeTrue()
        ->and(PageFile::where('page_id', $conflictingPage->id)->count())->toBe(1)
        ->and(PageFile::where('page_id', $conflictingPage->id)->first()->id)->toBe($existingFile->id);
});

test('conflicting pages can be overwritten explicitly, adding a new file on top', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $conflictingPage = $issue->pages()->where('position', 3)->first();
    PageFile::factory()->for($conflictingPage)->create();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])))
        ->call('confirmMultipageUpload', true);

    expect(PageFile::where('page_id', $conflictingPage->id)->count())->toBe(2);
});

test('a locked page inside the range is skipped even when overwriting', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $lockedPage = $issue->pages()->where('position', 3)->first();
    $lockedPage->update(['locked_at' => now(), 'locked_by' => $user->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])))
        ->call('confirmMultipageUpload', true);

    expect(PageFile::where('page_id', $page->id)->exists())->toBeTrue()
        ->and(PageFile::where('page_id', $lockedPage->id)->exists())->toBeFalse();
});

test('cancelling a multi-page upload discards it without writing anything', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])))
        ->call('cancelMultipageUpload')
        ->assertSet('multipageUploadConflict', null)
        ->assertSet('pendingUploads', [])
        ->assertDispatched('close-modal', 'multipage-upload-conflict');

    expect(PageFile::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('a pdf reaching past the end of the issue is capped to the pages actually available', function () {
    $issue = reorderableIssue(); // total_pages = 6
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 5)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])));

    $component->assertSet('multipageUploadConflict.totalPdfPages', 4)
        ->assertSet('multipageUploadConflict.availablePages', 2); // solo le posizioni 5 e 6 esistono

    $component->call('confirmMultipageUpload', false);

    expect(PageFile::query()->count())->toBe(2);
});

test('shifting conflicting pages relocates their existing content instead of losing it, then writes the new pdf in place', function () {
    $issue = reorderableIssue(); // total_pages = 6
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $conflictingPage = $issue->pages()->where('position', 3)->first();
    $existingFile = PageFile::factory()->for($conflictingPage)->create();
    $existingContent = \App\Models\Content::factory()->create(['issue_id' => $issue->id, 'type' => \App\Enums\ContentType::Articolo]);
    $conflictingPage->contents()->attach($existingContent->id, ['occupied_percentage' => 40]);
    $conflictingPage->update(['notes' => 'nota originale']);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])));

    $component->assertSet('multipageUploadConflict.conflictingPositions', [3]);
    $component->call('confirmMultipageUploadWithShift');

    // La pagina 3 originale è tornata bianca (il suo contenuto è
    // "traslato dopo"), il PDF nuovo occupa posizione 2 e 3 come previsto.
    expect($conflictingPage->fresh()->content_type)->toBe(\App\Enums\PageContentType::Bianca)
        ->and($conflictingPage->fresh()->notes)->toBeNull()
        ->and($conflictingPage->contents()->count())->toBe(0)
        ->and(PageFile::where('page_id', $conflictingPage->id)->where('id', $existingFile->id)->exists())->toBeFalse()
        ->and(PageFile::where('page_id', $page->id)->exists())->toBeTrue()
        ->and(PageFile::where('page_id', $conflictingPage->id)->exists())->toBeTrue(); // il PDF nuovo, non il vecchio file

    // Il totale pagine si è esteso di 1 (una sola pagina in conflitto): la
    // nuova pagina bianca è inserita SUBITO DOPO l'intervallo occupato dal
    // PDF (posizione 4, non in fondo al numero) — "sposta dopo" significa
    // dopo il nuovo contenuto, non in coda a tutto il timone; le vecchie
    // posizioni 4/5/6 slittano di conseguenza a 5/6/7.
    $issue->refresh();
    expect($issue->total_pages)->toBe(7);

    $newPage = $issue->pages()->where('position', 4)->first();
    expect($newPage)->not->toBeNull()
        ->and($newPage->notes)->toBe('nota originale')
        ->and($newPage->contents()->count())->toBe(1)
        ->and($newPage->contents()->first()->pivot->occupied_percentage)->toEqualWithDelta(40, 0.01)
        ->and(PageFile::where('page_id', $newPage->id)->where('id', $existingFile->id)->exists())->toBeTrue();
});

test('shifting when the conflict reaches the last page auto-expands total_pages, no gap is left', function () {
    $issue = reorderableIssue(); // total_pages = 6
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 5)->first();
    $conflictingPage = $issue->pages()->where('position', 6)->first();
    PageFile::factory()->for($conflictingPage)->create();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])))
        ->call('confirmMultipageUploadWithShift');

    $issue->refresh();
    expect($issue->total_pages)->toBe(7)
        ->and($issue->pages()->count())->toBe(7)
        ->and($issue->pages()->pluck('position')->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6, 7]);
});

test('a locked conflicting page is never relocated by shift, and the new pdf skips that position', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();
    $lockedPage = $issue->pages()->where('position', 3)->first();
    $lockedFile = PageFile::factory()->for($lockedPage)->create();
    $lockedPage->update(['locked_at' => now(), 'locked_by' => $user->id, 'notes' => 'nota bloccata']);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])))
        ->call('confirmMultipageUploadWithShift');

    // La pagina bloccata resta esattamente com'era (nota inclusa) e il suo
    // file originale non si è spostato altrove — mai toccata da nessuna
    // parte dell'operazione di shift, coerente con blockIfLocked() usato
    // ovunque nel progetto.
    expect($lockedPage->fresh()->notes)->toBe('nota bloccata')
        ->and(PageFile::where('page_id', $page->id)->exists())->toBeTrue()
        ->and(PageFile::where('id', $lockedFile->id)->first()->page_id)->toBe($lockedPage->id);
});

test('a guest cannot shift pages to resolve an upload conflict', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])));

    $conflict = $component->get('multipageUploadConflict');
    $totalBefore = $issue->total_pages;

    Livewire::test(Grid::class, ['issue' => $issue])
        ->set('multipageUploadConflict', $conflict)
        ->call('confirmMultipageUploadWithShift');

    expect(PageFile::query()->count())->toBe(0)
        ->and($issue->fresh()->total_pages)->toBe($totalBefore);
});

test('a guest cannot confirm a multi-page upload', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 2)->first();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->createWithContent('multi.pdf', multiPagePdfBytes([
            [mmToPt(210), mmToPt(270)],
            [mmToPt(210), mmToPt(270)],
        ])));

    $conflict = $component->get('multipageUploadConflict');

    Livewire::test(Grid::class, ['issue' => $issue])
        ->set('multipageUploadConflict', $conflict)
        ->call('confirmMultipageUpload', false);

    expect(PageFile::query()->count())->toBe(0);
});
