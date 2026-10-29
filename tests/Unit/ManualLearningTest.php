<?php

use App\Support\Manual\ManualLearning;
use Illuminate\Support\Carbon;

// Apprendimento dell'assistente (2026-10-29): la matematica di
// App\Support\Manual\ManualLearning con numeri noti, senza database.

const MANUAL_LEARNING_PAGES = ['peso' => ['v1', 'b1'], 'segnature' => ['v2', 'b2'], 'legenda' => ['v3', 'b3']];

/** Un voto come riga di manual_feedback. */
function learningVote(string $user, int $vote, array $extra = []): array
{
    static $id = 0;

    return array_merge([
        'id' => ++$id,
        'chiave' => 'domanda nuova',
        'domanda' => 'domanda nuova',
        'slug_mostrato' => 'peso',
        'slug_scelto' => null,
        'su_alternativa' => false,
        'posizione' => 1,
        'voto' => $vote,
        'hash_versione' => 'v1',
        'hash_in_breve' => 'b1',
        'utente' => $user,
        'created_at' => Carbon::parse('2026-10-29 12:00'),
    ], $extra);
}

function learningNow(): Carbon
{
    return Carbon::parse('2026-10-29 12:00');
}

test('Wilson interval matches known values', function () {
    // 10 su 10: limite inferiore 0,7225 (valore di riferimento noto).
    [$lower, $upper] = ManualLearning::wilson(1.0, 10);
    expect(round($lower, 4))->toBe(0.7225)->and($upper)->toBe(1.0);

    // 50 su 100: 0,4038 – 0,5962.
    [$lower, $upper] = ManualLearning::wilson(0.5, 100);
    expect(round($lower, 4))->toBe(0.4038)->and(round($upper, 4))->toBe(0.5962);

    // 0 su 10: limite superiore 0,2775.
    expect(round(ManualLearning::wilson(0.0, 10)[1], 4))->toBe(0.2775);
});

test('decay halves every 90 days', function () {
    expect(ManualLearning::decay(0))->toBe(1.0)
        ->and(ManualLearning::decay(90))->toBe(0.5)
        ->and(ManualLearning::decay(180))->toBe(0.25)
        ->and(round(ManualLearning::decay(365), 4))->toBe(0.0601);
});

test('the correction is zero until the result is clear and never beyond ±0.3', function () {
    // 10 su 10: 0,6 × (0,7225 − 0,5) = 0,1335.
    expect(ManualLearning::correction(1.0, 10))->toBe(0.1335)
        // Maggioranza positiva ma incerta: nessuna correzione.
        ->and(ManualLearning::correction(0.6, 10))->toBe(0.0)
        ->and(ManualLearning::correction(1.0, 100000))->toBeLessThanOrEqual(0.3)
        ->and(ManualLearning::correction(1.0, 100000))->toBeGreaterThan(0.29)
        ->and(ManualLearning::correction(0.0, 100000))->toBeGreaterThanOrEqual(-0.3)
        ->and(ManualLearning::correction(0.0, 100000))->toBeLessThan(-0.29)
        ->and(ManualLearning::correction(0.0, 10))->toBe(-0.1335);
});

test('only the last vote of each person counts', function () {
    $learning = new ManualLearning([], MANUAL_LEARNING_PAGES);
    // Una persona vota 8 volte 👍 e poi cambia idea: conta solo il 👎.
    $votes = [];
    for ($i = 0; $i < 8; $i++) {
        $votes[] = learningVote('a', 1, ['created_at' => learningNow()->subMinutes(10 - $i)]);
    }
    $votes[] = learningVote('a', -1);

    expect($learning->compute($votes, learningNow())['voti'])->toBe(1);
});

test('nothing is learned below 5 votes from 3 people', function () {
    $learning = new ManualLearning([], MANUAL_LEARNING_PAGES);

    $four = array_map(fn ($u) => learningVote("u{$u}", 1, ['slug_scelto' => 'peso', 'su_alternativa' => true, 'slug_mostrato' => 'legenda']), range(1, 4));
    expect($learning->compute($four, learningNow())['correzioni'])->toBe([]);

    $five = [...$four, learningVote('u5', 1, ['slug_scelto' => 'peso', 'su_alternativa' => true, 'slug_mostrato' => 'legenda'])];
    $result = $learning->compute($five, learningNow());
    expect($result['correzioni'])->toHaveCount(1)
        ->and($result['correzioni'][0]['slug'])->toBe('peso')
        ->and($result['correzioni'][0]['correzione'])->toBeGreaterThan(0);
});

