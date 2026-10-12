<?php

use App\Enums\ContentType;
use App\Livewire\Timone\Grid;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use Livewire\Livewire;

// reorderableIssue()/editorFor() sono definite in TimoneGridReorderTest.php.
// Nessun ext-imagick richiesto: si parte già da un ContentFile con
// format_check_status precalcolato (factory state), senza passare dal Job.
// Punto 4 del prompt (2026-09-02): Grid::confirmFormatOverride() opera ora
// su ContentFile invece che PageFile (il PDF si carica solo su un
// contenuto) — stesso identico comportamento, verificato qui con
// l'equivalente ContentFile+pivot invece del vecchio PageFile diretto.

function attachedArticleWithFile(\App\Models\Page $page, string $factoryState): ContentFile
{
    $content = Content::create(['issue_id' => $page->issue_id, 'type' => ContentType::Articolo, 'title' => 'Articolo di prova', 'page_occupancy' => 1]);
    Article::create(['content_id' => $content->id, 'editorial_status' => 'da_scrivere']);
    $contentFile = ContentFile::factory()->for($content)->{$factoryState}()->create();
    $page->contents()->attach($content->id, ['occupied_percentage' => 100, 'content_file_id' => $contentFile->id]);

    return $contentFile;
}

test('an editor can force-accept a mismatching format', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $contentFile = attachedArticleWithFile($page, 'formatMismatch');

    expect($contentFile->hasUnresolvedFormatMismatch())->toBeTrue();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $contentFile->id)
        ->assertHasNoErrors();

    $contentFile->refresh();
    expect($contentFile->format_override_confirmed_at)->not->toBeNull()
        ->and($contentFile->format_override_confirmed_by)->toBe($user->id)
        ->and($contentFile->hasUnresolvedFormatMismatch())->toBeFalse();
});

test('confirming a format that already matches does nothing', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $contentFile = attachedArticleWithFile($page, 'formatMatching');

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $contentFile->id);

    expect($contentFile->fresh()->format_override_confirmed_at)->toBeNull();
});

test('a locked page cannot have its format override confirmed', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();
    $contentFile = attachedArticleWithFile($page, 'formatMismatch');
    $page->update(['locked_at' => now(), 'locked_by' => $user->id]);

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $contentFile->id)
        ->assertHasErrors('locked');

    expect($contentFile->fresh()->format_override_confirmed_at)->toBeNull();
});

test('a content file from a different issue cannot have its format override confirmed through this grid', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $otherIssue = reorderableIssue();
    $otherPage = $otherIssue->pages()->where('position', 1)->first();
    $contentFile = attachedArticleWithFile($otherPage, 'formatMismatch');

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $contentFile->id);

    expect($contentFile->fresh()->format_override_confirmed_at)->toBeNull();
});

test('a guest cannot confirm a format override', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    $contentFile = attachedArticleWithFile($page, 'formatMismatch');

    Livewire::test(Grid::class, ['issue' => $issue])
        ->call('confirmFormatOverride', $contentFile->id);

    expect($contentFile->fresh()->format_override_confirmed_at)->toBeNull();
});
