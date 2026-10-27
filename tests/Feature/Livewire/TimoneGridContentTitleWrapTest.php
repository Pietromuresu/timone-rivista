<?php

use App\Livewire\Timone\Grid;
use App\Models\Content;
use Livewire\Livewire;

// reorderableIssue() ed editorFor() sono definite globalmente in TimoneGridReorderTest.php.

test('a long content title is fully rendered on the card, without any truncating css class', function () {
    // Verifica CSS + presenza del testo integrale: la correttezza visiva del
    // wrap vero e proprio (dove il browser va effettivamente a capo) non è
    // asseribile senza un browser reale (nessun Dusk in questo progetto,
    // vedi Fase 0) — qui si verifica invece che (a) nessuna classe
    // Tailwind di troncamento (`truncate`/`overflow-hidden`/
    // `whitespace-nowrap`/`text-ellipsis`) sia più presente sullo span del
    // titolo contenuto, e (b) il titolo compaia per intero nell'HTML
    // renderizzato — se fosse ancora troncato via `truncate` (che agisce
    // solo via CSS, il testo pieno resterebbe comunque nel DOM) questo
    // secondo controllo da solo non basterebbe, da cui il controllo (a).
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $page = $issue->pages()->where('position', 1)->first();

    $longTitle = 'Un titolo di articolo volutamente lunghissimo per verificare che non venga mai troncato con i puntini di sospensione sulla card della griglia';
    expect(mb_strlen($longTitle))->toBeGreaterThan(80);

    $content = Content::factory()->article()->create([
        'issue_id' => $issue->id,
        'title' => $longTitle,
    ]);
    $page->contents()->attach($content->id, ['occupied_twelfths' => 6]);

    $html = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])->html();

    // Isola il blocco della card relativo a QUESTO contenuto (wire:key
    // univoco) dal resto della pagina — il pannello "contenuti da
    // assegnare" a fianco usa `truncate` sui suoi chip per un motivo
    // diverso (spazio orizzontale fisso in un elenco compatto) e non è
    // nello scope di questo fix, quindi non va fatto fallire il test.
    $marker = "page-{$page->id}-content-{$content->id}";
    $markerPos = mb_strpos($html, $marker);
    expect($markerPos)->not->toBeFalse();

    $cardBlock = mb_substr($html, $markerPos, 800);

    expect($cardBlock)->toContain($longTitle)
        ->and($cardBlock)->not->toContain('truncate')
        ->and($cardBlock)->not->toContain('whitespace-nowrap')
        ->and($cardBlock)->not->toContain('text-ellipsis')
        ->and($cardBlock)->toContain('break-words');
});
