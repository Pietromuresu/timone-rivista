<?php

use App\Livewire\Timone\ContentEdit;
use App\Models\Content;
use App\Support\ContentFormRules;
use App\Support\PdfPageMeasurer;
use App\Support\Uploads\UploadEnvironmentInspector;
use App\Support\Uploads\UploadLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

// Revisione 2, Fase 1, bug 5 (2026-10-27): upload fino ad almeno 100 MB.
// Prima il modulo contenuti era fermo a 20 MB mentre PHP/nginx/Livewire
// accettavano 100 MB. editorFor()/reorderableIssue() sono in TimoneGridReorderTest.php.

test('the upload limit lives in one config key, used by the form rule and by the Livewire temporary upload', function () {
    expect(config('timone.upload_max_kb'))->toBe(102400)
        ->and(ContentFormRules::maxKilobytes())->toBe(102400)
        ->and(ContentFormRules::fileRule())->toEndWith('|max:102400')
        ->and(config('livewire.temporary_file_upload.rules'))->toContain('max:102400')
        ->and(config('livewire.temporary_file_upload.max_upload_time'))->toBeGreaterThanOrEqual(30);
});

test('a 90 MB PDF passes the form rule, a 101 MB one gets a message with the limit in MB', function () {
    $rules = ['file' => ContentFormRules::fileRule()];

    $ok = Validator::make(['file' => UploadedFile::fake()->create('stampa.pdf', 90 * 1024, 'application/pdf')], $rules, ContentFormRules::messages());
    $tooBig = Validator::make(['file' => UploadedFile::fake()->create('enorme.pdf', 101 * 1024, 'application/pdf')], $rules, ContentFormRules::messages());

    expect($ok->passes())->toBeTrue()
        ->and($tooBig->errors()->first('file'))->toStartWith('Il file supera il limite di 100 MB');
});

test('the edit modal shows the limit and an upload progress bar', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->create(['issue_id' => $issue->id]);

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->assertSeeHtml('x-data="fileUpload(')
        ->assertSeeHtml('data-testid="upload-progress"')
        // Il limite mostrato è quello EFFETTIVO (il più stretto tra PHP e app):
        // in locale può essere upload_max_filesize di PHP.
        ->assertSee('Massimo '.UploadLimits::humanMegabytes(UploadLimits::effective(ContentFormRules::maxKilobytes())['bytes']).'.');
});

test('a file over the limit in the edit modal gets the clear message with the limit', function () {
    $issue = reorderableIssue();
    $content = Content::factory()->create(['issue_id' => $issue->id]);

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->set('file', UploadedFile::fake()->create('enorme.pdf', 101 * 1024, 'application/pdf'))
        ->assertHasErrors('file')
        ->assertSee('Il file supera il limite di 100 MB');
});

test('the upload check warns when PHP times are below the threshold', function () {
    $previous = ini_get('max_execution_time');
    ini_set('max_execution_time', '30');

    try {
        $times = collect(UploadEnvironmentInspector::inspect(componentMaxKilobytes: 102400, probePdf: false))->firstWhere('check', 'Tempi PHP');
    } finally {
        ini_set('max_execution_time', (string) $previous);
    }

    expect($times['status'])->toBe('warn')
        ->and($times['hint'])->toContain('max_execution_time ≥ 120');
});

test('the upload check reports the PHP size threshold and the temporary disk space', function () {
    $results = collect(UploadEnvironmentInspector::inspect(componentMaxKilobytes: 102400, probePdf: false));

    expect($results->firstWhere('check', 'Limiti PHP')['detail'])->toContain('soglia consigliata ≥ 110 MB')
        ->and($results->firstWhere('check', 'Spazio disco temporaneo'))->not->toBeNull();
});

test('pages are counted by Ghostscript without rendering them', function () {
    if ((new ExecutableFinder)->find('gs') === null) {
        $this->markTestSkipped('Ghostscript non installato (presente nel container Docker).');
    }

    $path = sys_get_temp_dir().'/timone count (test).pdf';
    (new Process(['gs', '-q', '-dNOSAFER', '-sDEVICE=pdfwrite', '-o', $path, '-c', 'showpage showpage showpage']))->mustRun();

    try {
        expect(PdfPageMeasurer::pageCount($path))->toBe(3);
    } finally {
        @unlink($path);
    }
});
