<?php

use App\Models\User;

// Non esiste una landing page pubblica in questo progetto (strumento
// redazionale interno, non un sito di marketing) — "/" è solo uno
// smistamento verso login/riviste, mai una pagina vera propria da
// renderizzare (vedi routes/web.php e HANDOFF.md, 2026-07-29).

it('redirects a guest visiting the root to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

it('redirects an authenticated user visiting the root to the magazine list', function () {
    $response = $this->actingAs(User::factory()->create())->get('/');

    $response->assertRedirect(route('dashboard'));
});
