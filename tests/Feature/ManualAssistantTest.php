<?php

use App\Enums\UserRole;
use App\Livewire\Manual\Questions;
use App\Models\User;
use App\Support\Manual\ArrayLearnedCorrections;
use App\Support\Manual\ManualAssistant;
use App\Support\Manual\ManualAssistantFactory;
use App\Support\Manual\ManualContexts;
use App\Support\Manual\ManualFeedbackRecorder;
use App\Support\Manual\ManualLearningGate;
use App\Support\Manual\ManualLearningPublisher;
use App\Support\Manual\ManualQuestions;
use App\Support\Manual\ManualRepository;
use App\Support\Manual\ManualSearchIndex;
use App\Support\Manual\QuestionKey;
use App\Support\Manual\StoredLearnedCorrections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

// Assistente «Chiedi al manuale» (2026-10-29): domande iniziali, set di
// riferimento, risposte via HTTP, voti, job notturno con cancello,
// simulazione con un votante ostile, pagina admin, pulizia a 12 mesi.

function assistantFactory(): ManualAssistantFactory
{
    return ManualAssistantFactory::fromConfig();
}

/** Assistente senza pesi imparati, costruito una volta per file (è puro e costa ~1,5 s). */
function plainAssistant(): ManualAssistant
{
    static $assistant = null;

    return $assistant ??= ManualAssistant::build(ManualSearchIndex::fromConfig()->build(), assistantFactory()->initialQuestions());
}

function assistantUser(UserRole $role = UserRole::Redattore): User
{
    return User::factory()->create(['role' => $role]);
}

test('domande.yml has at least 60 entries of 3–6 phrasings, pointing to real pages, with no ambiguous key', function () {
    $entries = ManualQuestions::load(config('timone.manual_dir').'/domande.yml');
    $pages = ManualRepository::fromConfig()->all();
    $keys = new QuestionKey(ManualSearchIndex::fromConfig()->build()['sinonimi']);
    $seen = [];

    expect(count($entries))->toBeGreaterThanOrEqual(60);

    foreach ($entries as $entry) {
        expect($pages->has($entry['pagina']))->toBeTrue("pagina inesistente: {$entry['pagina']}")
            ->and(in_array($entry['pagina'], ManualSearchIndex::NOT_INDEXED, true))->toBeFalse()
            ->and(count($entry['domande']))->toBeGreaterThanOrEqual(3)
            ->and(count($entry['domande']))->toBeLessThanOrEqual(6);

        foreach ($entry['domande'] as $question) {
            $key = $keys->of($question);
            expect(! isset($seen[$key]) || $seen[$key][0] === $entry['pagina'])
                ->toBeTrue("«{$question}» ha la stessa chiave di «".($seen[$key][1] ?? '')."» ma un'altra pagina");
            $seen[$key] = [$entry['pagina'], $question];
        }
    }

    $all = array_column(ManualQuestions::flatten($entries), 'domanda');
    foreach (ManualAssistant::QUICK_QUESTIONS as $quick) {
        expect(in_array($quick, $all, true))->toBeTrue("la domanda rapida «{$quick}» non è in domande.yml");
    }
});

test('a malformed line in domande.yml is an error', function () {
    $path = sys_get_temp_dir().'/domande-'.uniqid().'.yml';
    File::put($path, "- pagina: peso\n  domande:\n    - quanto pesa\nrisposta: 500 grammi\n");

    expect(fn () => ManualQuestions::load($path))->toThrow(InvalidArgumentException::class);
    File::delete($path);
});

test('the question key ignores word order, accents, stopwords and synonyms', function () {
    $keys = new QuestionKey(ManualSearchIndex::fromConfig()->build()['sinonimi']);

    expect($keys->of('Come tolgo una pubblicità?'))->toBe($keys->of('rimuovere la pubblicita'))
        ->and($keys->of('eliminare una inserzione'))->toBe($keys->of('Come tolgo una pubblicità?'))
        ->and($keys->of('spostare la pagina'))->toBe($keys->of('pagina spostare'))
        ->and($keys->of('chi può fare cosa'))->not->toBe('')
        ->and(QuestionKey::similarity('a b', 'a b'))->toBe(1.0)
        ->and(QuestionKey::similarity('a b', 'a c'))->toBe(1 / 3);
});

