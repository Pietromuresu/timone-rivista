<?php

use App\Enums\AdMaterialStatus;
use App\Enums\FormatCheckStatus;
use App\Models\Page;
use App\Models\PageFile;
use App\Support\AdMaterialStatusResolver;
use Illuminate\Support\Collection;

/**
 * Repliche a mano delle relazioni "pages"/"files" (nessuna query, nessun
 * DB) — stesso stile "puro" già usato in AutomaticChecksTest/AdLoadCalculatorTest.
 */
function fakeReservationPage(array $files): Page
{
    $page = new Page;
    $page->setRelation('files', new Collection($files));

    return $page;
}

function fakeReservationFile(?FormatCheckStatus $status = null, bool $overrideConfirmed = false, ?string $createdAt = null): PageFile
{
    $file = new PageFile([
        'format_check_status' => $status?->value,
        'format_override_confirmed_at' => $overrideConfirmed ? now() : null,
    ]);
    $file->created_at = $createdAt ?? now();

    return $file;
}

test('no assigned pages means the reservation is still just reserved', function () {
    expect(AdMaterialStatusResolver::resolve(new Collection))->toBe(AdMaterialStatus::Prenotato);
});

test('an assigned page with no file at all is assigned but not complete', function () {
    $pages = new Collection([fakeReservationPage([])]);

    expect(AdMaterialStatusResolver::resolve($pages))->toBe(AdMaterialStatus::Assegnato);
});

test('an assigned page with a matching file is complete', function () {
    $pages = new Collection([fakeReservationPage([fakeReservationFile(FormatCheckStatus::Matching)])]);

    expect(AdMaterialStatusResolver::resolve($pages))->toBe(AdMaterialStatus::Completo);
});

test('an assigned page where the format check does not apply is still complete once a file exists', function () {
    $pages = new Collection([fakeReservationPage([fakeReservationFile(FormatCheckStatus::NotApplicable)])]);

    expect(AdMaterialStatusResolver::resolve($pages))->toBe(AdMaterialStatus::Completo);
});

test('an unresolved format mismatch keeps the reservation at assegnato, not completo', function () {
    $pages = new Collection([fakeReservationPage([fakeReservationFile(FormatCheckStatus::Mismatch)])]);

    expect(AdMaterialStatusResolver::resolve($pages))->toBe(AdMaterialStatus::Assegnato);
});

test('a format mismatch explicitly overridden by the user counts as complete', function () {
    $pages = new Collection([fakeReservationPage([fakeReservationFile(FormatCheckStatus::Mismatch, overrideConfirmed: true)])]);

    expect(AdMaterialStatusResolver::resolve($pages))->toBe(AdMaterialStatus::Completo);
});

test('every assigned page must have a file for the reservation to be complete', function () {
    $pages = new Collection([
        fakeReservationPage([fakeReservationFile(FormatCheckStatus::Matching)]),
        fakeReservationPage([]),
    ]);

    expect(AdMaterialStatusResolver::resolve($pages))->toBe(AdMaterialStatus::Assegnato);
});

test('only the most recent file on a page is considered, not older re-uploads', function () {
    $older = fakeReservationFile(FormatCheckStatus::Mismatch, createdAt: now()->subDay()->toDateTimeString());
    $newer = fakeReservationFile(FormatCheckStatus::Matching, createdAt: now()->toDateTimeString());

    $pages = new Collection([fakeReservationPage([$older, $newer])]);

    expect(AdMaterialStatusResolver::resolve($pages))->toBe(AdMaterialStatus::Completo);
});
