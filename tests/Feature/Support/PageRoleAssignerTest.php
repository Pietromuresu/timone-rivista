<?php

use App\Enums\PageRole;
use App\Models\Issue;
use App\Support\PageRoleAssigner;

test('creating an issue auto-assigns copertina1 and copertina4 to first and last page', function () {
    $issue = Issue::factory()->create(['total_pages' => 6]);

    expect($issue->pages()->where('position', 1)->first()->role)->toBe(PageRole::Copertina1)
        ->and($issue->pages()->where('position', 6)->first()->role)->toBe(PageRole::Copertina4)
        ->and($issue->pages()->where('position', 3)->first()->role)->toBe(PageRole::Interna);
});

test('an issue with a single page only gets copertina1, not also copertina4', function () {
    $issue = Issue::factory()->create(['total_pages' => 1]);

    expect($issue->pages()->first()->role)->toBe(PageRole::Copertina1);
});

test('syncCoverRoles moves copertina1/copertina4 when the physical extremes change', function () {
    $issue = Issue::factory()->create(['total_pages' => 4]);

    // Simula lo scambio di posizione 1/2 con la stessa tecnica a offset
    // temporaneo usata da Grid::movePage() (via lockForUpdate), qui
    // applicata a mano per isolare il comportamento di PageRoleAssigner
    // dal resto della transazione di riordino.
    $oldFirst = $issue->pages()->where('position', 1)->first();
    $oldSecond = $issue->pages()->where('position', 2)->first();

    $oldFirst->update(['position' => 100]);
    $oldSecond->update(['position' => 1]);
    $oldFirst->update(['position' => 2]);

    PageRoleAssigner::syncCoverRoles($issue);

    expect($oldSecond->fresh()->role)->toBe(PageRole::Copertina1)
        ->and($oldFirst->fresh()->role)->toBe(PageRole::Interna);
});

test('syncCoverRoles does not touch manually assigned copertina2/copertina3/battente roles', function () {
    $issue = Issue::factory()->create(['total_pages' => 6]);
    $page3 = $issue->pages()->where('position', 3)->first();
    $page3->update(['role' => PageRole::Battente->value]);

    PageRoleAssigner::syncCoverRoles($issue);

    expect($page3->fresh()->role)->toBe(PageRole::Battente);
});
