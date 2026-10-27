<?php

use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;
use App\Support\Occupancy\OccupancyConversion;
use Illuminate\Support\Facades\DB;

/*
 * Fase 3 (2026-10-27): conversione dei dati da percentuali a dodicesimi
 * (App\Support\Occupancy\OccupancyConversion, la stessa chiamata dalla
 * migration), su righe scritte come le scriveva il vecchio codice.
 *
 * Lo schema (up/down della migration) NON è eseguito qui: su SQLite
 * `->change()` ricostruisce la tabella e, dentro la transazione di
 * RefreshDatabase, il PRAGMA foreign_keys non si può spegnere — le cascade
 * cancellerebbero il pivot. Migrate + rollback dello schema sono stati
 * verificati su una copia MySQL del database di sviluppo (vedi HANDOFF.md),
 * come per la Fase 2.
 * reorderableIssue() è in TimoneGridReorderTest.php (6 pagine interne).
 */

/**
 * Contenuto + pivot con i soli valori del vecchio schema; le colonne nuove
 * (NOT NULL) ricevono un segnaposto 0 che la conversione sovrascrive.
 */
function legacyContent(Issue $issue, array $content, array $pivots = [], ?array $ad = null): int
{
    $id = DB::table('contents')->insertGetId($content + ['issue_id' => $issue->id, 'occupancy_twelfths' => 0, 'created_at' => now(), 'updated_at' => now()]);

    if ($ad !== null) {
        DB::table('advertisements')->insert($ad + ['content_id' => $id, 'client' => 'Cliente', 'confirmation_status' => 'confermata', 'created_at' => now(), 'updated_at' => now()]);
    }

    foreach ($pivots as $pageId => $percentage) {
        DB::table('page_content')->insert(['page_id' => $pageId, 'content_id' => $id, 'occupied_percentage' => $percentage, 'occupied_twelfths' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    return $id;
}

function pageIdAt(Issue $issue, int $position): int
{
    return $issue->pages()->where('position', $position)->value('id');
}

test('existing percentages become the nearest twelfth and contents get their total occupancy', function () {
    $issue = reorderableIssue();

    $article = legacyContent($issue, ['type' => 'articolo', 'title' => 'Tre pagine', 'page_occupancy' => 3], [
        pageIdAt($issue, 1) => 100, pageIdAt($issue, 2) => 99.7, pageIdAt($issue, 3) => 33,
    ]);
    $half = legacyContent($issue, ['type' => 'pubblicita', 'title' => 'Mezza', 'page_occupancy' => 0.5], [pageIdAt($issue, 3) => 50], ['format' => 'mezza_pagina_verticale']);
    $third = legacyContent($issue, ['type' => 'articolo', 'title' => 'Un terzo', 'page_occupancy' => 0.33]);
    $piedino = legacyContent($issue, ['type' => 'pubblicita', 'title' => 'Piedino', 'page_occupancy' => 0.33], [], ['format' => 'piedino']);
    $expanded = legacyContent($issue, ['type' => 'pubblicita', 'title' => 'Bug 8', 'page_occupancy' => 3], [], ['format' => 'mezza_pagina_orizzontale', 'occupied_percentage_override' => 100]);

    OccupancyConversion::applyAll();

    $twelfths = fn (int $contentId) => DB::table('contents')->where('id', $contentId)->value('occupancy_twelfths');
    $onPage = fn (int $contentId, int $position) => DB::table('page_content')->where('content_id', $contentId)->where('page_id', pageIdAt($issue, $position))->value('occupied_twelfths');

    expect($twelfths($article))->toBe(36)
        ->and($twelfths($half))->toBe(6)
        ->and($twelfths($third))->toBe(4)
        ->and($twelfths($piedino))->toBe(4)
        ->and($twelfths($expanded))->toBe(36)
        ->and($onPage($article, 1))->toBe(12)
        ->and($onPage($article, 2))->toBe(12)
        ->and($onPage($article, 3))->toBe(4)
        ->and($onPage($half, 3))->toBe(6)
        // Nessuna assegnazione persa, pagina 3 = 4 + 6 = 10/12.
        ->and(DB::table('page_content')->count())->toBe(4)
        ->and(Page::find(pageIdAt($issue, 3))->occupiedTwelfths())->toBe(10);
});

test('the rollback keeps the original values and fills the rows created after the conversion', function () {
    $issue = reorderableIssue();
    $legacy = legacyContent($issue, ['type' => 'articolo', 'title' => 'Vecchio', 'page_occupancy' => 0.5], [pageIdAt($issue, 1) => 49.8]);
    OccupancyConversion::applyAll();

    // Creato dopo la conversione: solo colonne nuove.
    $new = Content::factory()->create(['issue_id' => $issue->id, 'occupancy_twelfths' => 30]);
    Page::find(pageIdAt($issue, 2))->contents()->attach($new->id, ['occupied_twelfths' => 9]);

    OccupancyConversion::revertAll();

    expect((float) DB::table('contents')->where('id', $legacy)->value('page_occupancy'))->toBe(0.5)
        ->and((float) DB::table('page_content')->where('content_id', $legacy)->value('occupied_percentage'))->toBe(49.8)
        // 2,5 pagine non esistono nel vecchio modello: le pagine attraversate.
        ->and((float) DB::table('contents')->where('id', $new->id)->value('page_occupancy'))->toBe(3.0)
        ->and((float) DB::table('page_content')->where('content_id', $new->id)->value('occupied_percentage'))->toBe(75.0);
});

test('the dry-run shows what becomes what and flags pages over capacity, without writing', function () {
    $issue = reorderableIssue();
    // 3 contenuti a 40% sulla stessa pagina: 5 + 5 + 5 = 15/12 dopo l'arrotondamento.
    foreach (['A', 'B', 'C'] as $title) {
        legacyContent($issue, ['type' => 'articolo', 'title' => $title, 'page_occupancy' => 0.33], [pageIdAt($issue, 4) => 40]);
    }

    $this->artisan('timone:occupancy-dry-run', ['--issue' => $issue->id])
        ->expectsOutputToContain('«A»: 0.33 pag. → 1/3 di pagina (4/12)')
        ->expectsOutputToContain('pagina 4, «A»: 40% → 5/12 di pagina (5/12) (arrotondato)')
        ->expectsOutputToContain('pagina 4: dopo l\'arrotondamento occupa 15/12')
        ->assertSuccessful();

    expect(DB::table('page_content')->pluck('occupied_twelfths')->unique()->all())->toBe([0]);
});