test('every question of domande.yml finds its page first, with no learned weights', function () {
    $assistant = plainAssistant();
    $failures = [];

    foreach (assistantFactory()->initialQuestions() as $q) {
        $first = $assistant->answer($q['domanda']);

        if ($first['stato'] !== 'risposta' || $first['pagina'] !== $q['pagina']) {
            $failures[] = "«{$q['domanda']}» → {$first['stato']} ".($first['pagina'] ?? implode(', ', $first['alternative']))." (attesa {$q['pagina']})";
        }
    }

    expect($failures)->toBe([]);
});

test('the 15 control questions find their page first through the assistant too', function () {
    $assistant = plainAssistant();

    foreach (assistantFactory()->controlQuestions() as $q) {
        $ranked = array_column($assistant->rank($q['domanda']), 'slug');
        expect($ranked[0] ?? null)->toBe($q['pagina'], "«{$q['domanda']}» → ".implode(', ', array_slice($ranked, 0, 3)));
    }
});

test('20 new user-style questions: the expected page is in the first three (known misses are listed, not hidden)', function () {
    $assistant = plainAssistant();
    $queries = json_decode(File::get(base_path('tests/fixtures/manual-assistant-queries.json')), true);
    $inTop3 = 0;

    foreach ($queries as $q) {
        $top = array_column(array_slice($assistant->rank($q['domanda']), 0, 3), 'slug');
        $found = in_array($q['pagina'], $top, true);
        $inTop3 += $found ? 1 : 0;

        // Chi è segnato come fuori dai primi tre e ci entra va tolto dalla
        // lista; chi non è segnato e ne esce è una regressione.
        expect($found)->toBe(! ($q['fuori_dai_primi_3'] ?? false), "«{$q['domanda']}» → ".implode(', ', $top)." (attesa {$q['pagina']})");
    }

    expect($inTop3)->toBe(14);
});

test('off-topic questions get no answer; the context bonus and corrections move pages within limits', function () {
    $assistant = plainAssistant();

    foreach (['ricetta della carbonara', 'chi ha vinto la partita', 'zxqw'] as $question) {
        expect($assistant->answer($question)['stato'])->toBe('niente', $question);
    }

    // «peso» chiesto dal timone resta la pagina del peso: il bonus è piccolo e fisso.
    $base = $assistant->rank('peso');
    $withContext = $assistant->rank('peso', ManualContexts::pages('timone'));
    expect($withContext[0]['slug'])->toBe('peso');
    $legenda = fn ($ranked) => collect($ranked)->firstWhere('slug', 'legenda')['score'] ?? 0;
    expect($legenda($withContext))->toEqualWithDelta($legenda($base) * ManualAssistant::CONTEXT_BONUS, 0.001);

    // Una correzione oltre ±0,3 viene comunque limitata a 0,3.
    $key = $assistant->key('peso');
    $plain = collect($base)->firstWhere('slug', 'peso')['score'];
    $boosted = collect($assistant->rank('peso', [], new ArrayLearnedCorrections([$key => ['peso' => 5.0]])))->firstWhere('slug', 'peso')['score'];
    expect($boosted)->toEqualWithDelta($plain * 1.3, 0.001);
});

test('every context page exists and routes map to the right context', function () {
    $pages = ManualRepository::fromConfig()->all();

    foreach (ManualContexts::PAGES as $context => $slugs) {
        foreach ($slugs as $slug) {
            expect($pages->has($slug))->toBeTrue("{$context}: {$slug}");
        }
    }

    expect(ManualContexts::forRoute('issues.show'))->toBe('timone')
        ->and(ManualContexts::forRoute('magazines.edit'))->toBe('rivista')
        ->and(ManualContexts::forRoute('magazines.index'))->toBe('riviste')
        ->and(ManualContexts::forRoute('users.create'))->toBe('utenti')
        ->and(ManualContexts::forRoute('profile.edit'))->toBeNull();
});

