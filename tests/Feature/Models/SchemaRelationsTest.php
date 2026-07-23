<?php

use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\UserRole;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\Page;
use App\Models\Section;
use App\Models\User;

test('a user can be attached to multiple magazines and access is scoped per magazine', function () {
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $accessible = Magazine::factory()->create();
    $forbidden = Magazine::factory()->create();

    $user->magazines()->attach($accessible);

    expect($user->canAccessMagazine($accessible))->toBeTrue()
        ->and($user->canAccessMagazine($forbidden))->toBeFalse();
});

test('an admin can access any magazine without being attached', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $magazine = Magazine::factory()->create();

    expect($admin->canAccessMagazine($magazine))->toBeTrue();
});

test('a magazine slug is generated automatically from its name when omitted', function () {
    $magazine = Magazine::factory()->create(['name' => 'Motori Elettrici Oggi', 'slug' => null]);

    expect($magazine->slug)->toBe('motori-elettrici-oggi');
});

test('an issue belongs to a magazine and orders its pages by position', function () {
    $issue = Issue::factory()->create();

    Page::factory()->create(['issue_id' => $issue->id, 'position' => 3]);
    Page::factory()->create(['issue_id' => $issue->id, 'position' => 1]);
    Page::factory()->create(['issue_id' => $issue->id, 'position' => 2]);

    expect($issue->pages->pluck('position')->all())->toBe([1, 2, 3])
        ->and($issue->magazine)->toBeInstanceOf(Magazine::class);
});

test('a content can be an article or an advertisement and links back to its issue and section', function () {
    $issue = Issue::factory()->create();
    $section = Section::factory()->create(['magazine_id' => $issue->magazine_id]);

    $articleContent = Content::factory()->create([
        'issue_id' => $issue->id,
        'section_id' => $section->id,
        'type' => ContentType::Articolo,
    ]);
    Article::factory()->create(['content_id' => $articleContent->id]);

    $adContent = Content::factory()->create([
        'issue_id' => $issue->id,
        'type' => ContentType::Pubblicita,
    ]);
    Advertisement::factory()->create([
        'content_id' => $adContent->id,
        'format' => AdFormat::MezzaPaginaVerticale,
    ]);

    expect($articleContent->article)->toBeInstanceOf(Article::class)
        ->and($articleContent->section->id)->toBe($section->id)
        ->and($adContent->advertisement)->toBeInstanceOf(Advertisement::class)
        ->and($adContent->advertisement->occupiedPercentage())->toBe(50.0);
});

test('an advertisement format default percentage can be overridden manually', function () {
    $ad = Advertisement::factory()->create([
        'format' => AdFormat::UnQuartoPagina,
        'occupied_percentage_override' => 40,
    ]);

    expect($ad->occupiedPercentage())->toBe(40.0);
});

test('a page can host multiple contents each with its own occupied percentage', function () {
    $issue = Issue::factory()->create();
    $page = Page::factory()->create(['issue_id' => $issue->id]);

    $article = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Articolo]);
    $ad = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita]);

    $page->contents()->attach($article->id, ['occupied_percentage' => 50]);
    $page->contents()->attach($ad->id, ['occupied_percentage' => 50]);

    expect($page->contents)->toHaveCount(2)
        ->and($page->contents->firstWhere('id', $ad->id)->pivot->occupied_percentage)->toEqual(50)
        ->and($ad->isAssigned())->toBeTrue();

    $unassigned = Content::factory()->create(['issue_id' => $issue->id]);
    expect($issue->unassignedContents->pluck('id'))->toContain($unassigned->id)
        ->and($issue->unassignedContents->pluck('id'))->not->toContain($article->id);
});
