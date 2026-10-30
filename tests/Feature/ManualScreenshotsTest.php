<?php

use App\Enums\AdFormat;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;
use App\Support\Manual\ManualBlocks;
use App\Support\Manual\ManualPage;
use App\Support\Manual\ManualRepository;
use App\Support\Manual\ManualScreenshot;
use App\Support\Manual\ManualScreenshotFiles;
use App\Support\ManualRenderer;
use Database\Seeders\ManualScreenshotSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

// Screenshot del manuale (2026-10-29, Fase 4): blocchi `:::screenshot`,
// immagini in public/manuale/img/ (scripts/manuale-screenshot.mjs),
// annotazioni in HTML sopra l'immagine, dati demo inventati.

/** Le schermate richieste dalla Fase 4, con la pagina che le mostra. */
const REQUIRED_SCREENSHOTS = [
    'timone-intero' => 'inizia-qui',
    'card-pagina' => 'legenda',
    'pannello-contenuti' => 'pannello-contenuti',
    'selezione-multipla' => 'selezione-multipla',
    'modifica-contenuto' => 'modificare-contenuto',
    'peso' => 'peso',
    'copertine' => 'copertine',
    'esporta-timone-pdf' => 'esporta-timone-pdf',
    'formati-nella-card' => 'disposizione-nella-pagina',
];

/** @return array<string, array{page: string, shot: ManualScreenshot}> */
function screenshotBlocks(): array
{
    $out = [];

    foreach (ManualRepository::fromConfig()->all() as $page) {
        foreach (ManualBlocks::split($page->body) as $block) {
            if ($block['type'] === 'screenshot') {
                expect($out)->not->toHaveKey($block['arg'], "screenshot «{$block['arg']}» usato due volte");
                $out[$block['arg']] = ['page' => $page->slug, 'shot' => ManualScreenshot::parse($block['arg'], $block['markdown'])];
            }
        }
    }

    return $out;
}

test('a screenshot block is parsed: alt text, numbered markers in percent, placeholder', function () {
    $shot = ManualScreenshot::parse('prova', "alt: Il timone di prova\n1 | 10% | 20,5% | Il **riepilogo**\n2 | 100% | 0% | Il bottone ?\n");

    expect($shot->alt)->toBe('Il timone di prova')
        ->and($shot->markers)->toBe([
            ['number' => 1, 'x' => 10.0, 'y' => 20.5, 'text' => 'Il **riepilogo**'],
            ['number' => 2, 'x' => 100.0, 'y' => 0.0, 'text' => 'Il bottone ?'],
        ])
        ->and($shot->placeholder)->toBe('Il timone di prova')
        ->and(ManualScreenshot::parse('vecchio', '[Screenshot da fare: la card]')->placeholder)->toBe('la card');
});

test('a malformed screenshot block is an error, not a marker silently lost', function (string $name, string $markdown) {
    expect(fn () => ManualScreenshot::parse($name, $markdown))->toThrow(InvalidArgumentException::class);
})->with([
    'marker without percent' => ['prova', "alt: x\n1 | 10 | 20% | testo"],
    'marker outside the image' => ['prova', "alt: x\n1 | 120% | 20% | testo"],
    'unknown line' => ['prova', "alt: x\nqualcosa d'altro"],
    'name that is not a file name' => ['Prova Uno', 'alt: x'],
]);

