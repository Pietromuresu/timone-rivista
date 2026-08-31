<?php

use App\Livewire\Timone\Grid;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.

test('an editor can set a print deadline on an issue', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updatePrintDeadline', '2026-12-01');

    expect($issue->fresh()->print_deadline_at->toDateString())->toBe('2026-12-01');
});

test('clearing the deadline also clears any pending reminder flag', function () {
    $issue = reorderableIssue();
    $issue->update(['print_deadline_at' => '2026-09-01', 'deadline_reminder_sent_at' => now()]);
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updatePrintDeadline', '');

    $fresh = $issue->fresh();
    expect($fresh->print_deadline_at)->toBeNull()
        ->and($fresh->deadline_reminder_sent_at)->toBeNull();
});

test('moving the deadline to a new date resets deadline_reminder_sent_at so a fresh reminder can fire', function () {
    $issue = reorderableIssue();
    $issue->update(['print_deadline_at' => '2026-09-01', 'deadline_reminder_sent_at' => now()]);
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updatePrintDeadline', '2026-12-15');

    $fresh = $issue->fresh();
    expect($fresh->print_deadline_at->toDateString())->toBe('2026-12-15')
        ->and($fresh->deadline_reminder_sent_at)->toBeNull();
});

test('a guest cannot change the print deadline', function () {
    $issue = reorderableIssue();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('updatePrintDeadline', '2026-12-01');

    expect($issue->fresh()->print_deadline_at)->toBeNull();
});

test('a sola lettura user cannot change the print deadline', function () {
    $issue = reorderableIssue();
    $readOnly = User::factory()->create(['role' => \App\Enums\UserRole::SolaLettura]);
    $issue->magazine->users()->attach($readOnly->id);

    Livewire::actingAs($readOnly)->test(Grid::class, ['issue' => $issue])
        ->call('updatePrintDeadline', '2026-12-01');

    expect($issue->fresh()->print_deadline_at)->toBeNull();
});
