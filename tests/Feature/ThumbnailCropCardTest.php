<?php

use App\Enums\AdFormat;
use App\Enums\FileFit;
use App\Enums\ThumbnailStatus;
use App\Events\ContentAssigned;
use App\Events\ContentFileUploaded;
use App\Events\ContentListChanged;
use App\Jobs\GenerateContentFileThumbnail;
use App\Livewire\Timone\ContentEdit;
use App\Livewire\Timone\Grid;
use App\Models\ContentFile;
use App\Support\ContentPlacer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Ritaglio dell'anteprima nel rettangolo del contenuto (revisione 2, Fase
// 2.4; dal 2026-10-30 con ThumbnailCrop, regole in tests/Unit/ThumbnailCropTest.php).
// gridAd()/cellsOf() sono in PageGridMoveTest.php, reorderableIssue()/editorFor()
// in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class, ContentListChanged::class, ContentFileUploaded::class]));

test('the card crops a full-page file to the rectangle of the content', function () {
    $issue = reorderableIssue();
    $issue->update(['formato_base_mm' => 210, 'formato_altezza_mm' => 270]);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    ContentFile::factory()->ready()->create(['content_id' => $ad->id, 'aspect_ratio' => 210 / 270]);
    ContentPlacer::place($ad, $page);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('data-fit="full_page"')
        ->assertSeeHtml('left: 0%; top: 0%; width: 200%; height: 100%; max-width: none; object-fit: fill;');
});

test('the edit modal shows what was detected and saves the manual choice', function () {
    $issue = reorderableIssue();
    $issue->update(['formato_base_mm' => 210, 'formato_altezza_mm' => 270]);
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    // Proporzioni della mezza pagina: il file è già nel formato.
    ContentFile::factory()->ready()->create(['content_id' => $ad->id, 'aspect_ratio' => 0.3889]);
    ContentPlacer::place($ad->fresh(), $issue->pages()->where('position', 1)->first());

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $ad->id)
        ->assertSee('Automatico (rilevato: nel formato)')
        ->set('file_fit', 'full_page')
        ->call('save')
        ->assertHasNoErrors();

    expect($ad->fresh()->file_fit)->toBe(FileFit::FullPage);
});

test('the thumbnail job measures the proportions of the page once', function () {
    Storage::fake('local');
    $issue = reorderableIssue();
    $ad = gridAd($issue, AdFormat::UnQuartoPagina, 'Vetrina');
    $image = UploadedFile::fake()->image('vetrina.png', 300, 200);
    Storage::disk('local')->putFileAs('contents/'.$ad->id, $image, 'vetrina.png');
    $file = ContentFile::factory()->create([
        'content_id' => $ad->id,
        'path' => 'contents/'.$ad->id.'/vetrina.png',
        'mime_type' => 'image/png',
        'original_name' => 'vetrina.png',
        'thumbnail_status' => ThumbnailStatus::Pending,
    ]);

    (new GenerateContentFileThumbnail($file))->handle();

    expect($file->fresh()->aspect_ratio)->toBe(1.5);
});
