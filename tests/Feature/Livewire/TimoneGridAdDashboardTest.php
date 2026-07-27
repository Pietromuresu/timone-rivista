<?php

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\UserRole;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\User;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php). reorderableIssue()
// crea un'issue con total_pages = 6.

function assignedAdContent(App\Models\Issue $issue, int $position, AdFormat $format, AdConfirmationStatus $status): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create([
        'content_id' => $content->id,
        'format' => $format,
        'occupied_percentage_override' => null,
        'confirmation_status' => $status,
    ]);
    $page = $issue->pages()->where('position', $position)->first();
    $page->contents()->attach($content->id, ['occupied_percentage' => (string) $format->defaultPercentage()]);

    return $content;
}

test('an issue with no ads shows a zero percent ad load', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSee('0%')
        ->assertSee('Inserzioni assegnate:')
        ->assertSee('0');
});

test('the dashboard reports the correct equivalent pages and percentage for assigned ads', function () {
    $issue = reorderableIssue(); // total_pages = 6
    $user = editorFor($issue);

    assignedAdContent($issue, 1, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata);
    assignedAdContent($issue, 2, AdFormat::MezzaPaginaOrizzontale, AdConfirmationStatus::InTrattativa);

    // 1 pagina intera (100%) + 1 mezza pagina (50%) = 1.5 pagine equivalenti
    // su 6 pagine totali = 25%.
    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSee('25%')
        ->assertSee('Pagina intera: 1')
        ->assertSee('Mezza pagina orizzontale: 1')
        ->assertSee('Confermata: 1')
        ->assertSee('In trattativa: 1');
});

test('a not yet assigned advertisement is counted separately from assigned ones', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create(['content_id' => $content->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->assertSee('Pubblicità non ancora assegnate:')
        ->assertSee('Inserzioni assegnate: <strong>0</strong>', false)
        ->assertSee('Pubblicità non ancora assegnate: <strong>1</strong>', false);
});

test('an editor can set an ad load warning threshold and it is highlighted once exceeded', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    assignedAdContent($issue, 1, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateAdThreshold', '10');

    expect((float) $issue->magazine->fresh()->ad_threshold_percentage)->toBe(10.0);

    // Con una pagina intera su 6 pagine il carico è ~16.67%, sopra la
    // soglia del 10% appena impostata. $issue->fresh() evita di riusare
    // la relazione "magazine" già cache-ata (con la vecchia soglia) sulla
    // variabile di test originale.
    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue->fresh()])
        ->assertSee('⚠️ Sopra soglia');
});

test('clearing the threshold removes it', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $issue->magazine->update(['ad_threshold_percentage' => 20]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateAdThreshold', '');

    expect($issue->magazine->fresh()->ad_threshold_percentage)->toBeNull();
});

test('a threshold outside the 0-100 range is rejected', function () {
    $issue = reorderableIssue();
    $issue->magazine->update(['ad_threshold_percentage' => null]);
    $user = editorFor($issue);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateAdThreshold', '150');

    expect($issue->magazine->fresh()->ad_threshold_percentage)->toBeNull();
});

test('a sola lettura user cannot change the ad load threshold', function () {
    $issue = reorderableIssue();
    $issue->magazine->update(['ad_threshold_percentage' => null]);
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('updateAdThreshold', '30');

    expect($issue->magazine->fresh()->ad_threshold_percentage)->toBeNull();
});

test('a guest cannot change the ad load threshold', function () {
    $issue = reorderableIssue();
    $issue->magazine->update(['ad_threshold_percentage' => null]);

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('updateAdThreshold', '30');

    expect($issue->magazine->fresh()->ad_threshold_percentage)->toBeNull();
});
