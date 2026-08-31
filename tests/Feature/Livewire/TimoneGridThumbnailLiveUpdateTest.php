<?php

use App\Enums\ThumbnailStatus;
use App\Livewire\Timone\Grid;
use App\Models\PageFile;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

test('the card switches from pending to the ready thumbnail without a page reload when PageFileUploaded arrives', function () {
    // Riproduce lato Livewire::test() quello che nel browser succede a colpi
    // di broadcast: il job gira in un worker separato e aggiorna il DB, poi
    // trasmette PageFileUploaded (senza ->toOthers(), Punto 1bis in HANDOFF.md)
    // — questo test simula esattamente quell'arrivo, senza toccare il file
    // reale/ext-imagick, per isolare "il componente si aggiorna quando
    // l'evento arriva" da "il job genera davvero la miniatura" (già coperto
    // da GeneratePageFileThumbnailTest.php).
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $pageFile = PageFile::factory()->for($page)->create([
        'thumbnail_status' => ThumbnailStatus::Pending,
    ]);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    expect($component->html())->toContain('⏳ PDF')
        ->and($component->html())->not->toContain('object-cover');

    // Il job (in un altro processo/worker) completa il lavoro sul DB...
    $pageFile->update([
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_path' => "pages/{$page->id}/thumb.png",
    ]);

    // ...e trasmette l'evento: il listener a corpo vuoto in Grid.php si
    // affida al prossimo render() per ripescare lo stato aggiornato dal DB,
    // qui simulato chiamando direttamente il metodo agganciato all'evento
    // (stesso identico effetto del vero arrivo via Echo lato browser).
    $component->call('onPageFileUploaded');

    expect($component->html())
        ->not->toContain('⏳ PDF')
        ->toContain('object-cover');
});
