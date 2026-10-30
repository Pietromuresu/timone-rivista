<?php

use App\Enums\AdFormat;
use App\Events\ContentAssigned;
use App\Events\ContentFileUploaded;
use App\Events\ContentListChanged;
use App\Jobs\CompleteContentFileThumbnail;
use App\Jobs\WarmCroppedThumbnail;
use App\Livewire\Timone\Grid;
use App\Support\ContentPlacer;
use App\Support\CroppedThumbnails;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Ritagli per l'export del timone (2026-10-30, CroppedThumbnails): cache
 * legata a file, finestra (posizione, forma, rotazione) e miniatura;
 * preparati in coda dalla card. cropIssue()/cropFile()/blockOf() sono in
 * ThumbnailCropReproTest.php, gridAd() in PageGridMoveTest.php,
 * reorderableIssue()/editorFor() in TimoneGridReorderTest.php.
 */

beforeEach(function () {
    Event::fake([ContentAssigned::class, ContentListChanged::class, ContentFileUploaded::class]);
    Storage::fake('local');
    config(['broadcasting.default' => 'null']);
});

test('the cached crop changes name when the content moves or rotates, or when the file changes', function () {
    $issue = cropIssue(210, 297);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::UnQuartoPagina, 'Vetrina');
    $file = cropFile($ad, 400 / 566, 400 / 566);
    ContentPlacer::place($ad, $page);

    $block = blockOf($page, $ad);
    $before = CroppedThumbnails::ensure($block['file'], $block['crop']->windowFor($block['cells']));
    expect($before)->not->toBeNull()->and(Storage::disk('local')->exists($before))->toBeTrue();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->call('moveContentOnGrid', $page->id, $ad->id, 'right');
    $moved = blockOf($page, $ad);
    $afterMove = CroppedThumbnails::relativePath($moved['file'], $moved['crop']->windowFor($moved['cells']));

    expect($moved['cells']->x)->toBe(3)
        ->and($afterMove)->not->toBe($before);

    // Un file nuovo (nuova riga content_files) = un ritaglio nuovo.
    $newFile = cropFile($ad, 400 / 566, 400 / 566);
    expect(CroppedThumbnails::relativePath($newFile, $moved['crop']->windowFor($moved['cells'])))->not->toBe($afterMove)
        ->and($file->id)->not->toBe($newFile->id);
});

test('the card prepares the crops in the queue, once, and completes old files in the queue', function () {
    Queue::fake();
    $issue = cropIssue(210, 297);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    cropFile($ad, 400 / 566, 400 / 566);
    ContentPlacer::place($ad, $page);

    blockOf($page, $ad);
    blockOf($page, $ad);

    Queue::assertPushed(WarmCroppedThumbnail::class, 1);
    Queue::assertPushed(CompleteContentFileThumbnail::class, 1);
});

test('completing a file in the queue makes the other users redraw the timone', function () {
    $issue = cropIssue(210, 297);
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    $file = cropFile($ad, 400 / 566, null);
    $before = $issue->fresh()->getRawOriginal('pages_updated_at');

    $this->travel(2)->seconds();
    (new CompleteContentFileThumbnail($file))->handle();

    expect($file->fresh()->aspect_ratio)->toEqualWithDelta(400 / 566, 0.01)
        ->and($file->fresh()->trim_box)->toBe(['media' => [210, 297], 'trim' => null])
        ->and($issue->fresh()->getRawOriginal('pages_updated_at'))->not->toBe($before);
});
