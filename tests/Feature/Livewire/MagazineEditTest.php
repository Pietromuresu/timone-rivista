<?php

use App\Enums\UserRole;
use App\Livewire\Magazines\Edit;
use App\Models\Magazine;
use App\Models\User;
use Livewire\Livewire;

test('an admin can assign a redattore to a magazine', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $magazine = Magazine::factory()->create(['redattore_id' => null]);

    Livewire::actingAs($admin)->test(Edit::class, ['magazine' => $magazine])
        ->set('redattore_id', $redattore->id)
        ->call('save')
        ->assertRedirect();

    expect($magazine->fresh()->redattore_id)->toBe($redattore->id);
});

test('assigning a redattore also grants them magazine access, without revoking anyone else\'s', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $otherUser = User::factory()->create(['role' => UserRole::Commerciale]);
    $magazine = Magazine::factory()->create(['redattore_id' => null]);
    $magazine->users()->attach($otherUser->id);

    expect($redattore->fresh()->canAccessMagazine($magazine))->toBeFalse();

    Livewire::actingAs($admin)->test(Edit::class, ['magazine' => $magazine])
        ->set('redattore_id', $redattore->id)
        ->call('save');

    expect($redattore->fresh()->canAccessMagazine($magazine))->toBeTrue()
        ->and($otherUser->fresh()->canAccessMagazine($magazine))->toBeTrue();
});

test('a redattore field can be cleared back to none', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $magazine = Magazine::factory()->create(['redattore_id' => $redattore->id]);

    Livewire::actingAs($admin)->test(Edit::class, ['magazine' => $magazine])
        ->set('redattore_id', null)
        ->call('save');

    expect($magazine->fresh()->redattore_id)->toBeNull();
});

test('a non-admin cannot open or save the magazine edit form', function () {
    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $magazine = Magazine::factory()->create();

    $response = Livewire::actingAs($redattore)->test(Edit::class, ['magazine' => $magazine]);

    $response->assertForbidden();
});

test('a guest cannot open the magazine edit form', function () {
    $magazine = Magazine::factory()->create();

    Livewire::test(Edit::class, ['magazine' => $magazine])
        ->assertForbidden();
});

// Fase 2 (2026-10-26): battente_counted_in_total non è più nel modulo.
test('editing preserves the ad threshold set at creation', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $magazine = Magazine::factory()->create([
        'ad_threshold_percentage' => 42.5,
    ]);

    Livewire::actingAs($admin)->test(Edit::class, ['magazine' => $magazine])
        ->assertSet('ad_threshold_percentage', '42.50')
        ->set('name', $magazine->name)
        ->call('save');

    expect((float) $magazine->fresh()->ad_threshold_percentage)->toBe(42.5);
});
