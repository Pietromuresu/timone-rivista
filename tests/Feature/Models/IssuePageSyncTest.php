<?php

use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Models\Issue;

test('creating an issue automatically generates blank pages matching total_pages', function () {
    $issue = Issue::factory()->create(['total_pages' => 12]);

    expect($issue->pages()->count())->toBe(12)
        ->and($issue->pages->pluck('position')->all())->toBe(range(1, 12))
        ->and($issue->pages->every(fn ($page) => $page->content_type === PageContentType::Bianca))->toBeTrue()
        ->and($issue->pages->every(fn ($page) => $page->status === PageStatus::DaAssegnare))->toBeTrue();
});

test('an issue created with zero total pages has no pages', function () {
    $issue = Issue::factory()->create(['total_pages' => 0]);

    expect($issue->pages()->count())->toBe(0);
});
