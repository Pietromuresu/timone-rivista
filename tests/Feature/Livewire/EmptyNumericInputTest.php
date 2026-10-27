<?php

use App\Enums\UserRole;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Magazines\Edit as MagazineEdit;
use App\Livewire\Timone\ContentCreate;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;
use Livewire\Livewire;

/*
 * Fase 1, bug 2 (2026-10-26): svuotare un input numerico legato a una
 * proprietà Livewire tipizzata (`int`/`?int`) faceva esplodere la pagina —
 * Livewire, ricevendo '' su una proprietà intera, la "unset"-ta invece di
 * metterla a null (HandleComponents::setComponentPropertyAwareOfTypes()), e
 * il primo accesso successivo lanciava "must not be accessed before
 * initialization". Ogni test qui svuota un campo e verifica che il
 * componente resti vivo, con il valore a null e (dove serve) un errore di
 * validazione inline invece dell'eccezione.
 */

function emptyInputEditor(Magazine $magazine): User
{
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($magazine);

    return $user;
}

test('emptying the page count while creating an issue does not throw and shows an inline error on save', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(emptyInputEditor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('total_pages', '')
        ->assertOk()
        ->assertSet('total_pages', null)
        ->set('title', 'Numero di prova')
        ->call('save')
        ->assertHasErrors(['total_pages' => 'required'])
        ->assertNoRedirect();

    expect(Issue::count())->toBe(0);
});

test('the page count can be typed again after being emptied', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(emptyInputEditor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('total_pages', '')
        ->set('total_pages', '12')
        ->assertSet('total_pages', 12)
        ->set('title', 'Numero di prova')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Issue::first()->total_pages)->toBe(12);
});

test('choosing the empty "duplicate from" option does not throw', function () {
    $magazine = Magazine::factory()->create();

    Livewire::actingAs(emptyInputEditor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('duplicateFromIssueId', '')
        ->assertOk()
        ->assertSet('duplicateFromIssueId', null);
});

test('emptying the expected length of an article does not throw', function () {
    $issue = Issue::factory()->create(['total_pages' => 8]);

    Livewire::actingAs(emptyInputEditor($issue->magazine))->test(ContentCreate::class, ['issue' => $issue])
        ->call('toggleForm')
        ->set('expected_length', '')
        ->assertOk()
        ->assertSet('expected_length', null)
        ->set('section_id', '')
        ->assertSet('section_id', null);
});

// (Il test sulla select del vecchio pannello CoverSection è stato rimosso
// con il pannello stesso, Fase 2 del 2026-10-26.)

test('choosing "no editor" on the magazine edit form does not throw', function () {
    $magazine = Magazine::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)->test(MagazineEdit::class, ['magazine' => $magazine])
        ->set('redattore_id', '')
        ->assertOk()
        ->assertSet('redattore_id', null);
});
