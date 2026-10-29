<?php

use App\Models\User;
use App\Support\Manual\LegacyAnchors;
use App\Support\Manual\ManualPage;
use App\Support\Manual\ManualRepository;
use App\Support\ManualRenderer;

// Manuale utente in app. Bug 3 (2026-10-12): /manuale, indice cliccabile,
// HTML grezzo non eseguito, manuale mancante gestito con un messaggio.
// Dal 2026-10-29 una pagina per argomento (resources/manuale/*.md):
// /manuale = pagina d'ingresso, /manuale/{slug} = una pagina.

function manualReader(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

test('a guest is redirected away from the manual', function () {
    $this->get(route('documentation.show'))->assertRedirect(route('login'));
    $this->get(route('documentation.page', 'spostare-pagine'))->assertRedirect(route('login'));
});

test('the entry page shows «Inizia qui», «Cosa vuoi fare?» and the index of every page', function () {
    $response = $this->actingAs(manualReader())->get(route('documentation.show'))->assertOk();

    $response->assertSee('data-testid="manual-start"', false)
        ->assertSee('data-testid="manual-tasks"', false)
        ->assertSee('aria-label="Indice del manuale"', false)
        ->assertSee('rivista → numero → timone')
        ->assertSee('class="manual-task"', false)
        // Fase 2: ricerca in testa, con l'indirizzo dell'indice e i compiti di riserva.
        ->assertSee('data-testid="manual-search"', false)
        ->assertSee('manuale\\/indice.json', false)
        ->assertSee('class="manual-search-tasks"', false)
        ->assertSee('data-testid="manual-role-filter"', false);

    foreach (ManualRepository::fromConfig()->all() as $page) {
        $response->assertSee('href="'.route('documentation.page', $page->slug).'"', false);
    }
});

test('every page of the manual opens at /manuale/{slug} with its «In breve» and the index marking it as current', function () {
    $user = manualReader();

    foreach (ManualRepository::fromConfig()->all() as $page) {
        $this->actingAs($user)->get('/manuale/'.$page->slug)
            ->assertOk()
            ->assertSee('<h1>'.e($page->titolo).'</h1>', false)
            ->assertSee('class="manual-in-breve"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee(':::', false)
            ->assertDontSee('](manuale:', false);
    }
});

test('an unknown page is a 404', function () {
    $this->actingAs(manualReader())->get('/manuale/non-esiste')->assertNotFound();
});

test('the manual lives at /manuale and the old /documentazione address redirects there', function () {
    expect(route('documentation.show', absolute: false))->toBe('/manuale');
    $this->actingAs(manualReader())->get('/documentazione')->assertRedirect('/manuale');
});

test('the entry page carries the full old-anchor map for the redirect script', function () {
    $response = $this->actingAs(manualReader())->get(route('documentation.show'))->assertOk();

    // Lo script legge window.location.hash e usa questa mappa (JSON).
    $response->assertSee('window.location.hash', false);

    foreach (LegacyAnchors::MAP as $anchor => $slug) {
        $response->assertSee(json_encode($anchor), false)->assertSee(json_encode($slug), false);
    }
});

test('a missing manual shows a message instead of a server error', function () {
    config(['timone.manual_dir' => base_path('NON_ESISTE')]);

    $this->actingAs(manualReader())->get(route('documentation.show'))
        ->assertOk()
        ->assertSee('Il manuale utente non è disponibile');
    $this->actingAs(manualReader())->get('/manuale/spostare-pagine')->assertNotFound();
});

test('raw html in the manual is escaped, never rendered', function () {
    $rendered = ManualRenderer::render("## Titolo\n\n<script>alert(1)</script>\n\n### Sotto");

    expect($rendered['html'])->not->toContain('<script>')
        ->and($rendered['html'])->toContain('&lt;script&gt;')
        ->and($rendered['toc'])->toBe([
            ['level' => 2, 'id' => 'titolo', 'text' => 'Titolo'],
            ['level' => 3, 'id' => 'sotto', 'text' => 'Sotto'],
        ]);

    // Anche dentro i blocchi :::nome: l'HTML dei blocchi lo scrive il renderer, il testo resta escapato.
    $page = ManualPage::parse("---\ntitolo: T\nslug: t\ncategoria: Riferimento\nruoli: [admin]\nparole_chiave: [a]\nsinonimi: [b]\nordine: 1\n---\n:::in-breve\n<img src=x onerror=alert(1)>\n:::\n:::problema <b>titolo</b>\ntesto\n:::\n");
    $html = ManualRenderer::renderPage($page, fn ($slug) => '/manuale/'.$slug)['html'];

    expect($html)->not->toContain('<img')->not->toContain('<b>')->toContain('&lt;img')->toContain('&lt;b&gt;');
});

test('links to the application in the manual use APP_URL from the environment, not a hardcoded localhost', function () {
    config(['app.url' => 'https://timone.example.org/']);

    $this->actingAs(manualReader())->get(route('documentation.show'))
        ->assertOk()
        ->assertSee('href="https://timone.example.org"', false)
        ->assertDontSee('{{APP_URL}}', false);
});
