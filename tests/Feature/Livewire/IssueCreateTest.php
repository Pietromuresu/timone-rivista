<?php

use App\Enums\PageContentType;
use App\Enums\UserRole;
use App\Livewire\Issues\Create;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;
use Livewire\Livewire;

test('an editor with access can create an issue and its pages are auto-generated', function () {
    $magazine = Magazine::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($magazine);

    Livewire::actingAs($user)->test(Create::class, ['magazine' => $magazine])
        ->set('title', 'Dicembre 2026')
        ->set('total_pages', 8)
        ->call('save')
        ->assertRedirect();

    $issue = Issue::where('title', 'Dicembre 2026')->first();

    expect($issue)->not->toBeNull()
        ->and($issue->status->value)->toBe('bozza')
        ->and($issue->pages()->count())->toBe(8)
        ->and($issue->pages()->pluck('content_type')->unique()->all())->toBe([PageContentType::Bianca]);
});

test('an admin can create an issue on any magazine', function () {
    $magazine = Magazine::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)->test(Create::class, ['magazine' => $magazine])
        ->set('title', 'Gennaio 2027')
        ->set('total_pages', 4)
        ->call('save')
        ->assertRedirect();

    expect(Issue::where('title', 'Gennaio 2027')->exists())->toBeTrue();
});

test('a redattore without access to the magazine cannot open the creation form', function () {
    $magazine = Magazine::factory()->create();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    Livewire::actingAs($outsider)->test(Create::class, ['magazine' => $magazine])
        ->assertForbidden();
});

test('a sola lettura user cannot open the creation form even with magazine access', function () {
    $magazine = Magazine::factory()->create();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($magazine);

    Livewire::actingAs($user)->test(Create::class, ['magazine' => $magazine])
        ->assertForbidden();
});

test('a guest cannot open the creation form', function () {
    $magazine = Magazine::factory()->create();

    Livewire::test(Create::class, ['magazine' => $magazine])->assertForbidden();
});

test('the title is required', function () {
    $magazine = Magazine::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($magazine);

    Livewire::actingAs($user)->test(Create::class, ['magazine' => $magazine])
        ->set('title', '')
        ->set('total_pages', 8)
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

test('duplicating structure from a previous issue copies page content types by position', function () {
    $magazine = Magazine::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($magazine);

    $source = Issue::factory()->create(['magazine_id' => $magazine->id, 'total_pages' => 6]);
    $source->pages()->where('position', 1)->update(['content_type' => PageContentType::Editoriale]);
    $source->pages()->where('position', 2)->update(['content_type' => PageContentType::Pubblicita]);

    Livewire::actingAs($user)->test(Create::class, ['magazine' => $magazine])
        ->set('duplicateFromIssueId', $source->id)
        ->assertSet('total_pages', 6) // pre-compilato dalla issue scelta
        ->set('title', 'Numero duplicato')
        ->call('save')
        ->assertRedirect();

    $issue = Issue::where('title', 'Numero duplicato')->first();

    expect($issue->pages()->where('position', 1)->first()->content_type)->toBe(PageContentType::Editoriale)
        ->and($issue->pages()->where('position', 2)->first()->content_type)->toBe(PageContentType::Pubblicita)
        ->and($issue->pages()->where('position', 3)->first()->content_type)->toBe(PageContentType::Bianca);

    // La sorgente non viene toccata né i suoi contenuti/pubblicità copiati:
    // solo il tipo pagina, come da progetto.
    expect($source->fresh()->total_pages)->toBe(6);
});

test('duplicating from a shorter previous issue leaves extra pages blank', function () {
    $magazine = Magazine::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($magazine);

    $source = Issue::factory()->create(['magazine_id' => $magazine->id, 'total_pages' => 2]);
    $source->pages()->where('position', 1)->update(['content_type' => PageContentType::Mista]);

    Livewire::actingAs($user)->test(Create::class, ['magazine' => $magazine])
        ->set('duplicateFromIssueId', $source->id)
        ->set('title', 'Numero più lungo')
        ->set('total_pages', 5)
        ->call('save');

    $issue = Issue::where('title', 'Numero più lungo')->first();

    expect($issue->pages()->where('position', 1)->first()->content_type)->toBe(PageContentType::Mista)
        ->and($issue->pages()->where('position', 5)->first()->content_type)->toBe(PageContentType::Bianca);
});