test('asking needs a login; an answer is the «In breve» of the page with a receipt', function () {
    $this->postJson('/manuale/assistente', ['domanda' => 'peso'])->assertUnauthorized();

    $response = $this->actingAs(assistantUser())->postJson('/manuale/assistente', ['domanda' => 'come sposto una pagina?', 'contesto' => 'timone'])
        ->assertOk()
        ->assertJsonPath('stato', 'risposta')
        ->assertJsonPath('risposta.slug', 'spostare-pagine')
        ->assertJsonPath('risposta.url', route('documentation.page', 'spostare-pagine'));

    expect($response->json('risposta.in_breve'))->toContain('Trascina la pagina')
        ->and($response->json('alternative'))->toHaveCount(3)
        ->and($response->json('ricevuta'))->toBeString();

    $this->postJson('/manuale/assistente', ['domanda' => 'peso', 'contesto' => 'inventato'])->assertUnprocessable();
});

test('an unanswered question offers tasks and is counted, without the user', function () {
    $user = assistantUser();

    $this->actingAs($user)->postJson('/manuale/assistente', ['domanda' => 'ricetta della carbonara'])
        ->assertOk()->assertJsonPath('stato', 'niente')->assertJsonPath('ricevuta', null)
        ->assertJsonCount(ManualSearchIndex::FALLBACK_TASKS, 'compiti');
    $this->actingAs($user)->postJson('/manuale/assistente', ['domanda' => 'Ricetta della carbonara?']);

    $row = DB::table('manual_unanswered')->first();
    expect($row->volte)->toBe(2)->and((array) $row)->not->toHaveKey('user_id');
});

test('votes: once per answer, alternatives only after «Non è questo», receipts cannot be forged', function () {
    $user = assistantUser();
    $ask = fn () => $this->actingAs($user)->postJson('/manuale/assistente', ['domanda' => 'come sposto una pagina'])->json();

    // 👍 una volta sola.
    $first = $ask();
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $first['ricevuta'], 'voto' => 'su'])->assertOk();
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $first['ricevuta'], 'voto' => 'giu'])->assertStatus(409);

    // Un'alternativa senza 👎 prima: no. Dopo il 👎: sì, una volta.
    $second = $ask();
    $alternative = $second['alternative'][1]['slug'];
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $second['ricevuta'], 'scelta' => $alternative])->assertUnprocessable();
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $second['ricevuta'], 'voto' => 'giu'])->assertOk();
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $second['ricevuta'], 'scelta' => 'peso'])->assertUnprocessable();
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $second['ricevuta'], 'scelta' => $alternative])->assertOk();
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $second['ricevuta'], 'scelta' => $second['alternative'][0]['slug']])->assertStatus(409);

    // Ricevuta manomessa.
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => 'falsa', 'voto' => 'su'])->assertUnprocessable();

    $rows = DB::table('manual_feedback')->orderBy('id')->get();
    expect($rows)->toHaveCount(3)
        ->and($rows[2]->slug_mostrato)->toBe('spostare-pagine')
        ->and($rows[2]->slug_scelto)->toBe($alternative)
        ->and($rows[2]->posizione)->toBe(2)
        ->and($rows[1]->voto)->toBe(-1)
        ->and($rows[0]->utente)->toBe(ManualFeedbackRecorder::userHash($user->id))
        ->and(strlen($rows[0]->utente))->toBe(64)
        ->and($rows[0]->hash_in_breve)->toBe(ManualRepository::fromConfig()->find('spostare-pagine')->inBreveHash());
});

test('in «Forse cercavi» a page can be chosen directly, and the receipt expires after a day', function () {
    $user = assistantUser();
    $maybe = $this->actingAs($user)->postJson('/manuale/assistente', ['domanda' => 'cosa vuol dire il bordo giallo'])->assertJsonPath('stato', 'forse')->json();

    expect($maybe['risposta'])->toBeNull()->and($maybe['alternative'][0]['slug'])->toBe('legenda');
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $maybe['ricevuta'], 'voto' => 'su'])->assertUnprocessable();
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $maybe['ricevuta'], 'scelta' => 'legenda'])->assertOk();
    expect(DB::table('manual_feedback')->value('slug_mostrato'))->toBeNull();

    $later = $this->postJson('/manuale/assistente', ['domanda' => 'peso'])->json('ricevuta');
    $this->travel(25)->hours();
    $this->postJson('/manuale/assistente/voto', ['ricevuta' => $later, 'voto' => 'su'])->assertUnprocessable()->assertJsonPath('esito', 'scaduta');
});

