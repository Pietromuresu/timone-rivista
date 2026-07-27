<?php

use App\Models\PageFile;
use Illuminate\Support\Facades\Storage;

// reorderableIssue() è definita in TimoneGridReorderTest.php e riusata qui
// (vedi nota in TimoneGridReorderLockTest.php).

test('a file with no matching page_files row is deleted as orphaned', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $page = $issue->pages()->first();
    PageFile::factory()->for($page)->create(['path' => 'pages/1/tracked.pdf']);

    Storage::disk('local')->put('pages/1/tracked.pdf', 'contenuto tracciato');
    Storage::disk('local')->put('pages/1/orfano.pdf', 'file orfano');

    $this->artisan('pagefiles:prune-orphaned')->assertExitCode(0);

    Storage::disk('local')->assertExists('pages/1/tracked.pdf');
    Storage::disk('local')->assertMissing('pages/1/orfano.pdf');
});

test('a thumbnail referenced by thumbnail_path is not deleted', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $page = $issue->pages()->first();
    PageFile::factory()->for($page)->ready()->create([
        'path' => 'pages/1/doc.pdf',
        'thumbnail_path' => 'pages/1/thumbnails/doc.png',
    ]);

    Storage::disk('local')->put('pages/1/doc.pdf', 'pdf');
    Storage::disk('local')->put('pages/1/thumbnails/doc.png', 'png');

    $this->artisan('pagefiles:prune-orphaned');

    Storage::disk('local')->assertExists('pages/1/doc.pdf');
    Storage::disk('local')->assertExists('pages/1/thumbnails/doc.png');
});

test('dry run reports orphans without deleting anything', function () {
    Storage::fake('local');

    Storage::disk('local')->put('pages/1/orfano.pdf', 'file orfano');

    $this->artisan('pagefiles:prune-orphaned', ['--dry-run' => true]);

    Storage::disk('local')->assertExists('pages/1/orfano.pdf');
});

test('with no orphaned files nothing is deleted', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $page = $issue->pages()->first();
    PageFile::factory()->for($page)->create(['path' => 'pages/1/tracked.pdf']);
    Storage::disk('local')->put('pages/1/tracked.pdf', 'contenuto tracciato');

    $this->artisan('pagefiles:prune-orphaned')->assertExitCode(0);

    Storage::disk('local')->assertExists('pages/1/tracked.pdf');
});
