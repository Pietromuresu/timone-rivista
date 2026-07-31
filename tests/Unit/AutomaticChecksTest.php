<?php

use App\Enums\ContentType;
use App\Enums\PageStatus;
use App\Models\Content;
use App\Models\Page;
use Illuminate\Support\Collection;
use App\Support\AutomaticChecks;

/**
 * fakePage()/fakeContentOnPages() replicano a mano le relazioni che
 * Grid::render() carica normalmente via Eloquent — nessun accesso al
 * database, stesso stile "puro" già usato in AdLoadCalculatorTest.php e
 * PageCountResizerTest.php.
 */
function fakeAutoCheckPage(int $position, array $contents = [], PageStatus $status = PageStatus::DaAssegnare, bool $hasFile = true): Page
{
    $page = new Page(['position' => $position, 'status' => $status]);
    $page->setRelation('contents', new Collection($contents));
    // Di default "ha un PDF" (non è quello sotto test nella maggior parte
    // di questi casi): missingPdfPages() (Fase 2) verrebbe altrimenti
    // segnalata su ogni pagina finta di questo file, sporcando assert che
    // non la riguardano — vedi i test dedicati più sotto per $hasFile=false.
    $page->setRelation('files', new Collection($hasFile ? [new \App\Models\PageFile] : []));

    return $page;
}

/**
 * Un Content la cui relazione pages() elenca esattamente le posizioni
 * indicate — condivisa (stessa istanza) tra tutte le fakeAutoCheckPage()
 * che la referenziano, così AutomaticChecks vede lo stesso content_id su
 * più pagine, come farebbe la relazione belongsToMany reale.
 */
function fakeContentOnPositions(string $title, array $positions): Content
{
    static $nextId = 1;

    $content = new Content(['type' => ContentType::Articolo, 'title' => $title]);
    $content->id = $nextId++;
    $content->setRelation('pages', new Collection(array_map(
        fn (int $position) => new Page(['position' => $position]),
        $positions,
    )));

    return $content;
}

test('no pages produce no warnings', function () {
    $result = AutomaticChecks::check(new Collection);

    expect($result['nonContiguousContents'])->toBe([])
        ->and($result['approvedEmptyPages'])->toBe([])
        ->and($result['missingPdfPages'])->toBe([]);
});

test('an approved page with no pdf file at all is flagged as missing', function () {
    $pages = new Collection([fakeAutoCheckPage(1, [], PageStatus::OkStampa, hasFile: false)]);

    expect(AutomaticChecks::check($pages)['missingPdfPages'])->toBe([1]);
});

test('an approved page with a pdf file is not flagged as missing', function () {
    $pages = new Collection([fakeAutoCheckPage(1, [], PageStatus::Revisionata, hasFile: true)]);

    expect(AutomaticChecks::check($pages)['missingPdfPages'])->toBe([]);
});

test('a page still in progress without a pdf is not flagged as missing (too noisy otherwise)', function () {
    $pages = new Collection([fakeAutoCheckPage(1, [], PageStatus::InBozza, hasFile: false)]);

    expect(AutomaticChecks::check($pages)['missingPdfPages'])->toBe([]);
});

test('a content on contiguous pages is not flagged', function () {
    $content = fakeContentOnPositions('Articolo A', [5, 6]);
    $pages = new Collection([
        fakeAutoCheckPage(5, [$content]),
        fakeAutoCheckPage(6, [$content]),
    ]);

    expect(AutomaticChecks::check($pages)['nonContiguousContents'])->toBe([]);
});

test('a content spanning non-contiguous pages is flagged once', function () {
    $content = fakeContentOnPositions('Articolo B', [5, 9]);
    $pages = new Collection([
        fakeAutoCheckPage(5, [$content]),
        fakeAutoCheckPage(9, [$content]),
    ]);

    $flagged = AutomaticChecks::check($pages)['nonContiguousContents'];

    expect($flagged)->toHaveCount(1)
        ->and($flagged[0]['title'])->toBe('Articolo B')
        ->and($flagged[0]['positions'])->toBe([5, 9]);
});

test('a content on a single page is never flagged', function () {
    $content = fakeContentOnPositions('Articolo C', [3]);
    $pages = new Collection([fakeAutoCheckPage(3, [$content])]);

    expect(AutomaticChecks::check($pages)['nonContiguousContents'])->toBe([]);
});

test('an approved page with no content is flagged', function () {
    $pages = new Collection([
        fakeAutoCheckPage(1, [], PageStatus::Revisionata),
        fakeAutoCheckPage(2, [], PageStatus::OkStampa),
    ]);

    expect(AutomaticChecks::check($pages)['approvedEmptyPages'])->toBe([1, 2]);
});

test('an approved page with content is not flagged', function () {
    $content = fakeContentOnPositions('Articolo D', [1]);
    $pages = new Collection([fakeAutoCheckPage(1, [$content], PageStatus::OkStampa)]);

    expect(AutomaticChecks::check($pages)['approvedEmptyPages'])->toBe([]);
});

test('a non-approved empty page is not flagged', function () {
    $pages = new Collection([fakeAutoCheckPage(1, [], PageStatus::DaAssegnare)]);

    expect(AutomaticChecks::check($pages)['approvedEmptyPages'])->toBe([]);
});
