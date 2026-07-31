<?php

use App\Support\PdfPageMeasurer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }
});

test('pageCount returns the real number of pages in a multi-page pdf', function () {
    Storage::fake('local');
    Storage::disk('local')->put('test.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
        [mmToPt(210), mmToPt(270)],
    ]));

    expect(PdfPageMeasurer::pageCount(Storage::disk('local')->path('test.pdf')))->toBe(3);
});

test('pageCount returns 1 for a single-page pdf', function () {
    Storage::fake('local');
    Storage::disk('local')->put('test.pdf', minimalValidPdfBytes());

    expect(PdfPageMeasurer::pageCount(Storage::disk('local')->path('test.pdf')))->toBe(1);
});

test('pageCount returns null for a corrupted file instead of throwing', function () {
    Storage::fake('local');
    Storage::disk('local')->put('corrotto.pdf', 'questo non è un pdf valido');

    expect(PdfPageMeasurer::pageCount(Storage::disk('local')->path('corrotto.pdf')))->toBeNull();
});

test('pageSizeMm measures the correct internal page of a multi-page pdf, in millimeters', function () {
    Storage::fake('local');
    Storage::disk('local')->put('test.pdf', multiPagePdfBytes([
        [mmToPt(210), mmToPt(270)], // pagina 1: A4-ish
        [mmToPt(420), mmToPt(270)], // pagina 2: doppia pagina
    ]));

    $path = Storage::disk('local')->path('test.pdf');

    $page1 = PdfPageMeasurer::pageSizeMm($path, 1);
    $page2 = PdfPageMeasurer::pageSizeMm($path, 2);

    expect($page1['width'])->toBeBetween(209.5, 210.5)
        ->and($page1['height'])->toBeBetween(269.5, 270.5)
        ->and($page2['width'])->toBeBetween(419.5, 420.5)
        ->and($page2['height'])->toBeBetween(269.5, 270.5);
});

test('pageSizeMm returns null for a corrupted file instead of throwing', function () {
    Storage::fake('local');
    Storage::disk('local')->put('corrotto.pdf', 'questo non è un pdf valido');

    expect(PdfPageMeasurer::pageSizeMm(Storage::disk('local')->path('corrotto.pdf'), 1))->toBeNull();
});
