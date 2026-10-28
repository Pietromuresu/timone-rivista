<?php

use App\Enums\ContentType;
use App\Enums\UserRole;
use App\Events\ContentCreated;
use App\Jobs\GenerateContentFileThumbnail;
use App\Livewire\Timone\BulkUpload;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use App\Models\User;
use App\Support\BulkContentImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// Revisione 2, Fase 3.2 (2026-10-28): caricamento massivo, un contenuto per
// file. Il browser manda un file alla volta (upload + importUploaded), qui
// simulato impostando $upload e chiamando importUploaded() per ogni file.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php. I casi
// con PDF veri richiedono ext-imagick (conteggio pagine): solo in Docker.

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    Event::fake([ContentCreated::class]);
});

function bulkImport($component, UploadedFile $file, string $type = 'articolo')
{
    return $component->set('upload', $file)->call('importUploaded', $type);
}

test('the title comes from the file name: no extension, underscores and dashes as spaces, single spaces', function () {
    expect(BulkContentImporter::titleFromFilename('servizio_moto-elettrica  v2.pdf'))->toBe('servizio moto elettrica v2')
        ->and(BulkContentImporter::titleFromFilename('Intervista__al - sindaco.JPG'))->toBe('Intervista al sindaco')
        ->and(BulkContentImporter::titleFromFilename('___.pdf'))->toBe('Senza titolo');
});

test('each image becomes an unassigned article of one page, with the file and its thumbnail queued', function () {
    $issue = reorderableIssue();
    $component = Livewire::actingAs(editorFor($issue))->test(BulkUpload::class, ['issue' => $issue]);

    bulkImport($component, UploadedFile::fake()->image('apertura_rubrica.png', 600, 800))
        ->assertReturned(fn ($result) => $result['ok'] === true && $result['title'] === 'apertura rubrica' && $result['occupancy'] === '1 pagina')
        ->assertDispatched('content-created');
    bulkImport($component, UploadedFile::fake()->image('foto-copertina.jpg', 600, 800))
        ->assertReturned(fn ($result) => $result['ok'] === true);

    $contents = Content::where('issue_id', $issue->id)->with(['article', 'files'])->orderBy('id')->get();

    expect($contents)->toHaveCount(2)
        ->and($contents->pluck('title')->all())->toBe(['apertura rubrica', 'foto copertina'])
        ->and($contents->every(fn ($c) => $c->type === ContentType::Articolo && $c->occupancy_twelfths === 12 && $c->files->count() === 1 && ! $c->isAssigned()))->toBeTrue()
        ->and($contents->first()->article->editorial_status->value)->toBe('da_scrivere');

    Queue::assertPushed(GenerateContentFileThumbnail::class, 2);
    Event::assertDispatched(ContentCreated::class, 2);
});

test('an ad batch creates real ads (they have a file) without client, full page', function () {
    $issue = reorderableIssue();
    $component = Livewire::actingAs(editorFor($issue))->test(BulkUpload::class, ['issue' => $issue]);

    bulkImport($component, UploadedFile::fake()->image('acme_autunno.png', 600, 800), 'pubblicita')
        ->assertReturned(fn ($result) => $result['ok'] === true);

    $content = Content::where('issue_id', $issue->id)->with('advertisement')->sole();

    expect($content->type)->toBe(ContentType::Pubblicita)
        ->and($content->isPlaceholder())->toBeFalse()
        ->and($content->advertisement->client)->toBeNull()
        ->and($content->advertisement->format->value)->toBe('pagina_intera')
        ->and($content->occupancy_twelfths)->toBe(12)
        ->and($content->displayLabel())->toBe('acme autunno');
});

test('the importer rejects wrong type and oversize files with the form messages (second check after Livewire)', function () {
    $issue = reorderableIssue();
    $reasonFor = function (UploadedFile $file) use ($issue) {
        try {
            BulkContentImporter::import($issue, ContentType::Articolo, $file, null);
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first();
        }

        return null;
    };

    expect($reasonFor(UploadedFile::fake()->create('appunti.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')))->toContain('Formato non accettato')
        ->and($reasonFor(UploadedFile::fake()->create('enorme.pdf', config('timone.upload_max_kb') + 1, 'application/pdf')))->toContain('supera il limite')
        ->and(Content::where('issue_id', $issue->id)->count())->toBe(0);
});