/**
 * Voti inseriti direttamente: $n persone diverse sulla chiave della domanda.
 */
function seedVotes(string $question, string $slug, int $vote, int $users, array $extra = [], string $prefix = 'u'): void
{
    $assistant = plainAssistant();
    $page = ManualRepository::fromConfig()->find($slug);
    $rows = [];

    for ($i = 1; $i <= $users; $i++) {
        $rows[] = array_merge([
            'risposta_id' => bin2hex(random_bytes(16)),
            'chiave' => $assistant->key($question),
            'domanda' => $question,
            'slug_mostrato' => $slug,
            'slug_scelto' => null,
            'su_alternativa' => false,
            'posizione' => 1,
            'voto' => $vote,
            'contesto' => null,
            'hash_versione' => $page?->versionHash() ?? 'x',
            'hash_in_breve' => $page?->inBreveHash() ?? 'x',
            'utente' => ManualFeedbackRecorder::userHash($prefix.$i),
            'created_at' => now(),
        ], $extra);
    }

    DB::table('manual_feedback')->insert($rows);
}

test('the nightly job learns a new question and a correction, and the command logs the run', function () {
    // «spedire le copie all'estero» non somiglia a nessuna domanda iniziale
    // («quanti grammi fa una copia» sì: grammi e peso sono sinonimi):
    // 6 persone, dopo «Non è questo», scelgono la pagina del peso.
    seedVotes('spedire le copie all\'estero', 'peso', 1, 6, ['slug_mostrato' => 'segnature', 'slug_scelto' => 'peso', 'su_alternativa' => true]);

    Log::spy();
    $this->artisan('manuale:apprendi')->assertSuccessful();

    $learned = DB::table('manual_learned_questions')->first();
    expect($learned->slug)->toBe('peso')->and($learned->domanda)->toBe('spedire le copie all\'estero')
        ->and(DB::table('manual_learned_boosts')->where('slug', 'peso')->value('correzione'))->toBeGreaterThan(0)
        ->and(DB::table('manual_learning_runs')->value('pubblicato'))->toBeTruthy()
        ->and(assistantFactory()->learnedQuestions())->toBe([['domanda' => 'spedire le copie all\'estero', 'pagina' => 'peso']])
        ->and((new StoredLearnedCorrections)->forKey($learned->chiave))->toHaveKey('peso');
    Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains($message, 'pesi pubblicati'))->once();

    // La domanda imparata ora risponde, anche scritta diversamente (stessa chiave).
    $this->actingAs(assistantUser())->postJson('/manuale/assistente', ['domanda' => 'All\'estero, spedire copie?'])
        ->assertJsonPath('stato', 'risposta')->assertJsonPath('risposta.slug', 'peso');
});

test('a learned question pointing to a removed page is ignored', function () {
    DB::table('manual_learned_questions')->insert(['chiave' => 'pagin vecch', 'domanda' => 'pagina vecchia', 'slug' => 'pagina-tolta', 'voti' => 9, 'utenti' => 9, 'created_at' => now()]);

    expect(assistantFactory()->learnedQuestions())->toBe([]);
    $this->actingAs(assistantUser())->postJson('/manuale/assistente', ['domanda' => 'pagina vecchia'])->assertOk();
});

