<?php

use App\Enums\ThumbnailStatus;
use App\Livewire\Timone\Grid;
use App\Models\PageFile;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

test('a poll (pollRefresh + re-render) picks up a thumbnail status changed directly in the database', function () {
    // Chiude esplicitamente il dubbio sollevato dal punto 1.5 del prompt:
    // il polling non deve essere una toppa che nasconde un bug nel
    // broadcasting — qui si verifica che sia render() stesso (chiamato ad
    // ogni richiesta Livewire, poll compreso) a ripescare lo stato
    // aggiornato, non un meccanismo dedicato che andrebbe duplicato. Non
    // simula neppure l'evento PageFileUploaded (già coperto da
    // TimoneGridThumbnailLiveUpdateTest.php): qui il "client" non riceve
    // NESSUN segnale, il solo prossimo giro di render() basta.
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $pageFile = PageFile::factory()->for($page)->create([
        'thumbnail_status' => ThumbnailStatus::Pending,
    ]);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);
    expect($component->html())->toContain('⏳ PDF');

    $pageFile->update([
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_path' => "pages/{$page->id}/thumb.png",
    ]);

    // pollRefresh() + il re-render che Livewire::test()->call() esegue
    // comunque dopo ogni chiamata: stesso identico percorso del poll reale
    // lato browser (resources/js/app.js, store realtimeFallback).
    $html = $component->call('pollRefresh')->html();

    expect($html)->not->toContain('⏳ PDF')
        ->toContain('object-cover');
});
