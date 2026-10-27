<?php

use App\Models\Content;
use App\Models\Issue;
use App\Support\Covers\CoverConversion;
use Illuminate\Support\Facades\DB;

/*
 * Fase 2 (2026-10-26): migration dei dati su un numero di esempio "vecchio
 * modello" (copertine come pagine con ruolo, dentro total_pages), con
 * contenuti assegnati sia alle copertine sia alle pagine interne.
 * Il numero è costruito senza observer, come lo erano i numeri prima della
 * Fase 2.
 */

function legacyIssueWithCovers(): Issue
{
    $magazine = \App\Models\Magazine::factory()->create();
    $issue = Issue::withoutEvents(fn () => Issue::factory()->create(['magazine_id' => $magazine->id, 'total_pages' => 8, 'pagine_copertina' => 4]));
    $roles = [1 => 'copertina1', 2 => 'copertina2', 8 => 'copertina4'];

    foreach (range(1, 8) as $position) {
        DB::table('pages')->insert([
            'issue_id' => $issue->id,
            'position' => $position,
            'role' => $roles[$position] ?? 'interna',
            'content_type' => 'bianca',
            'status' => 'da_assegnare',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $issue;
}

function pageAt(Issue $issue, int $legacyPosition): int
{
    return (int) DB::table('pages')->where('issue_id', $issue->id)->where('position', $legacyPosition)->value('id');
}

test('the conversion moves covers into slots, renumbers internal pages from 1 and keeps every assignment', function () {
    $issue = legacyIssueWithCovers();
    $coverAd = Content::factory()->create(['issue_id' => $issue->id, 'title' => 'Pubblicità in II']);
    $article = Content::factory()->create(['issue_id' => $issue->id, 'title' => 'Articolo a pagina 3']);
    $iiPageId = pageAt($issue, 2);
    $articlePageId = pageAt($issue, 3);
    DB::table('page_content')->insert(['page_id' => $iiPageId, 'content_id' => $coverAd->id, 'occupied_percentage' => 100]);
    DB::table('page_content')->insert(['page_id' => $articlePageId, 'content_id' => $article->id, 'occupied_percentage' => 100]);

    CoverConversion::applyAll();

    $issue->refresh();

    expect($issue->total_pages)->toBe(5)
        ->and($issue->pages()->pluck('position')->all())->toBe([1, 2, 3, 4, 5])
        ->and($issue->covers()->pluck('cover_slot')->map->value->sort()->values()->all())->toBe(['I', 'II', 'III', 'IV'])
        ->and($issue->pagine_copertina)->toBe(4)
        // Assegnazioni intatte: la II porta la sua pubblicità, l'articolo è ora a pagina 1.
        ->and($issue->covers()->where('cover_slot', 'II')->first()->id)->toBe($iiPageId)
        ->and($coverAd->pages()->pluck('pages.id')->all())->toBe([$iiPageId])
        ->and(DB::table('pages')->where('id', $articlePageId)->value('position'))->toBe(1)
        ->and($article->pages()->pluck('pages.id')->all())->toBe([$articlePageId]);
});

test('the conversion can be rolled back to the original positions and roles', function () {
    $issue = legacyIssueWithCovers();
    $before = DB::table('pages')->where('issue_id', $issue->id)->orderBy('id')->get(['id', 'position', 'role'])->toArray();

    CoverConversion::applyAll();
    CoverConversion::revertAll();

    $after = DB::table('pages')->where('issue_id', $issue->id)->orderBy('id')->get(['id', 'position', 'role'])->toArray();

    expect($after)->toEqual($before)
        ->and(DB::table('pages')->where('issue_id', $issue->id)->whereNotNull('cover_slot')->count())->toBe(0)
        ->and($issue->fresh()->total_pages)->toBe(8)
        ->and(DB::table('cover_conversion_logs')->count())->toBe(0);
});

test('running the conversion twice changes nothing the second time', function () {
    $issue = legacyIssueWithCovers();

    CoverConversion::applyAll();
    $snapshot = DB::table('pages')->where('issue_id', $issue->id)->orderBy('id')->get()->toArray();
    CoverConversion::applyAll();

    expect(DB::table('pages')->where('issue_id', $issue->id)->orderBy('id')->get()->toArray())->toEqual($snapshot);
});

test('the dry-run command shows what becomes what without writing anything', function () {
    $issue = legacyIssueWithCovers();
    $before = DB::table('pages')->where('issue_id', $issue->id)->orderBy('id')->get()->toArray();

    $this->artisan('timone:covers-dry-run', ['--issue' => $issue->id])
        ->expectsOutputToContain('pagine totali: 8 → 5 interne')
        ->expectsOutputToContain('II di copertina: ← pagina in posizione 2')
        ->expectsOutputToContain('III di copertina: creata vuota')
        ->expectsOutputToContain('rinumerazione interne: 3–7 → 1–5')
        ->assertSuccessful();

    expect(DB::table('pages')->where('issue_id', $issue->id)->orderBy('id')->get()->toArray())->toEqual($before);
});

test('the rollback also brings back issues created after the conversion', function () {
    // Numero nato col modello nuovo (observer): copertine senza posizione,
    // nessuna riga nel registro della conversione.
    $issue = Issue::factory()->create(['total_pages' => 4]);

    CoverConversion::revertAll();

    $pages = DB::table('pages')->where('issue_id', $issue->id)->orderBy('position')->get();

    expect($pages->pluck('position')->all())->toBe(range(1, 8))
        ->and($pages->whereNotNull('cover_slot')->count())->toBe(0)
        ->and($pages->first()->role)->toBe('copertina1')
        ->and($pages->get(1)->role)->toBe('copertina2')
        ->and($pages->get(6)->role)->toBe('copertina3')
        ->and($pages->last()->role)->toBe('copertina4')
        ->and($issue->fresh()->total_pages)->toBe(8);
});