test('with an image the block becomes figure, markers and legend; without, the visible placeholder', function () {
    $page = ManualPage::parse("---\ntitolo: Prova\nslug: prova\ncategoria: Riferimento\nruoli: [admin]\nparole_chiave: [a]\nsinonimi: [b]\nordine: 1\n---\n\n:::in-breve\nTesto.\n:::\n\n:::screenshot timone-intero\nalt: Il timone <di prova>\n1 | 12.5% | 40% | Il **riepilogo**\n:::\n");
    $url = fn ($slug) => '/manuale/'.$slug;

    $with = ManualRenderer::renderPage($page, $url, [], fn ($name) => ['url' => "/manuale/img/{$name}.webp?v=1", 'width' => 2880, 'height' => 2000])['html'];
    expect($with)->toContain('<img src="/manuale/img/timone-intero.webp?v=1" alt="Il timone &lt;di prova&gt;" width="1440" height="1000"')
        ->toContain('<span class="manual-shot-marker" style="left: 12.5%; top: 40%" aria-hidden="true">1</span>')
        ->toContain('<li value="1">')
        ->toContain('<strong>riepilogo</strong>')
        ->not->toContain('Screenshot da fare');

    $without = ManualRenderer::renderPage($page, $url, [], fn () => null)['html'];
    expect($without)->toContain('[Screenshot da fare: Il timone &lt;di prova&gt;]')->not->toContain('<img');
});

test('every required screenshot exists, is on its page with alt text, and its markers point inside the image', function () {
    $blocks = screenshotBlocks();
    $files = ManualScreenshotFiles::fromConfig();

    foreach (REQUIRED_SCREENSHOTS as $name => $slug) {
        expect($blocks)->toHaveKey($name)
            ->and($blocks[$name]['page'])->toBe($slug)
            ->and($files->find($name))->not->toBeNull("manca public/manuale/img/{$name}.webp: npm run manuale:screenshot");
    }

    foreach ($blocks as $name => $block) {
        if ($files->find($name) !== null) {
            expect($block['shot']->alt)->not->toBeEmpty("«{$name}» ha l'immagine ma non il testo alternativo");
        }

        $numbers = array_column($block['shot']->markers, 'number');
        expect($numbers)->toBe($numbers === [] ? [] : range(1, count($numbers)), "«{$name}»: i numeri vanno da 1 in ordine");
    }
});

test('no image in public/manuale/img is left without its block, and images are light', function () {
    $blocks = screenshotBlocks();

    foreach (File::glob(config('timone.manual_images').'/*') as $path) {
        expect(pathinfo($path, PATHINFO_EXTENSION))->toBe(ManualScreenshotFiles::EXTENSION)
            ->and($blocks)->toHaveKey(pathinfo($path, PATHINFO_FILENAME))
            ->and(filesize($path))->toBeLessThan(400 * 1024);
    }
});

test('the manual page shows the annotated image, and the search index has the legend instead of the placeholder', function () {
    $this->actingAs(User::factory()->create())->get('/manuale/legenda')
        ->assertOk()
        ->assertSee('manuale/img/card-pagina.webp?v=', false)
        ->assertSee('manual-shot-marker', false)
        ->assertDontSee('Screenshot da fare');

    $text = collect(App\Support\Manual\ManualSearchIndex::fromConfig()->build()['documenti'])->pluck('testo')->implode(' ');
    expect($text)->not->toContain('Screenshot da fare')->toContain('il numero dice quanti');
});

test('the demo seeder creates only invented data, through the real services', function () {
    Storage::fake('local');
    // Le miniature in coda trasmettono un evento: qui nessun server Reverb.
    config(['broadcasting.default' => 'null']);
    $this->seed(ManualScreenshotSeeder::class);

    expect(User::pluck('email')->every(fn ($email) => str_ends_with($email, '@esempio.test')))->toBeTrue()
        ->and(Magazine::pluck('name')->all())->toBe(['Orto e Cortile'])
        ->and(Issue::where('title', 'Dicembre (numero di prova)')->first()->pages()->count())->toBe(24);

    // Tutti i formati usati negli screenshot, sulle pagine 7–14.
    $formats = Content::with('advertisement')->get()->pluck('advertisement.format')->filter()->map(fn (AdFormat $f) => $f->value)->unique();
    expect($formats)->toContain('doppia_pagina', 'piedino', 'mezza_pagina_verticale', 'un_terzo_pagina_verticale', 'un_quarto_pagina', 'pagina_intera');
});

test('the demo seeder refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new ManualScreenshotSeeder)->run())->toThrow(RuntimeException::class);
});
