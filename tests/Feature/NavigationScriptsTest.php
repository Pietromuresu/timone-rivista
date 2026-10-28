<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\File;

// 2026-10-28: il dropdown della navigazione (Alpine, x-dropdown) non si
// apriva sulle pagine senza componenti Livewire, perché lo script di
// Livewire (che porta Alpine) veniva inserito solo dove c'era un
// componente. Ora è nel layout: presente una e una sola volta ovunque.
// Una richiesta per test: Livewire ricorda tra una richiesta e l'altra
// dello stesso test se ha già inserito gli script.

test('every page of the app layout loads the Livewire/Alpine script exactly once', function (string $url) {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

    expect(preg_match_all('#<script[^>]+livewire(\.min)?\.js#', $html))->toBe(1);
})->with([
    'profilo (vista da controller)' => '/profile',
    'manuale (vista da controller)' => '/manuale',
    'riviste (pagina Livewire)' => '/riviste',
    'utenti (pagina Livewire)' => '/utenti',
]);

// 2026-10-28: in produzione `@click="open = ! open"` arrivava al browser
// riscritto come `open=""` (trigger senza click). Il menu usa ora la forma
// x-on:click, come il resto del progetto: nessun `@click` nelle viste.
test('the navigation dropdown uses x-on:click and no view uses the @click shorthand', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $html = $this->actingAs($admin)->get('/riviste')->getContent();

    expect($html)->toContain('<div x-on:click="open = ! open">')
        ->and(collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => preg_match('/\s@click[.=]/', preg_replace('/\{\{--.*?--\}\}/s', '', $file->getContents())))
            ->map->getRelativePathname()->values()->all())->toBe([]);
});
