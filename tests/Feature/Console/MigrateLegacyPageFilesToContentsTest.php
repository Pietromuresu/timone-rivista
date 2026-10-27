<?php

use App\Enums\ContentType;
use App\Models\Article;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\PageGroup;

// reorderableIssue() è definita in TimoneGridReorderTest.php. Punto 8 del
// prompt "modifiche timone" — questo comando è scritto e testato (contro
// il database di test, sempre effimero) ma NON eseguito contro dati reali
// in questa sessione, come richiesto esplicitamente: il piano è stato
// mostrato all'utente prima di scrivere anche solo il file, e resta da
// lanciare a mano quando deciderà di procedere.

test('a single-page legacy pdf becomes a content with an equivalent content file, same physical file', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 3)->first();
    $pageFile = PageFile::factory()->for($page)->ready()->create([
        'original_name' => 'inserzione.pdf',
        'path' => 'pages/999/abc.pdf',
        'thumbnail_path' => 'pages/999/thumbnails/abc.png',
    ]);

    $this->artisan('timone:migrate-legacy-page-files')->assertSuccessful();

    $content = Content::sole();
    expect($content->type)->toBe(ContentType::Articolo)
        ->and($content->title)->toBe('Materiale pagina 3')
        ->and($content->issue_id)->toBe($issue->id);

    $contentFile = ContentFile::sole();
    expect($contentFile->content_id)->toBe($content->id)
        ->and($contentFile->path)->toBe($pageFile->path)
        ->and($contentFile->thumbnail_path)->toBe($pageFile->thumbnail_path)
        ->and($contentFile->original_name)->toBe('inserzione.pdf')
        ->and($contentFile->thumbnail_status)->toBe($pageFile->thumbnail_status);

    expect($page->contents()->where('content_id', $content->id)->exists())->toBeTrue()
        ->and($page->contents()->first()->pivot->content_file_id)->toBe($contentFile->id)
        ->and($page->fresh()->previewFile()->id)->toBe($contentFile->id);

    // Il PageFile originale non viene toccato.
    expect(PageFile::find($pageFile->id))->not->toBeNull();
});

test('consecutive pages from the same original multi-page pdf are grouped into one content and auto-linked', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();

    PageFile::factory()->for($pages[1])->ready()->create(['original_name' => 'doppia.pdf', 'pdf_page_number' => 1]); // posizione 2
    PageFile::factory()->for($pages[2])->ready()->create(['original_name' => 'doppia.pdf', 'pdf_page_number' => 2]); // posizione 3

    $this->artisan('timone:migrate-legacy-page-files')->assertSuccessful();

    $content = Content::sole();
    expect($content->title)->toBe('Materiale pagine 2-3');

    $contentFiles = ContentFile::orderBy('pdf_page_number')->get();
    expect($contentFiles)->toHaveCount(2)
        ->and($contentFiles[0]->pdf_page_number)->toBe(1)
        ->and($contentFiles[1]->pdf_page_number)->toBe(2);

    expect($pages[1]->contents()->first()->pivot->content_file_id)->toBe($contentFiles[0]->id)
        ->and($pages[2]->contents()->first()->pivot->content_file_id)->toBe($contentFiles[1]->id);

    // Punto 5 applicato retroattivamente: le due pagine sono collegate.
    $group = PageGroup::sole();
    expect($pages[1]->fresh()->page_group_id)->toBe($group->id)
        ->and($pages[2]->fresh()->page_group_id)->toBe($group->id);
});

test('two independent single-page uploads on non-contiguous pages are never grouped together', function () {
    $issue = reorderableIssue();
    $pages = $issue->pages()->orderBy('pages.position')->get();

    PageFile::factory()->for($pages[0])->ready()->create(['original_name' => 'a.pdf', 'pdf_page_number' => 1]);
    PageFile::factory()->for($pages[4])->ready()->create(['original_name' => 'b.pdf', 'pdf_page_number' => 1]);

    $this->artisan('timone:migrate-legacy-page-files')->assertSuccessful();

    expect(Content::count())->toBe(2)
        ->and(PageGroup::count())->toBe(0);
});

test('a page whose preview already comes from a content (not the legacy PageFile) is skipped entirely', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    // Materiale legacy presente, ma "coperto" da un contenuto già
    // modernizzato assegnato dopo il Punto 4 — Page::previewFile()
    // preferisce quest'ultimo, quindi non c'è nulla da migrare qui.
    PageFile::factory()->for($page)->create();
    $modernContent = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Articolo]);
    Article::factory()->for($modernContent)->create();
    $modernFile = ContentFile::factory()->for($modernContent)->ready()->create();
    $page->contents()->attach($modernContent->id, ['occupied_twelfths' => 12, 'content_file_id' => $modernFile->id]);

    $this->artisan('timone:migrate-legacy-page-files')->assertSuccessful();

    expect(Content::count())->toBe(1) // solo quello già esistente, nessuno aggiunto
        ->and(ContentFile::count())->toBe(1);
});

test('dry-run reports what it would do without writing anything', function () {
    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();
    PageFile::factory()->for($page)->ready()->create(['original_name' => 'x.pdf']);

    $this->artisan('timone:migrate-legacy-page-files', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();

    expect(Content::count())->toBe(0)
        ->and(ContentFile::count())->toBe(0);
});

test('no legacy pages at all reports nothing to do', function () {
    reorderableIssue();

    $this->artisan('timone:migrate-legacy-page-files')
        ->expectsOutputToContain('Nessuna pagina da migrare')
        ->assertSuccessful();
});
