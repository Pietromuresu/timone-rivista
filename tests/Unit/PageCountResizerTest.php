<?php

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Page;
use App\Models\PageContent;
use App\Models\PageFile;
use App\Support\PageCountResizer;
use Illuminate\Support\Collection;

function fakePage(int $position, array $contents = [], array $files = []): Page
{
    $page = new Page(['position' => $position]);
    $page->setRelation('contents', new Collection($contents));
    $page->setRelation('files', new Collection($files));

    return $page;
}

function fakeAssignedContent(): Content
{
    $content = new Content(['type' => ContentType::Articolo]);
    $content->setRelation('pivot', new PageContent(['occupied_percentage' => '100']));

    return $content;
}

test('an unchanged total is a noop', function () {
    $impact = PageCountResizer::impact(new Collection, 6, 6);

    expect($impact)->toBe(['type' => 'noop', 'delta' => 0]);
});

test('a higher total reports an increase with the correct delta', function () {
    $impact = PageCountResizer::impact(new Collection, 6, 10);

    expect($impact)->toBe(['type' => 'increase', 'delta' => 4]);
});

test('a lower total on empty trailing pages reports no affected pages', function () {
    $pages = new Collection([
        fakePage(1),
        fakePage(2),
        fakePage(3),
    ]);

    $impact = PageCountResizer::impact($pages, 3, 1);

    expect($impact['type'])->toBe('decrease')
        ->and($impact['delta'])->toBe(2)
        ->and($impact['removedCount'])->toBe(2)
        ->and($impact['affectedPages'])->toBe([]);
});

test('a lower total flags trailing pages with assigned content', function () {
    $pages = new Collection([
        fakePage(1),
        fakePage(2, contents: [fakeAssignedContent()]),
        fakePage(3),
    ]);

    $impact = PageCountResizer::impact($pages, 3, 1);

    expect($impact['affectedPages'])->toBe([
        ['position' => 2, 'contentCount' => 1, 'hasFiles' => false],
    ]);
});

test('a lower total flags trailing pages with uploaded files even without content', function () {
    $pages = new Collection([
        fakePage(1),
        fakePage(2, files: [new PageFile]),
    ]);

    $impact = PageCountResizer::impact($pages, 2, 1);

    expect($impact['affectedPages'])->toBe([
        ['position' => 2, 'contentCount' => 0, 'hasFiles' => true],
    ]);
});