test('a choice after «Non è questo» weighs 2, a lower list position 1.5, the answer 1', function () {
    $learning = new ManualLearning([], MANUAL_LEARNING_PAGES);
    $now = learningNow();

    expect($learning->weight(learningVote('a', 1) + ['slug' => 'peso'], $now))->toBe(1.0)
        ->and($learning->weight(learningVote('a', 1, ['posizione' => 2, 'su_alternativa' => true, 'slug_mostrato' => null, 'slug_scelto' => 'peso']) + ['slug' => 'peso'], $now))->toBe(1.5)
        ->and($learning->weight(learningVote('a', 1, ['posizione' => 3, 'su_alternativa' => true, 'slug_mostrato' => 'legenda', 'slug_scelto' => 'peso']) + ['slug' => 'peso'], $now))->toBe(2.0);
});

test('a vote on a changed page weighs half, on a changed «In breve» nothing', function () {
    $learning = new ManualLearning([], MANUAL_LEARNING_PAGES);
    $now = learningNow();

    expect($learning->weight(learningVote('a', 1, ['hash_versione' => 'vecchia']) + ['slug' => 'peso'], $now))->toBe(0.5)
        ->and($learning->weight(learningVote('a', 1, ['hash_versione' => 'vecchia', 'hash_in_breve' => 'vecchio']) + ['slug' => 'peso'], $now))->toBe(0.0)
        // E un voto di 90 giorni fa sulla versione di oggi vale ½.
        ->and($learning->weight(learningVote('a', 1, ['created_at' => $now->copy()->subDays(90)]) + ['slug' => 'peso'], $now))->toBe(0.5);

    // Cinque voti tutti su un «In breve» cambiato: non si impara nulla.
    $votes = array_map(fn ($u) => learningVote("u{$u}", 1, ['hash_in_breve' => 'vecchio']), range(1, 6));
    expect($learning->compute($votes, $now))->toMatchArray(['correzioni' => [], 'voti' => 0]);
});

test('a new question is learned only above the threshold and only if not similar to an initial one', function () {
    $choose = fn (int $users, string $key) => array_map(fn ($u) => learningVote("u{$u}", 1, [
        'chiave' => $key, 'domanda' => 'testo di '.$key, 'slug_mostrato' => 'legenda', 'slug_scelto' => 'peso', 'su_alternativa' => true, 'posizione' => 1,
    ]), range(1, $users));

    $learning = new ManualLearning(['carta peso'], MANUAL_LEARNING_PAGES);

    expect($learning->compute($choose(4, 'grammi spedizion'), learningNow())['domande'])->toBe([]);

    $learned = $learning->compute($choose(6, 'grammi spedizion'), learningNow())['domande'];
    expect($learned)->toHaveCount(1)
        ->and($learned[0])->toMatchArray(['chiave' => 'grammi spedizion', 'domanda' => 'testo di grammi spedizion', 'slug' => 'peso', 'utenti' => 6]);

    // Troppo simile a una domanda iniziale («carta peso»): solo la correzione.
    $similar = $learning->compute($choose(6, 'carta peso'), learningNow());
    expect($similar['domande'])->toBe([])->and($similar['correzioni'])->toHaveCount(1);
});

test('votes for a page that no longer exists and disabled items are ignored', function () {
    $votes = array_map(fn ($u) => learningVote("u{$u}", 1, ['slug_mostrato' => 'pagina-tolta']), range(1, 8));
    expect((new ManualLearning([], MANUAL_LEARNING_PAGES))->compute($votes, learningNow())['correzioni'])->toBe([]);

    $votes = array_map(fn ($u) => learningVote("u{$u}", 1), range(1, 8));
    $disabled = [['tipo' => 'correzione', 'chiave' => 'domanda nuova', 'slug' => 'peso']];
    expect((new ManualLearning([], MANUAL_LEARNING_PAGES, $disabled))->compute($votes, learningNow())['correzioni'])->toBe([]);
});

test('an answer almost everyone rejects is flagged as probably wrong', function () {
    $votes = array_map(fn ($u) => learningVote("u{$u}", -1), range(1, 12));
    $result = (new ManualLearning([], MANUAL_LEARNING_PAGES))->compute($votes, learningNow());

    expect($result['correzioni'])->toHaveCount(1)
        ->and($result['correzioni'][0]['sospetta'])->toBeTrue()
        ->and($result['correzioni'][0]['correzione'])->toBeLessThan(0);

    // Con 4 persone no: troppo poche per dire che è sbagliata.
    $few = array_map(fn ($u) => learningVote("u{$u}", -1), range(1, 4));
    expect((new ManualLearning([], MANUAL_LEARNING_PAGES))->compute($few, learningNow())['correzioni'])->toBe([]);
});
