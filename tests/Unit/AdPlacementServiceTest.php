<?php

use App\Enums\CoverSlot;
use App\Enums\PlannedAdKind;
use App\Support\AdPlacement\AdPlacementService;

// Fase 5 (2026-10-27): regola di posizionamento delle pubblicità previste.
// Config passata esplicitamente (i test Unit non caricano Laravel).

const DEFAULT_AD_PLACEMENT = ['cover_order' => ['II', 'III', 'IV', 'I'], 'start_page' => 2, 'step' => 2];

// Test richiesto dalla specifica.
test('6 full pages and 2 halves on 32 pages: covers II, III, IV, I, then pages 2, 4, halves on 6 and 8', function () {
    $plan = AdPlacementService::plan(32, ['full' => 6, 'half' => 2], DEFAULT_AD_PLACEMENT);

    expect($plan->labels())->toBe(['II di copertina', 'III di copertina', 'IV di copertina', 'I di copertina', 'pagina 2', 'pagina 4', 'pagina 6', 'pagina 8'])
        ->and(array_map(fn ($p) => $p['kind'], $plan->placements))->toBe([
            PlannedAdKind::Full, PlannedAdKind::Full, PlannedAdKind::Full, PlannedAdKind::Full,
            PlannedAdKind::Full, PlannedAdKind::Full, PlannedAdKind::Half, PlannedAdKind::Half,
        ])
        ->and($plan->fits())->toBeTrue()
        // La I di copertina usata: segnalata.
        ->and($plan->warnings)->toHaveCount(1)
        ->and($plan->warnings[0])->toContain('I di copertina');
});

test('the I cover is the last choice: with 3 full pages it stays free and nothing is flagged', function () {
    $plan = AdPlacementService::plan(32, ['full' => 3], DEFAULT_AD_PLACEMENT);

    expect($plan->labels())->toBe(['II di copertina', 'III di copertina', 'IV di copertina'])
        ->and($plan->warnings)->toBe([]);
});

// Precisazione dell'utente (2026-10-27): le copertine sono tutte
// preassegnate alla pubblicità — le prime 4 pubblicità, di qualsiasi
// formato, le riempiono di fila; poi pagine interne una sì e una no.
test('with fewer than 4 full pages the covers are filled by the next ads of any format', function () {
    $plan = AdPlacementService::plan(32, ['full' => 2, 'half' => 3], DEFAULT_AD_PLACEMENT);

    expect($plan->labels())->toBe(['II di copertina', 'III di copertina', 'IV di copertina', 'I di copertina', 'pagina 2'])
        ->and(array_map(fn ($p) => $p['kind'], $plan->placements))->toBe([
            PlannedAdKind::Full, PlannedAdKind::Full, PlannedAdKind::Half, PlannedAdKind::Half, PlannedAdKind::Half,
        ])
        ->and($plan->coverTwelfths())->toBe(12 + 12 + 6 + 6);
});

test('only fractions: the first four still take the covers, in size order', function () {
    $plan = AdPlacementService::plan(32, ['vetrina' => 1, 'piedino' => 1, 'half' => 1, 'two_thirds' => 1], DEFAULT_AD_PLACEMENT);

    expect($plan->labels())->toBe(['II di copertina', 'III di copertina', 'IV di copertina', 'I di copertina'])
        ->and(array_map(fn ($p) => $p['kind'], $plan->placements))->toBe([PlannedAdKind::TwoThirds, PlannedAdKind::Half, PlannedAdKind::Piedino, PlannedAdKind::Vetrina]);
});

test('after the covers, fractions continue the alternating sequence in size order, one per page', function () {
    $plan = AdPlacementService::plan(32, ['full' => 4, 'vetrina' => 1, 'piedino' => 1, 'half' => 1, 'two_thirds' => 1], DEFAULT_AD_PLACEMENT);

    expect(array_slice($plan->labels(), 4))->toBe(['pagina 2', 'pagina 4', 'pagina 6', 'pagina 8'])
        ->and(array_map(fn ($p) => $p['kind'], array_slice($plan->placements, 4)))->toBe([PlannedAdKind::TwoThirds, PlannedAdKind::Half, PlannedAdKind::Piedino, PlannedAdKind::Vetrina]);
});

test('when the alternating positions end, free pages are filled from the bottom', function () {
    $plan = AdPlacementService::plan(6, ['full' => 4, 'half' => 5], DEFAULT_AD_PLACEMENT);

    expect(array_slice($plan->labels(), 4))->toBe(['pagina 2', 'pagina 4', 'pagina 6', 'pagina 5', 'pagina 3']);
});

// Test richiesto dalla specifica: più pubblicità che spazio.
test('with more ads than space the rest stays unplaced and is reported', function () {
    $plan = AdPlacementService::plan(4, ['full' => 4, 'half' => 7], DEFAULT_AD_PLACEMENT);

    expect($plan->placements)->toHaveCount(8) // 4 copertine + 4 pagine
        ->and($plan->unplaced)->toHaveCount(3)
        ->and($plan->fits())->toBeFalse()
        ->and($plan->warnings[count($plan->warnings) - 1])->toContain('3 pubblicità previste non hanno trovato posto');
});

test('cover order, start page and step come from the config', function () {
    $plan = AdPlacementService::plan(20, ['full' => 3], ['cover_order' => ['IV'], 'start_page' => 3, 'step' => 4]);

    expect($plan->placements[0]['cover'])->toBe(CoverSlot::IV)
        ->and($plan->labels())->toBe(['IV di copertina', 'pagina 3', 'pagina 7']);
});

test('the plan computes equivalent pages and the internal-pages percentage without the covers', function () {
    $plan = AdPlacementService::plan(32, ['full' => 6, 'half' => 2], DEFAULT_AD_PLACEMENT);

    // 6 intere + 2 mezze = 7 pagine; sulle interne 2 intere + 2 mezze = 3 su 32.
    expect($plan->totalTwelfths())->toBe(84)
        ->and($plan->coverTwelfths())->toBe(48)
        ->and($plan->internalTwelfths())->toBe(36)
        ->and($plan->internalPercentage())->toBe(9.38);
});

test('nothing planned, nothing placed', function () {
    $plan = AdPlacementService::plan(32, [], DEFAULT_AD_PLACEMENT);

    expect($plan->count())->toBe(0)->and($plan->fits())->toBeTrue();
});
