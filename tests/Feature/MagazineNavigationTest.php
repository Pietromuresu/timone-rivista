<?php

use App\Enums\IssueStatus;
use App\Enums\UserRole;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;

test('a guest is redirected to login from every navigation route', function () {
    $magazine = Magazine::factory()->create();
    $issue = Issue::factory()->create(['magazine_id' => $magazine->id]);

    $this->get('/riviste')->assertRedirect('/login');
    $this->get(route('magazines.show', $magazine))->assertRedirect('/login');
    $this->get(route('issues.show', [$magazine, $issue]))->assertRedirect('/login');
});

test('the magazine index only lists magazines the user is attached to', function () {
    $accessible = Magazine::factory()->create(['name' => 'Accessibile']);
    Magazine::factory()->create(['name' => 'Non accessibile']);

    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($accessible);

    $this->actingAs($user)
        ->get('/riviste')
        ->assertOk()
        ->assertSee('Accessibile')
        ->assertDontSee('Non accessibile');
});

test('an admin sees every magazine on the index without being attached', function () {
    Magazine::factory()->create(['name' => 'Rivista Uno']);
    Magazine::factory()->create(['name' => 'Rivista Due']);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get('/riviste')
        ->assertOk()
        ->assertSee('Rivista Uno')
        ->assertSee('Rivista Due');
});

test('a user without access to a magazine is forbidden from viewing it', function () {
    $magazine = Magazine::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Redattore]);

    $this->actingAs($user)
        ->get(route('magazines.show', $magazine))
        ->assertForbidden();
});

test('a user with access sees the active and archived issues of a magazine', function () {
    $magazine = Magazine::factory()->create();
    $active = Issue::factory()->create([
        'magazine_id' => $magazine->id,
        'title' => 'Numero Attivo',
        'status' => IssueStatus::InLavorazione,
    ]);
    $closed = Issue::factory()->create([
        'magazine_id' => $magazine->id,
        'title' => 'Numero Archiviato',
        'status' => IssueStatus::Chiuso,
    ]);

    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($magazine);

    $this->actingAs($user)
        ->get(route('magazines.show', $magazine))
        ->assertOk()
        ->assertSee('Numero Attivo')
        ->assertSee('Numero Archiviato');

    expect($active->id)->not->toBe($closed->id);
});

test('a user with access can open an issue workspace page', function () {
    $magazine = Magazine::factory()->create();
    $issue = Issue::factory()->create(['magazine_id' => $magazine->id, 'title' => 'Ottobre 2026']);

    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($magazine);

    $this->actingAs($user)
        ->get(route('issues.show', [$magazine, $issue]))
        ->assertOk()
        ->assertSee('Ottobre 2026');
});

test('an issue url is not found when the issue does not belong to the magazine in the url', function () {
    $magazineA = Magazine::factory()->create();
    $magazineB = Magazine::factory()->create();
    $issueOfB = Issue::factory()->create(['magazine_id' => $magazineB->id]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('issues.show', [$magazineA, $issueOfB]))
        ->assertNotFound();
});

test('a user without access to the magazine cannot open one of its issues directly', function () {
    $magazine = Magazine::factory()->create();
    $issue = Issue::factory()->create(['magazine_id' => $magazine->id]);
    $user = User::factory()->create(['role' => UserRole::Redattore]);

    $this->actingAs($user)
        ->get(route('issues.show', [$magazine, $issue]))
        ->assertForbidden();
});
