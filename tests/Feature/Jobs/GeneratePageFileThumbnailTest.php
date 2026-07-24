<?php

use App\Enums\ThumbnailStatus;
use App\Events\PageFileUploaded;
use App\Jobs\GeneratePageFileThumbnail;
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
