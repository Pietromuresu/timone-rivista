<?php

use App\Enums\PageRole;
use App\Models\Page;
use App\Support\PageNumberer;
use Illuminate\Support\Collection;

/**
 * Pagine "finte" (nessuna query, nessun DB) — stesso pattern di
 * AdLoadCalculatorTest/PageGroupResolverTest: PageNumberer è puro, opera
 * solo sulle pagine già passate.
 */
function fakePageWithRole(int $id, PageRole $role): Page
{
    $page = new Page(['role' => $role]);
    $page->id = $id;

    return $page;
}

test('cover pages are labelled with their role name, never numbered', function () {
    $pages = new Collection([
        fakePageWithRole(1, PageRole::Copertina1),
        fakePageWithRole(2, PageRole::Interna),
        fakePageWithRole(3, PageRole::Interna),
        fakePageWithRole(4, PageRole::Copertina4),
    ]);

    $labels = PageNumberer::assign($pages);

    expect($labels[1])->toBe('Copertina (1ª)')
        ->and($labels[2])->toBe('1')
        ->and($labels[3])->toBe('2')
        ->and($labels[4])->toBe('Quarta di copertina');
});

test('numbering restarts at 1 immediately after the cover, regardless of physical position', function () {
    $pages = new Collection([
        fakePageWithRole(10, PageRole::Copertina1),
        fakePageWithRole(11, PageRole::Copertina2),
        fakePageWithRole(12, PageRole::Interna),
        fakePageWithRole(13, PageRole::Interna),
        fakePageWithRole(14, PageRole::Interna),
    ]);

    $labels = PageNumberer::assign($pages);

    expect($labels[12])->toBe('1')
        ->and($labels[13])->toBe('2')
        ->and($labels[14])->toBe('3');
});

test('battente counts as a numbered page when the magazine opts in, otherwise it is labelled by role', function () {
    $pages = new Collection([
        fakePageWithRole(1, PageRole::Copertina1),
        fakePageWithRole(2, PageRole::Battente),
        fakePageWithRole(3, PageRole::Interna),
        fakePageWithRole(4, PageRole::Copertina4),
    ]);

    $notCounted = PageNumberer::assign($pages, battenteCounted: false);
    expect($notCounted[2])->toBe('Battente')
        ->and($notCounted[3])->toBe('1');

    $counted = PageNumberer::assign($pages, battenteCounted: true);
    expect($counted[2])->toBe('1')
        ->and($counted[3])->toBe('2');
});
