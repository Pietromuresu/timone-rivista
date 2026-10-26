<?php

use App\Livewire\Timone\ContentCreate;
use App\Support\Uploads\RequestRootSignedUploadUrl;
use App\Support\Uploads\UploadLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/*
 * Fase 1, bug 9 (2026-10-26): "in produzione non si caricano media".
 * reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.
 */

test('the upload url is signed on the real request address even when APP_URL differs', function () {
    // Come in produzione con APP_URL sbagliato: AppServiceProvider forza la root su APP_URL.
    config(['app.url' => 'http://localhost']);
    URL::forceRootUrl('http://localhost');

    $this->app->instance('request', Request::create('https://timone.intranet.example/riviste'));
    URL::setRequest($this->app['request']);

    $url = (new RequestRootSignedUploadUrl)->forLocal();

    expect($url)->toStartWith('https://timone.intranet.example/')
        ->and(URL::hasValidSignature(Request::create($url, 'POST')))->toBeTrue();

    // Le altre URL dell'app continuano a seguire l'host di APP_URL.
    expect(url('/riviste'))->toContain('://localhost/riviste');
});

test('the container hands Livewire the request-root signer', function () {
    // Durante i test Livewire sostituisce l'istanza con una propria (niente
    // URL S3 veri): la togliamo per vedere il binding di AppServiceProvider.
    app()->forgetInstance(\Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl::class);
    (new \App\Providers\AppServiceProvider(app()))->register();

    expect(app(\Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl::class))
        ->toBeInstanceOf(RequestRootSignedUploadUrl::class);
});

test('an upload rejected before validation is logged with context and explained to the user', function () {
    Log::spy();

    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->call('_uploadErrored', 'file', null, false)
        ->assertHasErrors('file')
        ->assertSee('Il file non è arrivato al server');

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($issue) {
        return $message === 'upload_failed'
            && $context['issue_id'] === $issue->id
            && array_key_exists('APP_URL', $context['environment'])
            && array_key_exists('Limiti PHP', $context['environment']);
    })->once();
});

test('a validation error from the temporary upload keeps the Livewire message and is logged too', function () {
    Log::spy();

    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->call('_uploadErrored', 'file', json_encode(['errors' => ['files.0' => ['Il file è troppo grande.']]]), false)
        ->assertHasErrors(['file'])
        ->assertSee('Il file è troppo grande.');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => $message === 'upload_failed')->once();
});

test('the drop zone knows the effective size limit', function () {
    $issue = reorderableIssue();
    $limit = UploadLimits::effective(ContentCreate::FILE_MAX_KILOBYTES);

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->assertSeeHtml('maxBytes: '.$limit['bytes'])
        ->assertSee('max '.UploadLimits::humanMegabytes($limit['bytes']));
});

test('the upload diagnostic command prints every check', function () {
    $this->artisan('timone:diagnose-upload', ['--no-pdf' => true])
        ->expectsOutputToContain('APP_URL')
        ->expectsOutputToContain('Limiti PHP')
        ->expectsOutputToContain('Disco temporaneo Livewire')
        ->expectsOutputToContain('Disco finale dei file');
});