test('the gate refuses weights that push a reference answer out of the first three', function () {
    $gate = assistantFactory()->gate();
    expect($gate->failures([], []))->toBe([]);

    // Il riferimento con il margine più stretto (legenda 15 punti, la seconda
    // 10): tre «domande imparate» con lo stesso testo verso altre pagine, e
    // la pagina giusta penalizzata al massimo. Le domande con un margine
    // largo resistono anche a questo («trentaduesimi»: 507 contro 124).
    $question = 'cosa vuol dire il bordo giallo';
    $failures = $gate->failures(
        [['domanda' => $question, 'slug' => 'peso'], ['domanda' => $question, 'slug' => 'glossario'], ['domanda' => $question, 'slug' => 'avvisi']],
        [['chiave' => plainAssistant()->key($question), 'slug' => 'legenda', 'correzione' => -0.3]],
    );

    expect($failures)->toBe([['domanda' => $question, 'attesa' => 'legenda', 'primi' => ['peso', 'avvisi', 'glossario']]]);
});

test('when the gate refuses, the old weights stay and the block is logged and recorded', function () {
    // Pesi «di prima», pubblicati da un giro precedente.
    DB::table('manual_learned_boosts')->insert(['chiave' => 'vecchia', 'slug' => 'peso', 'correzione' => 0.1, 'voti' => 5, 'utenti' => 5, 'positivi' => 1, 'sospetta' => false, 'created_at' => now()]);

    // Un set di controllo che nessun peso può soddisfare: il cancello blocca sempre.
    $dir = sys_get_temp_dir().'/manuale-'.uniqid();
    File::copyDirectory(config('timone.manual_dir'), $dir);
    File::put("{$dir}/domande-di-controllo.yml", "- pagina: peso\n  domande:\n    - ricetta della carbonara\n");
    config(['timone.manual_dir' => $dir]);
    seedVotes('quanti grammi fa una copia', 'peso', 1, 6);

    Log::spy();
    $summary = ManualLearningPublisher::fromConfig()->run(now());

    expect($summary['pubblicato'])->toBeFalse()
        ->and(DB::table('manual_learned_boosts')->pluck('chiave')->all())->toBe(['vecchia'])
        ->and(DB::table('manual_learning_runs')->value('pubblicato'))->toBeFalsy()
        ->and(json_decode(DB::table('manual_learning_runs')->value('fallimenti'), true)[0]['domanda'])->toBe('ricetta della carbonara');
    Log::shouldHaveReceived('warning')->once();
    $this->artisan('manuale:apprendi')->assertFailed();
    File::deleteDirectory($dir);
});

test('simulation: random votes plus a user who always says «Non è questo» keep the reference set green', function () {
    mt_srand(20261029);
    $references = [...assistantFactory()->initialQuestions(), ...assistantFactory()->controlQuestions()];
    $slugs = ManualRepository::fromConfig()->all()->keys()->reject(fn ($s) => in_array($s, ManualSearchIndex::NOT_INDEXED, true))->values()->all();
    $assistant = plainAssistant();
    $pages = ManualRepository::fromConfig()->all();
    $rows = [];

    // 30 persone, 600 voti a caso: 👍/👎 su pagine giuste e sbagliate.
    for ($i = 0; $i < 600; $i++) {
        $q = $references[mt_rand(0, count($references) - 1)];
        $slug = mt_rand(0, 2) === 0 ? $slugs[mt_rand(0, count($slugs) - 1)] : $q['pagina'];
        $rows[] = [$q['domanda'], $slug, mt_rand(0, 1) ? 1 : -1, 'p'.mt_rand(1, 30)];
    }

    // L'avversario: 👎 su ogni risposta giusta, più volte.
    foreach ($references as $q) {
        for ($r = 0; $r < 3; $r++) {
            $rows[] = [$q['domanda'], $q['pagina'], -1, 'avversario'];
        }
    }

    DB::table('manual_feedback')->insert(array_map(fn ($r) => [
        'risposta_id' => bin2hex(random_bytes(16)),
        'chiave' => $assistant->key($r[0]),
        'domanda' => $r[0],
        'slug_mostrato' => $r[1],
        'slug_scelto' => null,
        'su_alternativa' => false,
        'posizione' => 1,
        'voto' => $r[2],
        'contesto' => null,
        'hash_versione' => $pages[$r[1]]->versionHash(),
        'hash_in_breve' => $pages[$r[1]]->inBreveHash(),
        'utente' => ManualFeedbackRecorder::userHash($r[3]),
        'created_at' => now(),
    ], $rows));

    ManualLearningPublisher::fromConfig()->run(now());

    // Qualunque cosa sia stata pubblicata, il set di riferimento resta verde.
    $current = assistantFactory()->current();
    $stored = new StoredLearnedCorrections;
    foreach ($references as $q) {
        $top = array_column(array_slice($current->rank($q['domanda'], [], $stored), 0, ManualLearningGate::TOP), 'slug');
        expect(in_array($q['pagina'], $top, true))->toBeTrue("«{$q['domanda']}» → ".implode(', ', $top));
    }

    // Da solo l'avversario non sposta nulla: un voto per persona, servono 5 persone.
    DB::table('manual_feedback')->where('utente', '!=', ManualFeedbackRecorder::userHash('avversario'))->delete();
    expect(ManualLearningPublisher::fromConfig()->run(now())['correzioni'])->toBe(0);
});

