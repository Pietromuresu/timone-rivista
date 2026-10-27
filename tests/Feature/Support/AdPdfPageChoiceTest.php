<?php

use App\Enums\AdFormat;
use App\Models\Content;
use App\Models\ContentFile;
use App\Support\ContentCreator;
use App\Support\ContentPlacer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
 * Fase 1, bug 8 (2026-10-26): "Il PDF ha 3 pagine, ma il formato
 * «Copertina (2ª/3ª/4ª)» ne occupa 1" non è più bloccante. Qui si passa il
 * numero di pagine del PDF esplicitamente a ContentCreator::create() (come
 * fa ContentCreate dopo averlo misurato), quindi questi test non richiedono
 * Imagick; il percorso completo dal modulo è in
 * tests/Feature/Livewire/ContentCreateAdPdfChoiceTest.php (solo con Imagick).
 * reorderableIssue() è in TimoneGridReorderTest.php (6 pagine).
 */

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
});

function adPdfData(array $extra = []): array
{
    return [
        'type' => 'pubblicita',
        'title' => 'Inserzione su tre pagine',
        'client' => 'Cliente PDF lungo',
        'format' => AdFormat::CopertinaSecondaTerzaQuarta->value,
        'confirmation_status' => 'confermata',
        'editorial_status' => 'da_scrivere',
        ...$extra,
    ];
}

function fakeAdPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('adv.pdf', "%PDF-1.4\n%%EOF");
}

test('a cover ad with a 3-page pdf using all pages occupies 3 whole pages', function () {
    $issue = reorderableIssue();

    $content = ContentCreator::create($issue, adPdfData(['pdf_page_from' => 1, 'pdf_page_to' => 3]), fakeAdPdf(), editorFor($issue)->id, 3);

    expect($content->occupancy()->twelfths)->toBe(36)
        ->and($content->occupancy()->distribution())->toBe([12, 12, 12])
        ->and($content->files()->orderBy('pdf_page_number')->pluck('pdf_page_number')->all())->toBe([1, 2, 3]);
});

test('using only a range of pages stores just those pdf pages, with their original numbers', function () {
    $issue = reorderableIssue();

    $content = ContentCreator::create($issue, adPdfData(['pdf_page_from' => 2, 'pdf_page_to' => 2]), fakeAdPdf(), editorFor($issue)->id, 3);

    expect($content->files()->pluck('pdf_page_number')->all())->toBe([2])
        // Una sola pagina su un formato da una pagina: occupazione del formato.
        ->and($content->occupancy()->twelfths)->toBe(AdFormat::CopertinaSecondaTerzaQuarta->twelfths());
});

test('without a choice the longer pdf asks for one instead of failing on the file', function () {
    $issue = reorderableIssue();

    try {
        ContentCreator::create($issue, adPdfData(), fakeAdPdf(), editorFor($issue)->id, 3);
        $this->fail('Nessuna eccezione');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('pdfPageChoice')->not->toHaveKey('file');
    }

    expect(Content::count())->toBe(0);
});

test('placing a range starting at pdf page 2 shows pdf pages 2 and 3 on the two timone pages', function () {
    $issue = reorderableIssue();
    $content = ContentCreator::create($issue, adPdfData([
        'format' => AdFormat::PaginaIntera->value,
        'pdf_page_from' => 2,
        'pdf_page_to' => 3,
    ]), fakeAdPdf(), editorFor($issue)->id, 3);

    $result = ContentPlacer::place($content, $issue->pages()->where('position', 3)->first());

    expect($result)->toBeArray();

    $shown = $issue->pages()->whereIn('position', [3, 4])->orderBy('position')->get()
        ->map(fn ($page) => $page->contents()->first()->pivot->content_file_id)
        ->map(fn ($id) => ContentFile::find($id)->pdf_page_number)
        ->all();

    expect($shown)->toBe([2, 3]);
});
