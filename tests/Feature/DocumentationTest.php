<?php

use App\Models\User;

test('a guest is redirected away from the documentation page', function () {
    $this->get(route('documentation.show'))->assertRedirect(route('login'));
});

test('any authenticated user can view the documentation page, rendered from MANUALE_UTENTE.md', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('documentation.show'));

    $response->assertOk();

    // Un campione di sezioni, non tutte: una feature pre-esistente
    // (percentuale contenuto, Punto 3) e tre aggiunte in questo stesso
    // batch (Fase 2.3 gruppi, Fase 3.1 copertina, Fase 5 redattore/scadenza)
    // — se una di queste manca, la pagina sta servendo un manuale non
    // aggiornato insieme al codice, non solo uno stantio in generale.
    $response->assertSee('Cambiare lo stato di una pagina', false);
    $response->assertSee('Collegare pagine in un gruppo permanente', false);
    $response->assertSee('Copertina e battente', false);
    $response->assertSee('redattore di riferimento', false);

    // La conversione Markdown->HTML è avvenuta per davvero (non solo
    // testo grezzo passato attraverso): un titolo "##" deve diventare un
    // vero tag <h2>, non comparire come testo con i cancelletti letterali.
    $response->assertSee('<h2', false);
    $response->assertDontSee('## Collegare', false);
});
