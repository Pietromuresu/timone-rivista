<?php

use App\Support\PageSpreadBuilder;
use App\Support\Signatures\SignaturePlan;

// Organizzazione in segnature (2026-10-29): App\Support\Signatures\SignaturePlan.

test('totals: 0, 4, 32, 64, 120, 126 pages', function (int $pages, int $full, int $remainder, array $parts, int $loose, bool $printable, string $label) {
    $plan = new SignaturePlan($pages, 4);

    expect($plan->fullSignatures())->toBe($full)
        ->and($plan->remainder())->toBe($remainder)
        ->and($plan->remainderBreakdown())->toBe(['parts' => $parts, 'loose' => $loose])
        ->and($plan->isPrintable())->toBe($printable)
        ->and($plan->summaryLabel())->toBe($label);
})->with([
    'zero' => [0, 0, 0, [], 0, true, '0 trentaduesimi'],
    'un quartino' => [4, 0, 4, [4], 0, true, '4 pagine'],
    'un trentaduesimo' => [32, 1, 0, [], 0, true, '1 trentaduesimo'],
    'due' => [64, 2, 0, [], 0, true, '2 trentaduesimi'],
    'esempio del prompt' => [120, 3, 24, [16, 8], 0, true, '3 trentaduesimi + 24 pagine'],
    'non multiplo di 4' => [126, 3, 30, [16, 8, 4], 2, false, '3 trentaduesimi + 30 pagine'],
]);

test('suggestion: pages to add or remove to reach the nearest multiple of 32 up and down', function () {
    expect((new SignaturePlan(120, 4))->suggestion())->toBe(['add' => 8, 'addTotal' => 128, 'remove' => 24, 'removeTotal' => 96])
        ->and((new SignaturePlan(126, 4))->suggestion())->toBe(['add' => 2, 'addTotal' => 128, 'remove' => 30, 'removeTotal' => 96])
        ->and((new SignaturePlan(64, 4))->suggestion())->toBeNull()
        ->and((new SignaturePlan(0, 4))->suggestion())->toBeNull()
        // Sotto la prima segnatura non si propone di scendere a zero.
        ->and((new SignaturePlan(20, 4))->suggestion())->toBe(['add' => 12, 'addTotal' => 32, 'remove' => null, 'removeTotal' => null]);
});

test('covers included count as 4 pages at the head (6 with the bandella); excluded by default', function () {
    $excluded = new SignaturePlan(60, 4);
    $included = new SignaturePlan(60, 4, 32, true);
    $withBandella = new SignaturePlan(58, 6, 32, true);

    expect($excluded->countedPages())->toBe(60)->and($excluded->summaryLabel())->toBe('1 trentaduesimo + 28 pagine')
        ->and($included->countedPages())->toBe(64)->and($included->summaryLabel())->toBe('2 trentaduesimi')
        ->and($withBandella->countedPages())->toBe(64)
        // Con le copertine in testa la pagina 28 è l'ultima della prima segnatura.
        ->and($included->signatureOf(28))->toBe(1)
        ->and($included->signatureOf(29))->toBe(2)
        ->and($included->startsSignature(29))->toBeTrue()
        // Il suggerimento resta in pagine interne.
        ->and((new SignaturePlan(116, 4, 32, true))->suggestion())->toBe(['add' => 8, 'addTotal' => 124, 'remove' => 24, 'removeTotal' => 92]);
});

test('a signature size other than 32: 16 (breakdown 8 + 4, «sedicesimi»)', function () {
    $plan = new SignaturePlan(44, 4, 16);

    expect($plan->summaryLabel())->toBe('2 sedicesimi + 12 pagine')
        ->and($plan->remainderBreakdown())->toBe(['parts' => [8, 4], 'loose' => 0])
        ->and($plan->breakdownLabel())->toBe('8 + 4')
        ->and($plan->label(3))->toBe('16° n. 3')
        ->and($plan->signatureOf(16))->toBe(1)
        ->and($plan->signatureOf(17))->toBe(2)
        ->and($plan->signatureOf(44))->toBe(3)
        ->and($plan->suggestion())->toBe(['add' => 4, 'addTotal' => 48, 'remove' => 12, 'removeTotal' => 32]);
});

test('signature index of every page', function () {
    $plan = new SignaturePlan(120, 4);

    expect(array_map(fn ($p) => $plan->signatureOf($p), [1, 2, 31, 32, 33, 64, 65, 96, 97, 120]))->toBe([1, 1, 1, 1, 2, 2, 3, 3, 4, 4])
        ->and(array_values(array_filter(range(1, 120), fn ($p) => $plan->startsSignature($p))))->toBe([33, 65, 97]);
});

test('the boundary between 32 and 33 falls INSIDE the spread [32 | 33], between its two pages', function () {
    // Pagina 1 a destra: [— | 1], [2 | 3], …, [32 | 33], …
    foreach ([[new SignaturePlan(120, 4), [33, 65, 97]], [new SignaturePlan(120, 4, 32, true), [29, 61, 93]], [new SignaturePlan(120, 4, 16), [17, 33, 49, 65, 81, 97, 113]]] as [$plan, $starts]) {
        $slots = PageSpreadBuilder::slots(collect(range(1, 120)));

        foreach (array_filter(range(1, 120), fn ($p) => $plan->startsSignature($p)) as $start) {
            $spread = collect($slots)->first(fn ($pair) => in_array($start, $pair, true));

            // La pagina che apre la segnatura è la DESTRA di una coppia e la sua
            // sinistra è l'ultima della segnatura precedente: il confine (lato
            // sinistro della pagina) sta tra le due pagine della coppia.
            expect($spread)->toBe([$start - 1, $start])
                ->and($plan->signatureOf($start - 1))->toBe($plan->signatureOf($start) - 1);
        }

        expect(array_values(array_filter(range(1, 120), fn ($p) => $plan->startsSignature($p))))->toBe($starts);
    }
});

test('format names', function () {
    expect(SignaturePlan::formatName(32, 1))->toBe('trentaduesimo')
        ->and(SignaturePlan::formatName(32, 3))->toBe('trentaduesimi')
        ->and(SignaturePlan::formatName(16, 2))->toBe('sedicesimi')
        ->and(SignaturePlan::formatName(8, 1))->toBe('ottavo')
        ->and(SignaturePlan::formatName(4, 2))->toBe('quartini')
        ->and((new SignaturePlan(126, 4))->breakdownLabel())->toBe('16 + 8 + 4 + 2 sciolte');
});
