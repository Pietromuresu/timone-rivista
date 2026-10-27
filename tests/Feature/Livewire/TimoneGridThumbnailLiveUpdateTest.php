<?php

use App\Enums\ContentType;
use App\Enums\ThumbnailStatus;
use App\Livewire\Timone\Grid;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\PageFile;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

// Punto 4 del prompt (2026-09-02): l'anteprima principale sulla card viene
// ora da Page::previewFile(), che guarda prima i contenuti assegnati alla
// pagina (ContentFile) — questo test sostituisce la versione precedente
// (basata su PageFile e sul listener Grid::onPageFileUploaded(), rimossi
// insieme al caricamento diretto su pagina) con l'equivalente per il nuovo
// percorso, riusando il listener Grid::onContentFileUploaded() già
// esistente (Fase 4.1, invariato da questa modifica).
test('the card switches from pending to the ready thumbnail without a page reload when ContentFileUploaded arrives', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $content = Content::create(['issue_id' => $issue->id, 'type' => ContentType::Articolo, 'title' => 'Articolo di prova', 'occupancy_twelfths' => 12]);
    Article::create(['content_id' => $content->id, 'editorial_status' => 'da_scrivere']);
    $contentFile = ContentFile::factory()->for($content)->create(['thumbnail_status' => ThumbnailStatus::Pending]);
    $page->contents()->attach($content->id, ['occupied_twelfths' => 12, 'content_file_id' => $contentFile->id]);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    expect($component->html())->toContain('⏳ PDF')
        ->and($component->html())->not->toContain('object-cover');

    // Il job (in un altro processo/worker) completa il lavoro sul DB...
    $contentFile->update([
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_path' => "contents/{$content->id}/thumb.png",
    ]);

    // ...e trasmette l'evento: il listener a corpo vuoto in Grid.php si
    // affida al prossimo render() per ripescare lo stato aggiornato dal DB,
    // qui simulato chiamando direttamente il metodo agganciato all'evento
    // (stesso identico effetto del vero arrivo via Echo lato browser).
    $component->call('onContentFileUploaded');

    expect($component->html())
        ->not->toContain('⏳ PDF')
        ->toContain('object-cover');
});

// Percorso legacy (PageFile diretto, pagine non ancora migrate al Punto 8):
// Page::previewFile() ricade su di lui solo quando nessun contenuto
// assegnato ha un file — verificato qui che l'anteprima resti comunque
// visibile, i dati esistenti non vengono persi da questa modifica. Nessuna
// pretesa di "live update" per questo caso: nessun nuovo lavoro dispatcha
// più GeneratePageFileThumbnail (rimosso col caricamento diretto), quindi
// un PageFile legacy che cambia stato in produzione può arrivare solo da
// un job già in coda da prima di questa modifica — scenario non
// riproducibile qui, la sola cosa da verificare è che il rendering di uno
// stato Ready esistente funzioni ancora correttamente.
test('a legacy direct PageFile (not yet migrated) still renders correctly', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    PageFile::factory()->for($page)->create([
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_path' => "pages/{$page->id}/thumb.png",
    ]);

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    expect($component->html())
        ->not->toContain('⏳ PDF')
        ->toContain('object-cover');
});
