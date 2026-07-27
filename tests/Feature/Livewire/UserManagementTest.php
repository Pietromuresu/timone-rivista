<?php

use App\Enums\UserRole;
use App\Livewire\Users\Create;
use App\Livewire\Users\Edit;
use App\Livewire\Users\Index;
use App\Models\Magazine;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('an admin can view the user list', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->count(2)->create();

    Livewire::actingAs($admin)->test(Index::class)
        ->assertOk()
        ->assertSee($admin->name);
});

test('a non admin cannot view the user list', function () {
    $user = User::factory()->create(['role' => UserRole::Redattore]);

    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
});

test('a guest cannot view the user list', function () {
    Livewire::test(Index::class)->assertForbidden();
});

test('an admin can create a user with magazine access', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $magazineA = Magazine::factory()->create();
    $magazineB = Magazine::factory()->create();

    Livewire::actingAs($admin)->test(Create::class)
        ->set('name', 'Nuova Redattrice')
        ->set('email', 'redattrice@timone.test')
        ->set('password', 'password123')
        ->set('role', 'redattore')
        ->set('magazineIds', [$magazineA->id])
        ->call('save')
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'redattrice@timone.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Redattore)
        ->and(Hash::check('password123', $user->password))->toBeTrue()
        ->and($user->canAccessMagazine($magazineA))->toBeTrue()
        ->and($user->canAccessMagazine($magazineB))->toBeFalse();
});

test('creating a user requires a unique email', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $existing = User::factory()->create(['email' => 'preso@timone.test']);

    Livewire::actingAs($admin)->test(Create::class)
        ->set('name', 'Duplicato')
        ->set('email', 'preso@timone.test')
        ->set('password', 'password123')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

test('a non admin cannot create a user', function () {
    $user = User::factory()->create(['role' => UserRole::Redattore]);

    Livewire::actingAs($user)->test(Create::class)->assertForbidden();
});

test('an admin can edit a user role and magazine access', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $magazineA = Magazine::factory()->create();
    $magazineB = Magazine::factory()->create();
    $target = User::factory()->create(['role' => UserRole::Redattore]);
    $target->magazines()->attach($magazineA);

    Livewire::actingAs($admin)->test(Edit::class, ['user' => $target])
        ->set('role', 'commerciale')
        ->set('magazineIds', [$magazineB->id])
        ->call('save')
        ->assertRedirect(route('users.index'));

    $target->refresh();

    expect($target->role)->toBe(UserRole::Commerciale)
        ->and($target->canAccessMagazine($magazineA))->toBeFalse()
        ->and($target->canAccessMagazine($magazineB))->toBeTrue();
});

test('leaving the password blank on edit keeps the existing password', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $target = User::factory()->create(['password' => Hash::make('original-password')]);
    $originalHash = $target->password;

    Livewire::actingAs($admin)->test(Edit::class, ['user' => $target->fresh()])
        ->set('password', '')
        ->call('save');

    expect($target->fresh()->password)->toBe($originalHash);
});

test('filling the password on edit changes it', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $target = User::factory()->create();

    Livewire::actingAs($admin)->test(Edit::class, ['user' => $target->fresh()])
        ->set('password', 'brand-new-password')
        ->call('save');

    expect(Hash::check('brand-new-password', $target->fresh()->password))->toBeTrue();
});

test('a non admin cannot edit a user', function () {
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $target = User::factory()->create();

    Livewire::actingAs($user)->test(Edit::class, ['user' => $target])->assertForbidden();
});

test('a guest cannot edit a user', function () {
    $target = User::factory()->create();

    Livewire::test(Edit::class, ['user' => $target])->assertForbidden();
});
