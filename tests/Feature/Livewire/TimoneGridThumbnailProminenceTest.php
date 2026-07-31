<?php

use App\Livewire\Timone\Grid;
use App\Models\Issue;
use App\Models\PageFile;
use Livewire\Livewire;

// Segnalato dall'utente dopo un test dal vivo (2026-07-31, prompt di
// follow-up "verifica reale e correzione di quanto non funziona"): la
// miniatura PDF pronta deve essere l'elemento visivo principale della
// card, non un'icona minuscola in fondo insieme a 📤/🕐/📝. Prima della
// correzione questo test fallisce (la miniatura è ancora `w-6 h-8`,
// 24×32px, nella riga di icone in fondo).

test('a ready thumbnail is rendered as a prominent image, not a tiny bottom-row icon', function () {
    $issue = Issue::factory()->create(['total_pages' => 1]);
    $page = $issue->pages()->first();
    $latestFile = PageFile::factory()->for($page)->ready()->create();

    $html = Livewire::test(Grid::class, ['issue' => $issue])->html();

    // La miniatura pronta deve comparire con una classe che la rende
    // grande/dominante (larghezza piena della card), non più la vecchia
    // icona 24x32px "w-6 h-8" nascosta tra gli altri simboli in fondo.
    expect($html)->not->toContain('w-6 h-8 object-cover')
        ->toContain('w-full')
        ->toContain(route('page-files.thumbnail', $latestFile));
});
