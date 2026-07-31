<?php

use App\Enums\AdFormat;
use App\Enums\FormatCheckStatus;
use App\Enums\ThumbnailStatus;
use App\Events\PageFileUploaded;
use App\Jobs\GeneratePageFileThumbnail;
use App\Models\Content;
use App\Models\Page;
use App\Models\PageFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }
});

test('a valid pdf is converted and the thumbnail is marked ready', function () {
    Storage::fake('local');
    Event::fake([PageFileUploaded::class]);

    Storage::disk('local')->put('pages/1/test.pdf', minimalValidPdfBytes());
    $pageFile = PageFile::factory()->for(Page::factory())->create([
        'disk' => 'local',
        'path' => 'pages/1/test.pdf',
    ]);

    (new GeneratePageFileThumbnail($pageFile))->handle();

    $pageFile->refresh();

    expect($pageFile->thumbnail_status)->toBe(ThumbnailStatus::Ready)
        ->and($pageFile->thumbnail_path)->not->toBeNull();

    Storage::disk('local')->assertExists($pageFile->thumbnail_path);

    Event::assertDispatched(PageFileUploaded::class, fn (PageFileUploaded $event) => $event->pageFileId === $pageFile->id
        && $event->thumbnailStatus === 'ready');
});

test('a corrupted file marks the thumbnail as failed without throwing', function () {
    Storage::fake('local');
    Event::fake([PageFileUploaded::class]);

    Storage::disk('local')->put('pages/1/corrotto.pdf', 'questo non è un pdf valido');
    $pageFile = PageFile::factory()->for(Page::factory())->create([
        'disk' => 'local',
        'path' => 'pages/1/corrotto.pdf',
    ]);

    (new GeneratePageFileThumbnail($pageFile))->handle();

    expect($pageFile->refresh()->thumbnail_status)->toBe(ThumbnailStatus::Failed)
        ->and($pageFile->thumbnail_path)->toBeNull();

    Event::assertDispatched(PageFileUploaded::class, fn (PageFileUploaded $event) => $event->pageFileId === $pageFile->id
        && $event->thumbnailStatus === 'failed');
});

test('a corrupted file on a page with no applicable ad format is not applicable, not unverifiable', function () {
    Storage::fake('local');
    Event::fake([PageFileUploaded::class]);

    // NotApplicable viene controllato PRIMA di tentare la misura (nessun
    // formato a cui riferirsi, a prescindere da quanto sia leggibile il
    // file) — Unverifiable riguarda solo il caso "un formato c'era, ma non
    // si è riusciti a misurare il PDF", vedi test dedicato sotto.
    Storage::disk('local')->put('pages/1/corrotto.pdf', 'questo non è un pdf valido');
    $pageFile = PageFile::factory()->for(Page::factory())->create([
        'disk' => 'local',
        'path' => 'pages/1/corrotto.pdf',
    ]);

    (new GeneratePageFileThumbnail($pageFile))->handle();

    expect($pageFile->refresh()->format_check_status)->toBe(FormatCheckStatus::NotApplicable);
});

test('a corrupted file on a page with an applicable ad format is unverifiable, never throwing', function () {
    Storage::fake('local');
    Event::fake([PageFileUploaded::class]);

    $page = Page::factory()->create();
    $content = Content::factory()->advertisement()->create(['issue_id' => $page->issue_id]);
    $content->advertisement->update(['format' => AdFormat::PaginaIntera]);
    $page->contents()->attach($content->id, ['occupied_percentage' => '100']);

    Storage::disk('local')->put("pages/{$page->id}/corrotto.pdf", 'questo non è un pdf valido');
    $pageFile = PageFile::factory()->for($page)->create([
        'disk' => 'local',
        'path' => "pages/{$page->id}/corrotto.pdf",
    ]);

    (new GeneratePageFileThumbnail($pageFile))->handle();

    expect($pageFile->refresh()->format_check_status)->toBe(FormatCheckStatus::Unverifiable);
});

test('the correct internal pdf page is selected for the thumbnail via pdf_page_number', function () {
    Storage::fake('local');
    Event::fake([PageFileUploaded::class]);

    Storage::disk('local')->put('pages/1/multi.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]));

    $pageFile = PageFile::factory()->for(Page::factory())->create([
        'disk' => 'local',
        'path' => 'pages/1/multi.pdf',
        'pdf_page_number' => 3,
    ]);

    (new GeneratePageFileThumbnail($pageFile))->handle();

    expect($pageFile->refresh()->thumbnail_status)->toBe(ThumbnailStatus::Ready)
        ->and($pageFile->thumbnail_path)->not->toBeNull();

    Storage::disk('local')->assertExists($pageFile->thumbnail_path);
});

test('a page with no advertising content has a not applicable format check', function () {
    Storage::fake('local');
    Event::fake([PageFileUploaded::class]);

    $page = Page::factory()->create();
    Storage::disk('local')->put("pages/{$page->id}/test.pdf", minimalValidPdfBytes());
    $pageFile = PageFile::factory()->for($page)->create([
        'disk' => 'local',
        'path' => "pages/{$page->id}/test.pdf",
    ]);

    (new GeneratePageFileThumbnail($pageFile))->handle();

    expect($pageFile->refresh()->format_check_status)->toBe(FormatCheckStatus::NotApplicable);
});

test('a matching pdf format is marked as matching, with measured dimensions stored', function () {
    Storage::fake('local');
    Event::fake([PageFileUploaded::class]);

    $page = Page::factory()->create();
    $content = Content::factory()->advertisement()->create(['issue_id' => $page->issue_id]);
    $content->advertisement->update(['format' => AdFormat::PaginaIntera]);
    $page->contents()->attach($content->id, ['occupied_percentage' => '100']);

    // 210x270mm + 6mm di abbondanza = 216x276mm
    Storage::disk('local')->put("pages/{$page->id}/test.pdf", multiPagePdfBytes([
        [mmToPt(216), mmToPt(276)],
    ]));
    $pageFile = PageFile::factory()->for($page)->create([
        'disk' => 'local',
        'path' => "pages/{$page->id}/test.pdf",
    ]);

    (new GeneratePageFileThumbnail($pageFile))->handle();
    $pageFile->refresh();

    expect($pageFile->format_check_status)->toBe(FormatCheckStatus::Matching)
        ->and((float) $pageFile->measured_width_mm)->toBeBetween(215.5, 216.5)
        ->and((float) $pageFile->measured_height_mm)->toBeBetween(275.5, 276.5);
});

test('a mismatching pdf format is marked as mismatch', function () {
    Storage::fake('local');
    Event::fake([PageFileUploaded::class]);

    $page = Page::factory()->create();
    $content = Content::factory()->advertisement()->create(['issue_id' => $page->issue_id]);
    $content->advertisement->update(['format' => AdFormat::PaginaIntera]);
    $page->contents()->attach($content->id, ['occupied_percentage' => '100']);

    // Molto più piccolo del formato atteso (210x270mm + abbondanza)
    Storage::disk('local')->put("pages/{$page->id}/test.pdf", multiPagePdfBytes([
        [mmToPt(100), mmToPt(100)],
    ]));
    $pageFile = PageFile::factory()->for($page)->create([
        'disk' => 'local',
        'path' => "pages/{$page->id}/test.pdf",
    ]);

    (new GeneratePageFileThumbnail($pageFile))->handle();

    expect($pageFile->refresh()->format_check_status)->toBe(FormatCheckStatus::Mismatch);
});
