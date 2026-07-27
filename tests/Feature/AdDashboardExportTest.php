<?php

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\UserRole;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\Issue;
use App\Models\User;

// reorderableIssue() ed editorFor() sono definite globalmente in
// TimoneGridReorderTest.php (issue con total_pages = 6).

test('an editor can download the csv report with the correct figures', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);
    Advertisement::factory()->create([
        'content_id' => $content->id,
        'format' => AdFormat::PaginaIntera,
        'occupied_percentage_override' => null,
        'confirmation_status' => AdConfirmationStatus::Confermata,
    ]);
    $page = $issue->pages()->where('position', 1)->first();
    $page->contents()->attach($content->id, ['occupied_percentage' => '100']);

    $response = $this->actingAs($user)->get(route('issues.export.ad-dashboard-csv', [$issue->magazine, $issue]));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain($issue->magazine->name)
        ->toContain($issue->title)
        ->toContain('Pagine totali')
        ->toContain('16.67')
        ->toContain('Pagina intera')
        ->toContain('Confermata');
});

test('an editor can download the pdf report', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);

    $response = $this->actingAs($user)->get(route('issues.export.ad-dashboard-pdf', [$issue->magazine, $issue]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('a sola lettura user (read-only) can still export the report', function () {
    $issue = reorderableIssue();
    $user = User::factory()->create(['role' => UserRole::SolaLettura]);
    $user->magazines()->attach($issue->magazine);

    $this->actingAs($user)->get(route('issues.export.ad-dashboard-csv', [$issue->magazine, $issue]))->assertOk();
});

test('a user without access to the magazine cannot export the report', function () {
    $issue = reorderableIssue();
    $outsider = User::factory()->create(['role' => UserRole::Redattore]);

    $this->actingAs($outsider)->get(route('issues.export.ad-dashboard-csv', [$issue->magazine, $issue]))->assertForbidden();
});

test('a guest cannot export the report', function () {
    $issue = reorderableIssue();

    $this->get(route('issues.export.ad-dashboard-csv', [$issue->magazine, $issue]))->assertRedirect(route('login'));
});

test('an issue url is not found when the issue does not belong to the magazine in the url', function () {
    $issue = reorderableIssue();
    $otherIssue = reorderableIssue();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('issues.export.ad-dashboard-csv', [$issue->magazine, $otherIssue]))
        ->assertNotFound();
});
