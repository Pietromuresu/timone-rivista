<?php

use App\Enums\UserRole;
use App\Events\ContentAssigned;
use App\Livewire\Timone\Grid;
use App\Models\ContentFile;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Revisione 2, Fase 2.1 e 2.2 (2026-10-27): il click su un contenuto
// seleziona la pagina (non apre più il PDF), il file si apre dall'occhio,
// e i comandi del timone stanno nella stessa fascia sticky del riepilogo.
// layoutContent() è in PageLayoutTest.php, reorderableIssue()/editorFor()
// in TimoneGridReorderTest.php.

beforeEach(fn () => Event::fake([ContentAssigned::class]));

test('the preview inside a content block is no longer a link: the click reaches the page', function () {
    $issue = reorderableIssue();
    $content = layoutContent($issue, 1, 12);
    $file = ContentFile::factory()->ready()->create(['content_id' => $content->id]);

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();
    $href = route('content-files.show', $file);

    // L'unico link al file è l'occhio (nel menu «⋯»), non l'anteprima.
    expect(substr_count($html, 'href="'.$href.'"'))->toBe(1)
        ->and($html)->toMatch('/<a\s+href="'.preg_quote($href, '/').'"\s+target="_blank"\s+rel="noopener"[^>]*data-testid="open-content-file"/')
        // La card della pagina seleziona la pagina al click (clickPage).
        ->and($html)->toContain('$wire.clickPage(');
});

test('the eye opens the page of the PDF that belongs to this page of the timone', function () {
    $issue = reorderableIssue();
    $content = layoutContent($issue, 1, 12);
    $file = ContentFile::factory()->ready()->create(['content_id' => $content->id, 'pdf_page_number' => 3]);
    $issue->pages()->where('position', 1)->first()->contents()->updateExistingPivot($content->id, ['content_file_id' => $file->id]);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('href="'.route('content-files.show', $file).'#page=3"');
});

test('without a file the eye is disabled with the reason in the tooltip', function () {
    $issue = reorderableIssue();
    layoutContent($issue, 1, 6);

    Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('data-testid="open-content-file-disabled"')
        ->assertSeeHtml('title="Nessun file caricato"')
        ->assertDontSeeHtml('data-testid="open-content-file"');
});

test('the file is served inline only to users who can see the issue', function () {
    Storage::fake('local');
    $issue = reorderableIssue();
    $content = layoutContent($issue, 1, 12);
    $file = ContentFile::factory()->create(['content_id' => $content->id, 'path' => 'contents/x/file.pdf', 'original_name' => 'annuncio.pdf']);
    Storage::disk('local')->put('contents/x/file.pdf', '%PDF-1.4 test');

    $this->actingAs(editorFor($issue))->get(route('content-files.show', $file))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=annuncio.pdf');

    $outsider = User::factory()->create(['role' => UserRole::Redattore]);
    $this->actingAs($outsider)->get(route('content-files.show', $file))->assertForbidden();

    auth()->logout();
    $this->get(route('content-files.show', $file))->assertRedirect(route('login'));
});

test('summary and timone commands are in one sticky band under the header', function () {
    $issue = reorderableIssue();

    $html = Livewire::actingAs(editorFor($issue))->test(Grid::class, ['issue' => $issue])->html();

    $band = strpos($html, 'data-testid="timone-sticky-band"');
    $summary = strpos($html, 'data-testid="summary-bar"');
    $toolbar = strpos($html, 'data-testid="timone-toolbar"');
    // Subito dopo la fascia c'è il pannello Cronologia (componente a sé).
    $bandEnd = strpos($html, 'Cronologia', (int) $toolbar);

    expect($band)->not->toBeFalse()
        ->and($summary)->toBeGreaterThan($band)
        ->and($toolbar)->toBeGreaterThan($summary)
        // Zoom e viste sono dentro la fascia, prima della sua chiusura.
        ->and(strpos($html, 'aria-label="Dimensione delle pagine"'))->toBeGreaterThan($toolbar)->toBeLessThan($bandEnd)
        ->and(strpos($html, "setViewMode('lista')"))->toBeLessThan($bandEnd)
        // Una sola fascia sticky nel timone (il riepilogo non lo è più da solo).
        ->and(substr_count($html, 'data-sticky-top'))->toBe(1);
});
