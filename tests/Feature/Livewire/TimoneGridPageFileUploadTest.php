<?php

use App\Enums\ThumbnailStatus;
use App\Enums\UserRole;
use App\Jobs\GeneratePageFileThumbnail;
use App\Livewire\Timone\Grid;
use App\Models\PageFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

test('an editor with access uploads a pdf to an empty page', function () {
    Queue::fake();

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->create('sample.pdf', 100, 'application/pdf'))
        ->assertHasNoErrors();

    $pageFile = PageFile::where('page_id', $page->id)->first();

    expect($pageFile)->not->toBeNull()
        ->and($pageFile->disk)->toBe('local')
        ->and($pageFile->thumbnail_status)->toBe(ThumbnailStatus::Pending)
        ->and($pageFile->uploaded_by)->toBe($user->id);

    Queue::assertPushed(GeneratePageFileThumbnail::class, fn (GeneratePageFileThumbnail $job) => $job->pageFile->id === $pageFile->id);
});

test('uploading a non pdf file is rejected', function () {
    Queue::fake();

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->create('sample.txt', 10, 'text/plain'))
        ->assertHasErrors();

    expect(PageFile::where('page_id', $page->id)->exists())->toBeFalse();

    Queue::assertNothingPushed();
});

test('a sola lettura user cannot upload a pdf', function () {
    Queue::fake();

    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->create('sample.pdf', 100, 'application/pdf'));

    expect(PageFile::where('page_id', $page->id)->exists())->toBeFalse();

    Queue::assertNothingPushed();
});

test('a redattore without access to the magazine cannot upload a pdf', function () {
    Queue::fake();

    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::actingAs($outsider)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->create('sample.pdf', 100, 'application/pdf'));

    expect(PageFile::where('page_id', $page->id)->exists())->toBeFalse();

    Queue::assertNothingPushed();
});

test('a guest cannot upload a pdf', function () {
    Queue::fake();

    $issue = reorderableIssue();
    $page = $issue->pages()->where('position', 1)->first();

    Livewire::test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$page->id}", UploadedFile::fake()->create('sample.pdf', 100, 'application/pdf'));

    expect(PageFile::where('page_id', $page->id)->exists())->toBeFalse();

    Queue::assertNothingPushed();
});

test('a page belonging to a different issue cannot receive an upload', function () {
    Queue::fake();

    $issue = reorderableIssue();
    $user = editorFor($issue);
    $otherIssue = reorderableIssue();
    $otherPage = $otherIssue->pages()->where('position', 1)->first();

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set("pendingUploads.{$otherPage->id}", UploadedFile::fake()->create('sample.pdf', 100, 'application/pdf'));

    expect(PageFile::where('page_id', $otherPage->id)->exists())->toBeFalse();

    Queue::assertNothingPushed();
});
