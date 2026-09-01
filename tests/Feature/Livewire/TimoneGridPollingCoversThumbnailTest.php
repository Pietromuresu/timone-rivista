<?php

use App\Enums\ThumbnailStatus;
use App\Livewire\Timone\Grid;
use App\Models\PageFile;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

test('a poll (checkForUpdates + re-render) picks up a thumbnail status changed directly in the database', function () {
    // Chiude esplicitamente il dubbio sollevato dal punto 1.5 del prompt
    // originale (2026-08-31) e verificato di nuovo dopo il passaggio a
    // wire:poll/checkForUpdates() (2026-09-01): il polling non deve essere
    // una toppa che nasconde un bug nel broadcasting, e il controllo
    // leggero di checkForUpdates() non deve nascondere un cambiamento
    // reale. `$pageFile->update()` da solo NON tocca
    // issues.pages_updated_at (nessun observer sul model PageFile) — nella
    // realtà è GeneratePageFileThumbnail::handle() a chiamare
    // Issue::markPagesUpdated() insieme al broadcast PageFileUploaded (vedi
    // HANDOFF.md); qui lo si richiama esplicitamente per simulare "il job
    // ha fatto il suo lavoro", senza eseguire davvero la conversione PDF.
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
    $issue->markPagesUpdated();

    // checkForUpdates() + il re-render che Livewire::test()->call() esegue
    // comunque dopo ogni chiamata: stesso identico percorso del poll reale
    // lato browser (resources/js/app.js, store realtimeFallback +
    // wire:poll.1s in grid.blade.php).
    $html = $component->call('checkForUpdates')->html();

    expect($html)->not->toContain('⏳ PDF')
        ->toContain('object-cover');
});

test('a poll does NOT re-render (and so does not pick up unrelated changes) when pages_updated_at has not moved', function () {
    // Prova diretta del punto 4 della richiesta ("non ricaricare sempre
    // tutto"): senza un markPagesUpdated() a segnalarlo, checkForUpdates()
    // deve chiamare skipRender() e l'HTML restituito deve restare quello
    // del render precedente, anche se nel frattempo qualcosa È cambiato
    // fisicamente nel database (scenario volutamente "innaturale" — nella
    // realtà ogni mutazione passa sempre da markPagesUpdated() insieme al
    // proprio broadcast — usato qui solo per dimostrare che il ramo
    // "nessun cambiamento" salta davvero il lavoro pesante, non solo per
    // coincidenza).
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
    // Nessun markPagesUpdated() qui, di proposito.

    $html = $component->call('checkForUpdates')->html();

    expect($html)->toContain('⏳ PDF');
});
