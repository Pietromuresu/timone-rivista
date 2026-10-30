<?php

use App\Events\ContentCreated;
use App\Livewire\Timone\ContentCreate;
use App\Models\Content;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Bug 5 (2026-10-12): l'occupazione di pagina di un contenuto deve essere
// obbligatoria — oggi un articolo non ha proprio un campo occupazione (si
// prende "tutto lo spazio libero" della pagina al drop) e per la
// pubblicità la percentuale manuale è opzionale, quindi i calcoli di
// carico/foliazione lavorano su valori impliciti.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentCreated::class]));

// Dal 2026-10-30 il modulo parte da 1 pagina intera: il caso è l'utente
// che svuota il campo.
test('bug 5: an article cannot be created with an empty page occupancy', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'articolo')
        ->set('title', 'Articolo senza occupazione')
        ->set('whole_pages', null)
        ->call('save')
        ->assertHasErrors('occupancy');

    expect(Content::count())->toBe(0);
});

// Per la pubblicità l'occupazione È il formato (PageOccupancy::forAdFormat()):
// prima partiva preselezionato su "pagina intera" in silenzio, ora va scelto.
test('bug 5: an advertisement cannot be created without choosing its format, which is its page occupancy', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('type', 'pubblicita')
        ->set('title', 'Inserzione senza occupazione')
        ->set('client', 'Acme')
        ->call('save')
        ->assertHasErrors(['format' => 'required_if']);

    expect(Content::count())->toBe(0);
});
