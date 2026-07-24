<?php

use App\Enums\UserRole;
use App\Models\PageFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

test('an authorized user can open the original pdf', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();

    Storage::disk('local')->put('pages/1/x.pdf', '%PDF-1.4 fake content');
    $pageFile = PageFile::factory()->for($page)->create(['disk' => 'local', 'path' => 'pages/1/x.pdf']);

    $response = $this->actingAs($user)->get(route('page-files.show', $pageFile));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('an authorized user can open the thumbnail when ready', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();

    Storage::disk('local')->put('pages/1/thumbnails/x.png', 'fake png content');
    $pageFile = PageFile::factory()->for($page)->ready()->create([
        'disk' => 'local',
        'path' => 'pages/1/x.pdf',
        'thumbnail_path' => 'pages/1/thumbnails/x.png',
    ]);

    $response = $this->actingAs($user)->get(route('page-files.thumbnail', $pageFile));

    $response->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('a thumbnail request for a file with no thumbnail yet returns 404', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->first();

    $pageFile = PageFile::factory()->for($page)->create(['disk' => 'local', 'path' => 'pages/1/x.pdf']);

    $this->actingAs($user)->get(route('page-files.thumbnail', $pageFile))->assertNotFound();
});

test('a user without access to the magazine cannot open the pdf', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $page = $issue->pages()->first();
    Storage::disk('local')->put('pages/1/x.pdf', '%PDF-1.4 fake content');
    $pageFile = PageFile::factory()->for($page)->create(['disk' => 'local', 'path' => 'pages/1/x.pdf']);

    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    $this->actingAs($outsider)->get(route('page-files.show', $pageFile))->assertForbidden();
});

test('a guest cannot open the pdf', function () {
    Storage::fake('local');

    $issue = reorderableIssue();
    $page = $issue->pages()->first();
    Storage::disk('local')->put('pages/1/x.pdf', '%PDF-1.4 fake content');
    $pageFile = PageFile::factory()->for($page)->create(['disk' => 'local', 'path' => 'pages/1/x.pdf']);

    $this->get(route('page-files.show', $pageFile))->assertRedirect(route('login'));
});
