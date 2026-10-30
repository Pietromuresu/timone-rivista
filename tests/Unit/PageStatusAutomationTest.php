<?php

use App\Enums\PageStatus;
use App\Support\PageStatusAutomation;

// Stato automatico della pagina (2026-10-30): la regola pura, per ogni
// stato iniziale × passaggio. 12 = piena, 15 = oltre capienza (conta come piena).

dataset('transitions', function () {
    $cases = [];

    foreach (PageStatus::cases() as $status) {
        // diventa piena: solo «Da assegnare» avanza, gli stati più avanti restano
        $becomesFull = $status === PageStatus::DaAssegnare ? PageStatus::Assegnata : null;
        $cases["{$status->value}: diventa piena"] = [$status, 6, 12, $becomesFull];
        $cases["{$status->value}: diventa oltre capienza"] = [$status, 0, 15, $becomesFull];
        // smette di essere piena: sempre «Da assegnare» (null se lo è già)
        $stops = $status === PageStatus::DaAssegnare ? null : PageStatus::DaAssegnare;
        $cases["{$status->value}: smette di essere piena"] = [$status, 12, 6, $stops];
        $cases["{$status->value}: da oltre capienza a parziale"] = [$status, 15, 9, $stops];
        $cases["{$status->value}: si svuota"] = [$status, 12, 0, $stops];
        // nessun passaggio pieno ↔ non pieno: nulla cambia
        $cases["{$status->value}: resta non piena"] = [$status, 6, 9, null];
        $cases["{$status->value}: resta piena"] = [$status, 12, 12, null];
        $cases["{$status->value}: da piena a oltre capienza"] = [$status, 12, 15, null];
        $cases["{$status->value}: da oltre capienza a piena"] = [$status, 15, 12, null];
    }

    return $cases;
});

test('the rule changes the status only on the full ↔ not full transition', function (PageStatus $current, int $before, int $after, ?PageStatus $expected) {
    expect(PageStatusAutomation::decide($current, $before, $after))->toBe($expected);
})->with('transitions');

test('used space per page ignores deleted contents', function () {
    $snapshot = [
        'contents' => [1 => ['deleted_at' => null], 2 => ['deleted_at' => '2026-10-30 10:00:00'], 3 => ['deleted_at' => null]],
        'page_content' => [
            10 => ['page_id' => 7, 'content_id' => 1, 'occupied_twelfths' => 6],
            11 => ['page_id' => 7, 'content_id' => 2, 'occupied_twelfths' => 6],
            12 => ['page_id' => 8, 'content_id' => 3, 'occupied_twelfths' => '12'],
        ],
    ];

    expect(PageStatusAutomation::usedByPage($snapshot))->toBe([7 => 6, 8 => 12]);
});
