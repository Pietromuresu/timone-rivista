<?php

use App\Models\User;
use App\Support\Manual\ManualQuestions;
use App\Support\Manual\ManualRepository;
use App\Support\Manual\ManualSearchIndex;
use App\Support\Manual\ManualSynonyms;
use App\Support\Manual\SearchEngine;
use App\Support\ManualRenderer;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

// Ricerca nel manuale (2026-10-29): indice (App\Support\Manual\ManualSearchIndex),
// route JSON, sinonimi, e le domande reali fatte girare con il JavaScript del
// browser (resources/js/manual-search-core.js) tramite node.

function searchIndexData(): array
{
    return ManualSearchIndex::fromConfig()->build();
}

/** Esegue tests/js/manual-search-check.mjs; salta il test se node non c'è. */
function runSearchCheck(array $queries = []): array
{
    $node = trim((string) shell_exec('command -v node'));

    if ($node === '' || ! File::isDirectory(base_path('node_modules/minisearch'))) {
        test()->markTestSkipped('node o minisearch non disponibili: la ricerca nel browser non si può provare qui.');
    }

    $dir = sys_get_temp_dir().'/manual-search-'.uniqid();
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/indice.json", json_encode(searchIndexData()));
    File::put("{$dir}/domande.json", json_encode($queries));

    $process = new Process([$node, base_path('tests/js/manual-search-check.mjs'), "{$dir}/indice.json", "{$dir}/domande.json", base_path('tests/fixtures/manual-text.json')], base_path());
    $process->mustRun();
    File::deleteDirectory($dir);

    return json_decode($process->getOutput(), true);
}

test('every page (except the task hub) and every «##» section is in the index, with an anchor that exists on the page', function () {
    $data = searchIndexData();
    $byId = collect($data['documenti'])->keyBy('id');

    expect($byId)->toHaveCount(count($data['documenti']));

    foreach (ManualRepository::fromConfig()->all()->except(ManualSearchIndex::NOT_INDEXED) as $page) {
        expect($byId->has($page->slug))->toBeTrue("manca la pagina {$page->slug}");

        $rendered = ManualRenderer::renderPage($page, fn ($slug) => '/manuale/'.$slug);
        foreach ($rendered['toc'] as $entry) {
            if ($entry['level'] === 2 && $entry['id'] !== 'vedi-anche') {
                $doc = $byId->get($page->slug.'#'.$entry['id']);
                expect($doc)->not->toBeNull("manca la sezione {$page->slug}#{$entry['id']}")
                    ->and($doc['sezione'])->toBe($entry['text'])
                    ->and($rendered['html'])->toContain('id="'.$entry['id'].'"');
            }
        }
    }

    expect($byId->has('cosa-vuoi-fare'))->toBeFalse()
        ->and(collect($data['documenti'])->pluck('testo')->filter(fn ($t) => str_contains($t, '<') || str_contains($t, ':::')))->toBeEmpty()
        ->and($data['compiti'])->toHaveCount(ManualSearchIndex::FALLBACK_TASKS)
        ->and($data['lingua']['paroleVuote'])->toContain('come');
});

test('the synonyms file is valid and every group has single words', function () {
    $groups = ManualSynonyms::load(config('timone.manual_dir').'/sinonimi.yml');

    expect($groups)->not->toBeEmpty();
    foreach ($groups as $group) {
        expect(count($group))->toBeGreaterThanOrEqual(2);
    }
});

test('a malformed synonyms line is an error, not a silently ignored line', function () {
    $path = sys_get_temp_dir().'/sinonimi-'.uniqid().'.yml';
    File::put($path, "- [togliere, rimuovere]\ntogliere = eliminare\n");

    expect(fn () => ManualSynonyms::load($path))->toThrow(InvalidArgumentException::class);
    File::delete($path);
});

test('the index route needs a login and returns the index with an ETag', function () {
    $this->getJson('/manuale/indice.json')->assertUnauthorized();

    $response = $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->getJson('/manuale/indice.json')
        ->assertOk()
        ->assertJsonStructure(['versione', 'documenti' => [['id', 'url', 'titolo', 'sezione', 'categoria', 'testo']], 'sinonimi', 'lingua' => ['piega', 'paroleVuote', 'desinenze', 'radiceMinima'], 'compiti']);

    expect($response->headers->get('ETag'))->toContain($response->json('versione'));
});

test('the cached index changes as soon as a page of the manual changes', function () {
    $dir = sys_get_temp_dir().'/manuale-'.uniqid();
    File::copyDirectory(config('timone.manual_dir'), $dir);
    config(['timone.manual_dir' => $dir]);

    $before = ManualSearchIndex::fromConfig()->cached();
    File::put("{$dir}/peso.md", str_replace('Peso della rivista', 'Peso della rivista e delle copie', File::get("{$dir}/peso.md")));
    $after = ManualSearchIndex::fromConfig()->cached();

    expect($after['versione'])->not->toBe($before['versione'])
        ->and(collect($after['documenti'])->firstWhere('id', 'peso')['titolo'])->toBe('Peso della rivista e delle copie');
    File::deleteDirectory($dir);
});

test('the browser normalizes text exactly like the server', function () {
    $result = runSearchCheck();

    foreach (json_decode(file_get_contents(base_path('tests/fixtures/manual-text.json')), true) as $i => $case) {
        expect($result['testi'][$i]['parole'])->toBe($case['parole'], $case['testo']);
    }
});

test('the 15 real questions find the expected page in first place', function () {
    // Dal 2026-10-29 (Fase 3) in resources/manuale: le usa anche il job notturno in produzione.
    $queries = ManualQuestions::flatten(ManualQuestions::load(config('timone.manual_dir').'/domande-di-controllo.yml'));
    $result = runSearchCheck($queries);

    foreach ($result['risultati'] as $i => $row) {
        $first = explode('#', $row['primi'][0] ?? '')[0];
        expect($first)->toBe($queries[$i]['pagina'], "«{$row['domanda']}» → ".implode(', ', $row['primi']));
    }
});

test('the server search engine (PHP) gives the same results as the browser (MiniSearch)', function () {
    // Fase 3: l'assistente e il cancello del job cercano in PHP
    // (App\Support\Manual\SearchEngine) perché in produzione non c'è node;
    // devono dare gli stessi risultati della ricerca nel browser.
    $queries = [
        ...ManualQuestions::flatten(ManualQuestions::load(config('timone.manual_dir').'/domande-di-controllo.yml')),
        ...json_decode(file_get_contents(base_path('tests/fixtures/manual-assistant-queries.json')), true),
    ];
    $browser = runSearchCheck($queries);
    $index = searchIndexData();
    $engine = new SearchEngine($index['documenti'], $index['sinonimi']);

    foreach ($browser['risultati'] as $row) {
        // Come search() nel browser: al massimo due risultati per pagina, i primi cinque.
        $perPage = [];
        $server = [];
        foreach ($engine->search($row['domanda']) as $result) {
            $slug = $result['doc']['slug'];
            if (($perPage[$slug] ?? 0) >= 2) {
                continue;
            }
            $perPage[$slug] = ($perPage[$slug] ?? 0) + 1;
            $server[] = $result['doc']['id'];
            if (count($server) === 5) {
                break;
            }
        }

        expect($server)->toBe($row['primi'], "«{$row['domanda']}»");
    }
});
