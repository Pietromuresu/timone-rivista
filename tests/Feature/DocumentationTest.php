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
    $response->assertSee('Le copertine (I, II, III, IV) e la bandella', false); // Fase 2: sezione 9bis rinominata
    $response->assertSee('redattore di riferimento', false);

    // La conversione Markdown->HTML è avvenuta per davvero (non solo
    // testo grezzo passato attraverso): un titolo "##" deve diventare un
    // vero tag <h2>, non comparire come testo con i cancelletti letterali.
    $response->assertSee('<h2', false);
    $response->assertDontSee('## Collegare', false);
});

// Bug 3 (2026-10-12): manuale su /manuale, indice cliccabile, HTML grezzo
// non eseguito, file mancante gestito con un messaggio.

test('the manual lives at /manuale and the old /documentazione address redirects there', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    expect(route('documentation.show', absolute: false))->toBe('/manuale');
    $this->actingAs($user)->get('/documentazione')->assertRedirect('/manuale');
});

test('the manual page has a clickable table of contents pointing at the real heading ids', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('documentation.show'))->assertOk();

    $response->assertSee('aria-label="Indice del manuale"', false);
    // Stesso id dell'anchor scritto a mano nel manuale ([§4bis](#4bis-...)).
    $response->assertSee('href="#4bis-bloccare-una-pagina"', false);
    $response->assertSee('id="4bis-bloccare-una-pagina"', false);
});

test('a missing manual file shows a message instead of a server error', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    config(['app.manual_path' => base_path('NON_ESISTE.md')]);

    $this->actingAs($user)->get(route('documentation.show'))
        ->assertOk()
        ->assertSee('Il manuale utente non è disponibile');
});

test('raw html in the manual is escaped, never rendered', function () {
    $rendered = \App\Support\ManualRenderer::render("## Titolo\n\n<script>alert(1)</script>\n\n### Sotto");

    expect($rendered['html'])->not->toContain('<script>')
        ->and($rendered['html'])->toContain('&lt;script&gt;')
        ->and($rendered['toc'])->toBe([
            ['level' => 2, 'id' => 'titolo', 'text' => 'Titolo'],
            ['level' => 3, 'id' => 'sotto', 'text' => 'Sotto'],
        ]);
});

test('links to the application in the manual use APP_URL from the environment, not a hardcoded localhost', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    config(['app.url' => 'https://timone.example.org/']);

    $this->actingAs($user)->get(route('documentation.show'))
        ->assertOk()
        ->assertSee('href="https://timone.example.org"', false)
        ->assertDontSee('{{APP_URL}}', false)
        // Solo la riga del manuale: il resto del layout (asset, host della
        // richiesta di test) contiene legittimamente "localhost".
        ->assertDontSee('Accedi da <a href="http://localhost', false);
});