test('a rejected file returns its reason and does not block the next one', function () {
    $issue = reorderableIssue();
    $component = Livewire::actingAs(editorFor($issue))->test(BulkUpload::class, ['issue' => $issue]);

    // Rifiutato già dall'upload temporaneo di Livewire: al server non arriva nulla.
    bulkImport($component, UploadedFile::fake()->create('appunti.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->assertReturned(fn ($result) => $result['ok'] === false && $result['message'] !== '');
    bulkImport($component, UploadedFile::fake()->createWithContent('rotto.pdf', "%PDF-1.4\nnon un vero pdf"))
        ->assertReturned(fn ($result) => $result['ok'] === false);
    bulkImport($component, UploadedFile::fake()->image('buona.png'))
        ->assertReturned(fn ($result) => $result['ok'] === true);

    expect(Content::where('issue_id', $issue->id)->pluck('title')->all())->toBe(['buona']);
});

test('an unreadable pdf is rejected with a clear reason', function () {
    $issue = reorderableIssue();
    $component = Livewire::actingAs(editorFor($issue))->test(BulkUpload::class, ['issue' => $issue]);

    bulkImport($component, UploadedFile::fake()->createWithContent('rotto.pdf', "%PDF-1.4\nnon un vero pdf"))
        ->assertReturned(fn ($result) => $result['ok'] === false && str_contains($result['message'], 'Impossibile leggere'));

    expect(Content::where('issue_id', $issue->id)->count())->toBe(0);
});

test('a duplicate title is created anyway with a " (2)" suffix and reported', function () {
    $issue = reorderableIssue();
    Content::factory()->create(['issue_id' => $issue->id, 'title' => 'Editoriale', 'type' => 'articolo', 'occupancy_twelfths' => 12]);
    $component = Livewire::actingAs(editorFor($issue))->test(BulkUpload::class, ['issue' => $issue]);

    bulkImport($component, UploadedFile::fake()->image('editoriale.png'))
        ->assertReturned(fn ($result) => $result['ok'] === true && $result['title'] === 'editoriale (2)' && $result['duplicateOf'] === 'editoriale');
    bulkImport($component, UploadedFile::fake()->image('editoriale.png'))
        ->assertReturned(fn ($result) => $result['title'] === 'editoriale (3)');
});

test('a user who cannot edit the issue gets 403 and nothing is created (server-side check)', function () {
    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    Livewire::actingAs($outsider)->test(BulkUpload::class, ['issue' => $issue])
        ->set('upload', UploadedFile::fake()->image('intruso.png'))
        ->call('importUploaded', 'articolo')
        ->assertForbidden();

    expect(Content::where('issue_id', $issue->id)->count())->toBe(0);
});

test('a temporary upload rejected by the server leaves a readable reason for the browser', function () {
    $issue = reorderableIssue();
    $max = config('timone.upload_max_kb');

    Livewire::actingAs(editorFor($issue))->test(BulkUpload::class, ['issue' => $issue])
        ->call('_uploadErrored', 'upload', json_encode(['upload' => ["Il campo upload non può superare {$max} kilobyte."]]), false)
        ->assertSet('uploadError', fn ($message) => str_contains($message, 'supera il limite'))
        ->assertDispatched('upload:errored');
});

test('the timone shows the bulk upload panel to editors', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('bulk-upload');
});

test('a multi-page pdf becomes one content with as many whole pages and one file per page', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker) — vedi HANDOFF.md.');
    }

    $issue = reorderableIssue();
    $component = Livewire::actingAs(editorFor($issue))->test(BulkUpload::class, ['issue' => $issue]);
    $pdf = multiPagePdfBytes([[mmToPt(210), mmToPt(270)], [mmToPt(210), mmToPt(270)], [mmToPt(210), mmToPt(270)]]);

    bulkImport($component, UploadedFile::fake()->createWithContent('servizio_lungo.pdf', $pdf), 'pubblicita')
        ->assertReturned(fn ($result) => $result['ok'] === true && $result['occupancy'] === '3 pagine');

    $content = Content::where('issue_id', $issue->id)->with('files')->sole();

    expect($content->occupancy_twelfths)->toBe(36)
        ->and($content->files)->toHaveCount(3)
        ->and($content->isPlaceholder())->toBeFalse();
});

test('the temporary upload is deleted after each file, imported or rejected', function () {
    $issue = reorderableIssue();
    $component = Livewire::actingAs(editorFor($issue))->test(BulkUpload::class, ['issue' => $issue]);

    $component->set('upload', UploadedFile::fake()->image('buona.png'));
    $temporary = $component->get('upload')->getRealPath();
    expect(file_exists($temporary))->toBeTrue();

    $component->call('importUploaded', 'articolo')->assertReturned(fn ($result) => $result['ok'] === true);

    expect(file_exists($temporary))->toBeFalse();
});
