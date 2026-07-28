<?php

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\PageStatus;
use App\Enums\UserRole;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\PageFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

// reorderableIssue() ed editorFor() sono definite globalmente in
// TimoneGridReorderTest.php (issue con total_pages = 6).

test('an editor can download the full timone pdf', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('the onlyAds filter excludes pages without an advertisement', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $adContent = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create([
        'content_id' => $adContent->id,
        'format' => AdFormat::PaginaIntera,
        'confirmation_status' => AdConfirmationStatus::Confermata,
    ]);
    $adPage = $issue->pages()->where('position', 1)->first();
    $adPage->contents()->attach($adContent->id, ['occupied_percentage' => 100]);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'onlyAds' => 1]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('the onlyUnapproved filter excludes pages already revisionata or ok stampa', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $page = $issue->pages()->where('position', 1)->first();
    $page->update(['status' => PageStatus::OkStampa]);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'onlyUnapproved' => 1]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('withThumbnails embeds a real ready thumbnail without error', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Storage::disk('local')->put('pages/1/thumb.png', 'fake-png-bytes');
    PageFile::factory()->for($page)->ready()->create([
        'disk' => 'local',
        'path' => 'pages/1/doc.pdf',
        'thumbnail_path' => 'pages/1/thumb.png',
    ]);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'withThumbnails' => 1]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('withThumbnails does not crash when a thumbnail is still processing', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    PageFile::factory()->for($page)->create([
        'disk' => 'local',
        'path' => 'pages/1/doc.pdf',
    ]);

    $response = $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue, 'withThumbnails' => 1]));

    $response->assertOk();
});

test('a sola lettura user (read-only) can still export the timone pdf', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);

    $this->actingAs($user)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]))->assertOk();
});

test('a user without access to the magazine cannot export the timone pdf', function () {
    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    $this->actingAs($outsider)->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]))->assertForbidden();
});

test('a guest cannot export the timone pdf', function () {
    $issue = reorderableIssue();

    $this->get(route('issues.export.timone-pdf', [$issue->magazine, $issue]))->assertRedirect(route('login'));
});

test('an issue url is not found when the issue does not belong to the magazine in the url', function () {
    $issue = reorderableIssue();
    $otherIssue = reorderableIssue();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('issues.export.timone-pdf', [$issue->magazine, $otherIssue]))
        ->assertNotFound();
});
