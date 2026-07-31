<?php

use App\Enums\ThumbnailStatus;
use App\Livewire\Timone\Grid;
use App\Models\PageFile;
use App\Support\ThumbnailProgressEstimator;
use Illuminate\Support\Collection;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.
// Questi test coprono App\Support\ThumbnailProgressEstimator direttamente
// (Feature, non Unit: PageFile ha cast data, richiede il container Laravel
// completo — stesso motivo di PdfPageMeasurerTest, vedi tests/Pest.php) e,
// separatamente, che l'avanzamento sopravviva a un refresh della pagina
// (bug segnalato dall'utente il 2026-07-31: la versione precedente teneva
// lo stato in una proprietà Livewire effimera, persa a ogni remount).

test('with no completed samples yet, no average and no per-page estimate is fabricated', function () {
    expect(ThumbnailProgressEstimator::averageProcessingSeconds(collect([1, 2, 3])))->toBeNull();

    $page = \App\Models\Page::factory()->create();
    $pending = PageFile::factory()->create(['page_id' => $page->id]);

    $estimate = ThumbnailProgressEstimator::forPageFile($pending, null);

    expect($estimate['remainingSeconds'])->toBeNull()
        ->and($estimate['elapsedSeconds'])->toBeGreaterThanOrEqual(0);
});

test('the average is computed from real completed samples, not invented', function () {
    $page = \App\Models\Page::factory()->create();

    // Due miniature completate in 10s e 20s -> media reale 15s.
    PageFile::factory()->ready()->create([
        'page_id' => $page->id,
        'created_at' => now()->subSeconds(10),
        'updated_at' => now(),
    ]);
    PageFile::factory()->ready()->create([
        'page_id' => $page->id,
        'created_at' => now()->subSeconds(20),
        'updated_at' => now(),
    ]);

    $avg = ThumbnailProgressEstimator::averageProcessingSeconds(collect([$page->id]));

    expect($avg)->toBe(15.0);
});

test('a per-page estimate counts down as real time passes, and disappears once past the average', function () {
    $page = \App\Models\Page::factory()->create();
    $file = PageFile::factory()->create([
        'page_id' => $page->id,
        'created_at' => now()->subSeconds(4),
    ]);

    $estimate = ThumbnailProgressEstimator::forPageFile($file, 10.0);
    // ~6s (10s di media - ~4s già trascorsi): range invece di un valore
    // esatto per lo stesso motivo del test dell'aggregato sopra — tempo
    // vero trascorso durante l'esecuzione del test, non simulato.
    expect($estimate['remainingSeconds'])->toBeGreaterThanOrEqual(4)
        ->and($estimate['remainingSeconds'])->toBeLessThanOrEqual(6);

    // Il tempo trascorso ha già superato la media: niente numero
    // negativo/azzerato che simulerebbe una precisione che non abbiamo.
    $overdueFile = PageFile::factory()->create([
        'page_id' => $page->id,
        'created_at' => now()->subSeconds(30),
    ]);
    expect(ThumbnailProgressEstimator::forPageFile($overdueFile, 10.0)['remainingSeconds'])->toBeNull();
});

test('the aggregate counts every pending page and is null when nothing is pending', function () {
    expect(ThumbnailProgressEstimator::aggregate(collect(), 10.0))->toBeNull();

    $page = \App\Models\Page::factory()->create();
    $pending = PageFile::factory()->create(['page_id' => $page->id, 'created_at' => now()]);
    $processing = PageFile::factory()->create([
        'page_id' => $page->id,
        'thumbnail_status' => ThumbnailStatus::Processing,
        'created_at' => now()->subSeconds(10),
        'updated_at' => now()->subSeconds(4),
    ]);

    $aggregate = ThumbnailProgressEstimator::aggregate(collect([$pending, $processing]), 10.0);

    expect($aggregate['pending'])->toBe(2)
        // pending non ancora iniziata: intera media (10s); processing già
        // a ~4s su una media di 10s: ne restano ~6 -> totale reale ~16s
        // (range invece di un valore esatto: il tempo trascorso realmente
        // durante l'esecuzione del test introduce qualche centesimo/secondo
        // di scarto, che è esattamente il punto — è tempo vero, non finto).
        ->and($aggregate['remainingSeconds'])->toBeGreaterThanOrEqual(14)
        ->and($aggregate['remainingSeconds'])->toBeLessThanOrEqual(16);
});

test('thumbnail progress survives a fresh page mount, unlike the previous ephemeral-state design', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    PageFile::factory()->create([
        'page_id' => $page->id,
        'thumbnail_status' => ThumbnailStatus::Processing,
        'created_at' => now()->subSeconds(5),
    ]);

    // Un secondo Livewire::test() indipendente, come un vero refresh del
    // browser: nessuno stato del componente precedente sopravvive, solo
    // quello che render() può ricostruire da database.
    $freshMount = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);

    $freshMount->assertSee('1 pagina in elaborazione');
    expect($freshMount->html())->toContain('In coda da');
});