test('the admin page is for admins only and «Disattiva» switches a learned question off at once', function () {
    DB::table('manual_learned_questions')->insert(['chiave' => 'copi grammi', 'domanda' => 'quanti grammi fa una copia', 'slug' => 'peso', 'voti' => 6, 'utenti' => 6, 'created_at' => now()]);
    DB::table('manual_unanswered')->insert(['chiave' => 'carbonar ricett', 'domanda' => 'ricetta della carbonara', 'volte' => 4, 'ultima_volta' => now()]);
    DB::table('manual_learning_runs')->insert(['pubblicato' => false, 'voti' => 10, 'correzioni' => 1, 'domande_imparate' => 1, 'sospette' => 0, 'fallimenti' => json_encode([['domanda' => 'peso', 'attesa' => 'peso', 'primi' => ['legenda']]]), 'created_at' => now()]);

    $this->actingAs(assistantUser())->get('/manuale/amministrazione/domande')->assertForbidden();

    $admin = assistantUser(UserRole::Admin);
    $this->actingAs($admin)->get('/manuale/amministrazione/domande')
        ->assertOk()
        ->assertSee('Cosa chiedono gli utenti')
        ->assertSee('ricetta della carbonara')
        ->assertSee('quanti grammi fa una copia')
        ->assertSee('Ultimo blocco');

    expect(assistantFactory()->learnedQuestions())->toHaveCount(1);
    Livewire::actingAs($admin)->test(Questions::class)->call('disable', 'domanda', 'copi grammi', 'peso')->assertSee('Disattivata');
    expect(assistantFactory()->learnedQuestions())->toBe([]);

    Livewire::actingAs(assistantUser())->test(Questions::class)->assertForbidden();
});

test('votes and unanswered questions older than 12 months are deleted', function () {
    seedVotes('peso', 'peso', 1, 2, ['created_at' => now()->subMonths(13)], 'vecchio');
    seedVotes('peso', 'peso', 1, 3, [], 'nuovo');
    DB::table('manual_unanswered')->insert([
        ['chiave' => 'a', 'domanda' => 'a', 'volte' => 1, 'ultima_volta' => now()->subMonths(13)],
        ['chiave' => 'b', 'domanda' => 'b', 'volte' => 1, 'ultima_volta' => now()],
    ]);

    $this->artisan('manuale:pulisci')->assertSuccessful();

    expect(DB::table('manual_feedback')->count())->toBe(3)
        ->and(DB::table('manual_unanswered')->pluck('chiave')->all())->toBe(['b']);
});

test('the «?» button is on every app page with the context of the screen, and the manual entry page has the assistant', function () {
    $user = assistantUser();

    $this->actingAs($user)->get('/riviste')->assertOk()
        ->assertSee('data-testid="manual-help-button"', false)
        ->assertSee('\u0022context\u0022:\u0022riviste\u0022', false);

    $this->actingAs($user)->get('/manuale')->assertOk()
        ->assertSee('Chiedi al manuale')
        ->assertSee('Come annullo l&#039;ultima cosa che ho fatto', false)
        ->assertDontSee('Cosa chiedono gli utenti');

    $this->actingAs(assistantUser(UserRole::Admin))->get('/manuale')->assertSee('Cosa chiedono gli utenti');
});
