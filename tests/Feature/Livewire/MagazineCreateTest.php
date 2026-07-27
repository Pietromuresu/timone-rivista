<?php

use App\Enums\UserRole;
use App\Livewire\Magazines\Create;
use App\Models\Magazine;
use App\Models\User;
use Livewire\Livewire;

test('an admin can create a magazine', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)->test(Create::class)
        ->set('name', 'Motori Elettrici')
        ->set('periodicity', 'mensile')
        ->set('color', '#112233')
        ->set('ad_threshold_percentage', '25')
        ->set('notes', 'Rivista di prova')
        ->call('save')
        ->assertRedirect();

    $magazine = Magazine::where('name', 'Motori Elettrici')->first();

    expect($magazine)->not->toBeNull()
        ->and($magazine->slug)->toBe('motori-elettrici')
        ->and($magazine->periodicity->value)->toBe('mensile')
        ->and((float) $magazine->ad_threshold_percentage)->toBe(25.0);
});

test('an empty ad threshold is stored as null', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)->test(Create::class)
        ->set('name', 'Casa Serena')
        ->call('save');

    $magazine = Magazine::where('name', 'Casa Serena')->first();

    expect($magazine->ad_threshold_percentage)->toBeNull();
});

test('the name is required', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)->test(Create::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('a redattore cannot create a magazine', function () {
    $user = User::factory()->create(['role' => UserRole::Redattore]);

    Livewire::actingAs($user)->test(Create::class)
        ->assertForbidden();
});

test('a guest cannot access the magazine creation form', function () {
    Livewire::test(Create::class)->assertForbidden();
});
