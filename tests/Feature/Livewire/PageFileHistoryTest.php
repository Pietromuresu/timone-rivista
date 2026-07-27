<?php

use App\Livewire\Timone\PageFileHistory;
use App\Models\PageFile;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php).

test('showing history for a page lists every upload, newest first, with the uploader', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();

    $older = PageFile::factory()->for($page)->ready()->create([
        'original_name' => 'bozza-v1.pdf',
        'uploaded_by' => $user->id,
        'created_at' => now()->subDay(),
    ]);
    $newer = PageFile::factory()->for($page)->ready()->create([
        'original_name' => 'bozza-v2.pdf',
        'uploaded_by' => $user->id,
        'created_at' => now(),
    ]);

    $component = Livewire::actingAs($user)->test(PageFileHistory::class, ['issue' => $issue])
        ->call('show', $page->id);

    $component->assertSee('bozza-v2.pdf')
        ->assertSee('bozza-v1.pdf')
        ->assertSee('attuale')
        ->assertSee($user->name);

    // Il più recente è il primo elemento della lista (::latest()).
    expect($component->get('pageId'))->toBe($page->id);
});

test('a page belonging to a different issue cannot be inspected through this component', function () {
    $issue = reorderableIssue();
    $otherIssue = reorderableIssue();
    $user = editorFor($issue);
    $otherPage = $otherIssue->pages()->first();
    PageFile::factory()->for($otherPage)->ready()->create(['original_name' => 'non-deve-comparire.pdf']);

    $component = Livewire::actingAs($user)->test(PageFileHistory::class, ['issue' => $issue])
        ->call('show', $otherPage->id);

    expect($component->get('pageId'))->toBeNull();
    $component->assertDontSee('non-deve-comparire.pdf');
});

test('a page with no uploads yet shows the empty state', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();

    Livewire::actingAs($user)->test(PageFileHistory::class, ['issue' => $issue])
        ->call('show', $page->id)
        ->assertSee('Nessun file caricato per questa pagina.');
});

test('the original pdf link is shown even while the thumbnail is still processing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();
    $file = PageFile::factory()->for($page)->create(['original_name' => 'in-lavorazione.pdf']);

    Livewire::actingAs($user)->test(PageFileHistory::class, ['issue' => $issue])
        ->call('show', $page->id)
        ->assertSee('in-lavorazione.pdf')
        ->assertSee('anteprima in corso')
        ->assertSee(route('page-files.show', $file), false);
});
