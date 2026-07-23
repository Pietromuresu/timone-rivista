<?php

use App\Enums\UserRole;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;

test('a user with access to the magazine can join the issue presence channel', function () {
    $magazine = Magazine::factory()->create();
    $issue = Issue::factory()->create(['magazine_id' => $magazine->id]);
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($magazine);

    $response = $this->actingAs($user)->post('/broadcasting/auth', [
        'channel_name' => "presence-issue.{$issue->id}",
        'socket_id' => '123.456',
    ]);

    $response->assertOk();
});

test('a user without access to the magazine cannot join the issue presence channel', function () {
    $magazine = Magazine::factory()->create();
    $issue = Issue::factory()->create(['magazine_id' => $magazine->id]);
    $user = User::factory()->create(['role' => UserRole::Redattore]);

    $response = $this->actingAs($user)->post('/broadcasting/auth', [
        'channel_name' => "presence-issue.{$issue->id}",
        'socket_id' => '123.456',
    ]);

    $response->assertForbidden();
});

test('an admin can join any issue presence channel without explicit magazine access', function () {
    $issue = Issue::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->post('/broadcasting/auth', [
        'channel_name' => "presence-issue.{$issue->id}",
        'socket_id' => '123.456',
    ]);

    $response->assertOk();
});
