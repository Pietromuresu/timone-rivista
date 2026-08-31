<?php

use App\Support\PageGroupResolver;

test('a page with no group expands to just itself', function () {
    $map = [1 => null, 2 => null, 3 => 5];

    expect(PageGroupResolver::expand($map, [1]))->toBe([1]);
});

test('a page with a group expands to every page sharing that group', function () {
    $map = [1 => 10, 2 => 10, 3 => 10, 4 => null, 5 => 20];

    $result = PageGroupResolver::expand($map, [2]);

    sort($result);
    expect($result)->toBe([1, 2, 3]);
});

test('seeds already covering the whole group do not duplicate ids', function () {
    $map = [1 => 10, 2 => 10, 3 => null];

    $result = PageGroupResolver::expand($map, [1, 2]);

    sort($result);
    expect($result)->toBe([1, 2]);
});

test('a partial selection of a group is expanded to include the rest of it', function () {
    $map = [1 => 10, 2 => 10, 3 => 10, 4 => null];

    // Solo la pagina 1 selezionata esplicitamente, ma appartiene a un
    // gruppo di 3 — 2 e 3 vengono incluse automaticamente (Fase 2.3).
    $result = PageGroupResolver::expand($map, [1, 4]);

    sort($result);
    expect($result)->toBe([1, 2, 3, 4]);
});

test('multiple different groups among the seeds are all expanded independently', function () {
    $map = [1 => 10, 2 => 10, 3 => 20, 4 => 20, 5 => null];

    $result = PageGroupResolver::expand($map, [1, 3]);

    sort($result);
    expect($result)->toBe([1, 2, 3, 4]);
});
