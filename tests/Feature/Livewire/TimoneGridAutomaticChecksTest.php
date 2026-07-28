<?php

use App\Enums\PageStatus;
use App\Livewire\Timone\Grid;
use App\Models\Content;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php). reorderableIssue()
// crea un'issue con total_pages = 6.

test('an issue with nothing to flag shows no warnings panel', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertDontSee('⚠️ Avvisi');
});

test('an approved page with no content triggers the warnings panel', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 3)->first();
    $page->update(['status' => PageStatus::OkStampa]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSee('⚠️ Avvisi')
        ->assertSee('senza contenuti assegnati');
});

test('a content on non-contiguous pages triggers the warnings panel', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $content = Content::factory()->article()->create(['issue_id' => $issue->id, 'title' => 'Articolo sparso']);
    $issue->pages()->where('position', 1)->first()->contents()->attach($content->id, ['occupied_percentage' => 100]);
    $issue->pages()->where('position', 4)->first()->contents()->attach($content->id, ['occupied_percentage' => 100]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSee('⚠️ Avvisi')
        ->assertSee('Articolo sparso')
        ->assertSee('non consecutive');
});
