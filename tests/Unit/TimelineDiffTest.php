<?php

use App\Support\Timeline\TimelineDiff;

// Annulla/ripeti (2026-10-28): differenze tra fotografie e unione dei passi di un lotto.

test('only changed fields are kept, timestamps are ignored, inserts and deletes keep the whole row', function () {
    $before = ['pages' => [1 => ['id' => 1, 'position' => 2, 'status' => 'da_assegnare', 'updated_at' => 'x']], 'contents' => [7 => ['id' => 7, 'title' => 'Vecchio']]];
    $after = ['pages' => [1 => ['id' => 1, 'position' => 5, 'status' => 'da_assegnare', 'updated_at' => 'y']], 'contents' => [8 => ['id' => 8, 'title' => 'Nuovo']]];

    expect(TimelineDiff::between($before, $after))->toBe([
        ['t' => 'contents', 'id' => 7, 'b' => ['id' => 7, 'title' => 'Vecchio'], 'a' => null],
        ['t' => 'contents', 'id' => 8, 'b' => null, 'a' => ['id' => 8, 'title' => 'Nuovo']],
        ['t' => 'pages', 'id' => 1, 'b' => ['position' => 2], 'a' => ['position' => 5]],
    ]);
});

test('values are compared as text: 1 and "1", "" and null are equal', function () {
    expect(TimelineDiff::between(['pages' => [1 => ['id' => 1, 'locked_by' => '', 'position' => '3']]], ['pages' => [1 => ['id' => 1, 'locked_by' => null, 'position' => 3]]]))->toBe([]);
});

test('merging two steps keeps the first "before" and the last "after"; a row born and removed disappears', function () {
    $first = [['t' => 'pages', 'id' => 1, 'b' => ['position' => 2], 'a' => ['position' => 5]], ['t' => 'contents', 'id' => 9, 'b' => null, 'a' => ['id' => 9, 'title' => 'T']]];
    $second = [['t' => 'pages', 'id' => 1, 'b' => ['position' => 5, 'status' => 'a'], 'a' => ['position' => 6, 'status' => 'b']], ['t' => 'contents', 'id' => 9, 'b' => ['id' => 9, 'title' => 'T'], 'a' => null]];

    expect(TimelineDiff::merge($first, $second))->toBe([
        ['t' => 'pages', 'id' => 1, 'b' => ['position' => 2, 'status' => 'a'], 'a' => ['position' => 6, 'status' => 'b']],
    ]);
});
