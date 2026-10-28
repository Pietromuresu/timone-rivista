<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

// Revisione 2, Fase 4.3 (2026-10-28): l'auto-eliminazione dal profilo è
// stata tolta (prima: «user can delete their account», «correct password
// must be provided to delete account»). Eliminare un utente lo fa solo un
// admin: tests/Feature/Livewire/DeletionTest.php.
test('the profile no longer offers account self-deletion', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')->assertOk()->assertDontSee('Elimina account')->assertDontSee('Delete Account');
    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertStatus(405);

    $this->assertNotNull($user->fresh());
});
