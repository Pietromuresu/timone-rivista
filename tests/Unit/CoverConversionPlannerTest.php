<?php

use App\Support\Covers\CoverConversionPlanner;

// Fase 2 (2026-10-26): pianificatore della conversione alle copertine separate.

function plannerPage(int $id, int $position, string $role = 'interna', ?int $group = null, int $contents = 0): array
{
    return ['id' => $id, 'position' => $position, 'role' => $role, 'page_group_id' => $group, 'contents_count' => $contents, 'cover_slot' => null];
}

test('cover roles become slots and internal pages are renumbered from 1', function () {
    $plan = CoverConversionPlanner::plan([
        plannerPage(10, 1, 'copertina1', contents: 1),
        plannerPage(11, 2, 'copertina2'),
        plannerPage(12, 3),
        plannerPage(13, 4),
        plannerPage(14, 5, 'copertina3'),
        plannerPage(15, 6, 'copertina4'),
    ]);

    expect(array_map(fn ($c) => $c['page_id'], $plan['covers']))->toBe(['I' => 10, 'II' => 11, 'III' => 14, 'IV' => 15])
        ->and($plan['covers']['I']['contents_count'])->toBe(1)
        ->and($plan['renumbered'])->toBe([12 => ['old_position' => 3, 'new_position' => 1], 13 => ['old_position' => 4, 'new_position' => 2]])
        ->and($plan['created_slots'])->toBe([])
        ->and($plan['new_total_pages'])->toBe(2)
        ->and($plan['new_pagine_copertina'])->toBe(4);
});

test('only I and IV existed: II and III are created empty', function () {
    $plan = CoverConversionPlanner::plan([
        plannerPage(1, 1, 'copertina1'),
        plannerPage(2, 2),
        plannerPage(3, 3, 'copertina4'),
    ]);

    expect($plan['created_slots'])->toBe(['II', 'III'])
        ->and($plan['new_total_pages'])->toBe(1);
});

test('a battente becomes the bandella and counts 6 cover pages', function () {
    $plan = CoverConversionPlanner::plan([
        plannerPage(1, 1, 'copertina1'),
        plannerPage(2, 2, 'battente'),
        plannerPage(3, 3),
        plannerPage(4, 4, 'copertina4'),
    ]);

    expect($plan['covers'])->toHaveKey('bandella')
        ->and($plan['new_pagine_copertina'])->toBe(6);
});

test('an issue without any cover role keeps every page internal and gets four empty covers', function () {
    $plan = CoverConversionPlanner::plan([plannerPage(1, 1), plannerPage(2, 2)]);

    expect($plan['covers'])->toBe([])
        ->and($plan['created_slots'])->toBe(['I', 'II', 'III', 'IV'])
        ->and($plan['new_total_pages'])->toBe(2)
        ->and($plan['warnings'])->not->toBeEmpty();
});

test('a duplicated role keeps the first page as cover and warns about the other', function () {
    $plan = CoverConversionPlanner::plan([
        plannerPage(1, 1, 'copertina1'),
        plannerPage(2, 2, 'copertina2'),
        plannerPage(3, 3, 'copertina2'),
    ]);

    expect($plan['covers']['II']['page_id'])->toBe(2)
        ->and($plan['renumbered'])->toHaveKey(3)
        ->and($plan['warnings'][0])->toContain('posizione 3');
});

test('a group left with a single internal page is dissolved', function () {
    $plan = CoverConversionPlanner::plan([
        plannerPage(1, 1, 'copertina1', group: 7),
        plannerPage(2, 2, group: 7),
        plannerPage(3, 3, group: 8),
        plannerPage(4, 4, group: 8),
    ]);

    expect($plan['ungrouped'])->toBe([2 => 7]);
});

test('an already converted issue is left alone', function () {
    $pages = [plannerPage(1, 1)];
    $pages[] = ['id' => 2, 'position' => null, 'role' => 'interna', 'page_group_id' => null, 'cover_slot' => 'I'];

    expect(CoverConversionPlanner::plan($pages)['already_converted'])->toBeTrue();
});
