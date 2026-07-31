<?php

use App\Enums\PageContentType;
use App\Enums\PageStatus;

// Fase 4 (§4): la palette vive negli enum stessi (colorClasses()/
// borderClasses()/hexColors()) — questi test verificano che sia completa
// (ogni case ha un colore), distinta (nessun case condivide lo sfondo con
// un altro dello stesso enum, altrimenti "a colpo d'occhio" non
// funzionerebbe) e dark-mode-aware (ogni classe Tailwind ha un
// corrispettivo `dark:`, requisito esplicito della fase).

test('every page content type has non-empty, dark-mode-aware color classes', function () {
    foreach (PageContentType::cases() as $type) {
        $classes = $type->colorClasses();

        expect($classes)->toContain('bg-')
            ->and($classes)->toContain('text-')
            ->and($classes)->toContain('dark:bg-')
            ->and($classes)->toContain('dark:text-');
    }
});

test('every page content type has distinct background classes from the others', function () {
    $backgrounds = collect(PageContentType::cases())
        ->map(fn (PageContentType $type) => $type->colorClasses())
        ->unique();

    expect($backgrounds)->toHaveCount(count(PageContentType::cases()));
});

test('every page content type has complete hex colors', function () {
    foreach (PageContentType::cases() as $type) {
        $hex = $type->hexColors();

        expect($hex)->toHaveKeys(['bg', 'text'])
            ->and($hex['bg'])->toMatch('/^#[0-9a-f]{6}$/i')
            ->and($hex['text'])->toMatch('/^#[0-9a-f]{6}$/i');
    }
});

test('every page status has non-empty, dark-mode-aware color and border classes', function () {
    foreach (PageStatus::cases() as $status) {
        $colorClasses = $status->colorClasses();
        $borderClasses = $status->borderClasses();

        expect($colorClasses)->toContain('bg-')->toContain('dark:bg-')
            ->and($borderClasses)->toContain('border-')->toContain('dark:border-');
    }
});

test('every page status has distinct border classes from the others', function () {
    $borders = collect(PageStatus::cases())
        ->map(fn (PageStatus $status) => $status->borderClasses())
        ->unique();

    expect($borders)->toHaveCount(count(PageStatus::cases()));
});

test('every page status has complete hex colors including a border', function () {
    foreach (PageStatus::cases() as $status) {
        $hex = $status->hexColors();

        expect($hex)->toHaveKeys(['bg', 'text', 'border'])
            ->and($hex['bg'])->toMatch('/^#[0-9a-f]{6}$/i')
            ->and($hex['text'])->toMatch('/^#[0-9a-f]{6}$/i')
            ->and($hex['border'])->toMatch('/^#[0-9a-f]{6}$/i');
    }
});

test('page content type color classes no longer include a border, that channel belongs to page status', function () {
    // Fase 4: due canali cromatici distinti sulla stessa card — lo sfondo
    // (content type) e il bordo (status) non devono mai competere sulla
    // stessa proprietà CSS.
    foreach (PageContentType::cases() as $type) {
        expect($type->colorClasses())->not->toContain('border-');
    }
});
