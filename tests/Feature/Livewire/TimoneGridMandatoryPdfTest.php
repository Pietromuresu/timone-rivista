<?php

use App\Enums\PageStatus;
use App\Events\PageStatusUpdated;
use App\Livewire\Timone\Grid;
use App\Models\PageFile;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.
// Nessun ext-imagick richiesto qui: solo lo stato in database (presenza o
// meno di una riga page_files), non la generazione reale di un PDF.
// Event::fake() su ogni transizione che deve riuscire (non sui casi
// bloccati, dove broadcast() non viene mai chiamato): stesso motivo di
// TimoneGridPageStatusTest, un broadcast() reale verso Reverb fallirebbe
// in locale senza il servizio in ascolto.

test('a page without a pdf cannot be marked ok stampa', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, PageStatus::OkStampa->value)
        ->assertHasErrors('pdfRequired');

    expect($page->fresh()->status)->toBe(PageStatus::DaAssegnare);
});

test('a page with a pdf can be marked ok stampa', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    PageFile::factory()->for($page)->create();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, PageStatus::OkStampa->value)
        ->assertHasNoErrors();

    expect($page->fresh()->status)->toBe(PageStatus::OkStampa);
});

test('a page can still move through every other status without a pdf', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('changePageStatus', $page->id, PageStatus::Revisionata->value)
        ->assertHasNoErrors();

    expect($page->fresh()->status)->toBe(PageStatus::Revisionata);
});

test('bulk status change to ok stampa skips pages without a pdf and reports how many', function () {
    Event::fake([PageStatusUpdated::class]);

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $withPdf = $issue->pages()->where('position', 1)->first();
    $withoutPdf = $issue->pages()->where('position', 2)->first();
    PageFile::factory()->for($withPdf)->create();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('toggleSelectionMode')
        ->call('togglePageSelection', $withPdf->id)
        ->call('togglePageSelection', $withoutPdf->id)
        ->call('bulkChangeStatus', PageStatus::OkStampa->value)
        ->assertSee('1 pagina senza PDF ignorata');

    expect($withPdf->fresh()->status)->toBe(PageStatus::OkStampa)
        ->and($withoutPdf->fresh()->status)->toBe(PageStatus::DaAssegnare);
});

test('the warnings panel flags an already-approved page left without a pdf', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 3)->first();
    $page->update(['status' => PageStatus::Revisionata]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSee('⚠️ Avvisi')
        ->assertSee('ancora un PDF caricato');
});
