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
use App\Support\PageGrid\GridRect;
use App\Support\PageGrid\ThumbnailFit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Revisione 2, Fase 2.4 (2026-10-27): ritaglio dell'anteprima in base al
// rettangolo del contenuto. gridAd()/cellsOf() sono in PageGridMoveTest.php,
// reorderableIssue()/editorFor() in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class, ContentListChanged::class, ContentFileUploaded::class]));

test('a file with the proportions of the page (±5%) is full page, otherwise it is in the format', function () {
    $page = 210 / 270;

    expect(ThumbnailFit::resolve(216 / 276, null, $page))->toBe(FileFit::FullPage)     // con abbondanza
        ->and(ThumbnailFit::resolve(105 / 270, null, $page))->toBe(FileFit::Format)     // mezza verticale esportata a misura
        ->and(ThumbnailFit::resolve(null, null, $page))->toBe(FileFit::Format)          // non misurato: intero, mai un ritaglio sbagliato
        ->and(ThumbnailFit::resolve(105 / 270, FileFit::FullPage, $page))->toBe(FileFit::FullPage); // scelta manuale
});

test('full page shows only the portion of the rectangle; format shows the whole file', function () {
    // Mezza verticale a sinistra: metà sinistra della pagina.
    expect(ThumbnailFit::imageStyle(FileFit::FullPage, new GridRect(0, 0, 3, 6)))
        ->toContain('left: 0%; top: 0%; width: 200%; height: 100%')
        // Mezza orizzontale in basso: metà inferiore.
        ->and(ThumbnailFit::imageStyle(FileFit::FullPage, new GridRect(0, 3, 6, 3)))
        ->toContain('left: 0%; top: -100%; width: 100%; height: 200%')
        // Vetrina in basso a destra.
        ->and(ThumbnailFit::imageStyle(FileFit::FullPage, new GridRect(3, 3, 3, 3)))
        ->toContain('left: -100%; top: -100%; width: 200%; height: 200%')
        ->and(ThumbnailFit::imageStyle(FileFit::Format, new GridRect(0, 0, 3, 6)))
        ->toContain('object-fit: contain');
});

test('the card crops a full-page file to the rectangle of the content', function () {
    $issue = reorderableIssue();
    $issue->update(['formato_base_mm' => 210, 'formato_altezza_mm' => 270]);
    $page = $issue->pages()->where('position', 1)->first();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    ContentFile::factory()->ready()->create(['content_id' => $ad->id, 'aspect_ratio' => 0.7778]);
    ContentPlacer::place($ad, $page);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('data-fit="full_page"')
        ->assertSeeHtml('left: 0%; top: 0%; width: 200%; height: 100%');
});

test('the edit modal shows what was detected and saves the manual choice', function () {
    $issue = reorderableIssue();
    $ad = gridAd($issue, AdFormat::MezzaPaginaVerticale, 'Colonna');
    ContentFile::factory()->ready()->create(['content_id' => $ad->id, 'aspect_ratio' => 0.3889]);

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
