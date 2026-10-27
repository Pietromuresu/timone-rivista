<?php

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Livewire\Timone\Grid;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\Issue;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php
// e riusate qui (vedi nota in TimoneGridReorderLockTest.php). reorderableIssue()
// crea un'issue con total_pages = 6.

function assignedAdContent(Issue $issue, int $position, AdFormat $format, AdConfirmationStatus $status): Content
{
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create([
        'content_id' => $content->id,
        'format' => $format,
        'confirmation_status' => $status,
    ]);
    $page = $issue->pages()->where('position', $position)->first();
    $page->contents()->attach($content->id, ['occupied_twelfths' => min(12, $format->twelfths())]);

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
    $issue = reorderableIssue(); // 6 pagine interne + copertine I–IV a parte (Fase 2)

    $user = editorFor($issue);

    // Le copertine sono escluse dal cruscotto pubblicitario: dalla Fase 2
    // non sono più tra le pagine interne ($issue->pages()).
    assignedAdContent($issue, 2, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata);
    assignedAdContent($issue, 3, AdFormat::MezzaPaginaOrizzontale, AdConfirmationStatus::InTrattativa);

    // 1 pagina intera (12/12) + 1 mezza pagina (6/12) = 1.5 pagine equivalenti
    // su 6 pagine interne = 25%.
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

// Fase 2 (2026-10-26): una pubblicità sulla IV di copertina non entra nella
// percentuale delle pagine interne (né al numeratore né al denominatore).
test('an ad on a cover does not change the internal ad load', function () {
    $issue = reorderableIssue();
    $cover = $issue->covers()->where('cover_slot', 'IV')->first();
    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create(['content_id' => $content->id, 'format' => AdFormat::PaginaIntera]);
    $cover->contents()->attach($content->id, ['occupied_twelfths' => 12]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSee('0 pagine equiv. pubblicità su 6 totali');
});

// Fase 6 (2026-10-27): soglia di allarme e colori di avviso tolti dal
// cruscotto (richiesta della specifica): resta solo la percentuale.
test('the dashboard shows only the ad percentage, without threshold or warning colours', function () {
    $issue = reorderableIssue();
    assignedAdContent($issue, 2, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSee('16.67%')
        ->assertDontSee('Soglia allarme')
        ->assertDontSee('Sopra soglia')
        ->assertDontSeeHtml('updateAdThreshold');
});
