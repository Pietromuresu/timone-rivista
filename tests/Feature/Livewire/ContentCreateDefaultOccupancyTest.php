<?php

use App\Events\ContentAssigned;
use App\Events\ContentCreated;
use App\Events\PageGroupsChanged;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\ContentEdit;
use App\Models\Content;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// 2026-10-30: nel modulo di creazione l'occupazione parte da 1 pagina
// intera, ma il predefinito non batte i dati reali (PDF di più pagine,
// formato della pubblicità) finché l'utente non lo tocca.
// reorderableIssue()/editorFor() in TimoneGridReorderTest.php,
// placedArticle() in ContentEditTest.php.

beforeEach(function () {
    Queue::fake();
    Event::fake([ContentCreated::class, ContentAssigned::class, PageGroupsChanged::class]);
});

function threePagePdfForDefault(): string
{
    return multiPagePdfBytes(array_fill(0, 3, [mmToPt(210), mmToPt(270)]));
}

test('the form opens with 1 whole page and no fraction, already shown as the total', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->assertSet('whole_pages', 1)
        ->assertSet('fraction_twelfths', '0')
        ->assertSet('occupancyTouched', false)
        ->assertSee('Occupa 1 pagina');
});

test('"Carica materiale" from a page opens with the same default', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('openForPage', $issue->pages()->where('position', 2)->value('id'))
        ->assertSet('whole_pages', 1)
        ->assertSet('fraction_twelfths', '0')
        ->assertSee('Occupa 1 pagina');
});

test('reopening the form after a manual change goes back to the default, untouched', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('whole_pages', 3)
        ->assertSet('occupancyTouched', true)
        ->call('toggleForm')
        ->call('toggleForm')
        ->assertSet('whole_pages', 1)
        ->assertSet('occupancyTouched', false);
});

test('saving without touching the occupancy creates a 1-page article', function () {
    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Una pagina di default')
        ->call('save')
        ->assertHasNoErrors();

    expect(Content::where('title', 'Una pagina di default')->value('occupancy_twelfths'))->toBe(12);
});

test('the ad format replaces the default, and the format stays mandatory', function () {
    $issue = reorderableIssue();

    $component = Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('client', 'Acme')
        ->assertSet('format', '')
        ->call('save')
        ->assertHasErrors('format');

    $component->set('format', 'mezza_pagina_orizzontale')
        ->assertSet('whole_pages', 0)
        ->assertSet('fraction_twelfths', '6')
        ->call('save')
        ->assertHasNoErrors();

    expect(Content::where('type', 'pubblicita')->value('occupancy_twelfths'))->toBe(6);
});

test('the edit modal still loads the occupancy of the content, not the default', function () {
    $issue = reorderableIssue();
    $content = placedArticle($issue, 6, 1, 'Mezza pagina');

    Livewire::actingAs(editorFor($issue))->test(ContentEdit::class, ['issue' => $issue])
        ->call('open', $content->id)
        ->assertSet('whole_pages', 0)
        ->assertSet('fraction_twelfths', '6')
        ->assertSee('Occupa 1/2 di pagina');
});

test('a 3-page pdf replaces the untouched default with 3 whole pages', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker).');
    }

    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Servizio di tre pagine')
        ->set('file', UploadedFile::fake()->createWithContent('servizio.pdf', threePagePdfForDefault()))
        ->assertSet('whole_pages', 3)
        ->assertSet('fraction_twelfths', '0')
        ->call('save')
        ->assertHasNoErrors();

    expect(Content::where('title', 'Servizio di tre pagine')->value('occupancy_twelfths'))->toBe(36);
});

test('a 3-page pdf after the user wrote 2 keeps 2 and gives the usual error', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('ext-imagick non disponibile su questa macchina (solo dentro Docker).');
    }

    $issue = reorderableIssue();

    Livewire::actingAs(editorFor($issue))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('title', 'Incoerente')
        ->set('whole_pages', 2)
        ->set('file', UploadedFile::fake()->createWithContent('servizio.pdf', threePagePdfForDefault()))
        ->assertSet('whole_pages', 2)
        ->call('save')
        ->assertHasErrors('occupancy');

    expect(Content::count())->toBe(0);
});
