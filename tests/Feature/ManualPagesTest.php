<?php

use App\Support\Manual\LegacyAnchors;
use App\Support\Manual\ManualBlocks;
use App\Support\Manual\ManualCompiler;
use App\Support\Manual\ManualPage;
use App\Support\Manual\ManualRepository;
use App\Support\Manual\ManualVerifier;
use App\Support\ManualRenderer;
use Illuminate\Support\Facades\File;

// Manuale a pagine (2026-10-29): integrità delle pagine, dei link, della
// mappa delle ancore vecchie, del file unico generato e del controllo
// «non si perde niente» (manuale:verifica).

function manualPages()
{
    return ManualRepository::fromConfig()->all();
}

test('every page has a valid front matter, a unique slug equal to its file name and a known category', function () {
    $files = collect(File::glob(config('timone.manual_dir').'/*.md'));

    expect(manualPages())->toHaveCount($files->count())->toHaveCount(47);

    foreach ($files as $path) {
        $page = ManualPage::parse(File::get($path), basename($path));
        expect($page->slug.'.md')->toBe(basename($path))
            ->and(ManualRepository::CATEGORIES)->toContain($page->categoria)
            ->and($page->paroleChiave)->not->toBeEmpty()
            ->and($page->sinonimi)->not->toBeEmpty();
    }
});

test('every page opens with exactly one «In breve» block, which stands on its own', function () {
    foreach (manualPages() as $page) {
        $blocks = ManualBlocks::split($page->body);
        $inBreve = array_values(array_filter($blocks, fn ($b) => $b['type'] === 'in-breve'));

        expect($inBreve)->toHaveCount(1, $page->slug)
            ->and($blocks[0]['type'])->toBe('in-breve', $page->slug.': «In breve» deve essere il primo blocco')
            ->and($page->inBreve())->not->toBeEmpty()
            // Deve stare in piedi da solo: è la risposta che mostrerà l'assistente.
            ->and(mb_strtolower($page->inBreve()))->not->toContain('come detto sopra')->not->toContain('vedi sopra');
    }
});

test('every page ends with «Vedi anche» (except the entry pages)', function () {
    foreach (manualPages()->except(['cosa-vuoi-fare']) as $page) {
        expect(str_contains($page->body, "## Vedi anche\n"))->toBeTrue("{$page->slug}: manca «Vedi anche»");
    }
});

test('every link between pages points to an existing page', function () {
    $slugs = manualPages()->keys()->all();

    foreach (manualPages() as $page) {
        preg_match_all('/\]\(manuale:([a-z0-9-]+)/', $page->body, $m);

        foreach ($m[1] as $target) {
            expect(in_array($target, $slugs, true))->toBeTrue("{$page->slug} → {$target}: pagina inesistente");
        }
    }
});

test('rendered pages have unique heading ids and no leftover block markers', function () {
    foreach (manualPages() as $page) {
        $rendered = ManualRenderer::renderPage($page, fn ($slug) => '/manuale/'.$slug);
        $ids = array_column($rendered['toc'], 'id');

        expect($ids)->toBe(array_values(array_unique($ids)), $page->slug)
            // Link `manuale:slug` non convertiti (il testo può dire «Nel manuale: …»).
            ->and($rendered['html'])->not->toContain(':::')->not->toContain('(manuale:')->not->toContain('href="manuale:');
    }
});

test('a block that is never closed is an error, not a page swallowed by «Dettagli»', function () {
    ManualBlocks::split(":::dettagli\ntesto senza chiusura");
})->throws(InvalidArgumentException::class);

test('the old-anchor map covers every heading of the archived manual and points to existing pages', function () {
    $archive = File::get(config('timone.manual_archive'));
    $oldIds = array_column(ManualRenderer::render($archive)['toc'], 'id');
    $slugs = manualPages()->keys()->all();

    expect(array_keys(LegacyAnchors::MAP))->toEqualCanonicalizing($oldIds);

    foreach (LegacyAnchors::MAP as $anchor => $slug) {
        expect(in_array($slug, $slugs, true))->toBeTrue("{$anchor} → {$slug}: pagina inesistente");
    }

    // Anche tutte le ancore usate nei link del vecchio manuale.
    preg_match_all('/\]\(#([^)]+)\)/u', $archive, $links);
    expect(array_diff(array_unique($links[1]), array_keys(LegacyAnchors::MAP)))->toBe([]);
});

test('nothing of the archived manual is lost (manuale:verifica)', function () {
    $archive = File::get(config('timone.manual_archive'));
    $text = manualPages()->map(fn ($page) => $page->titolo."\n".$page->body)->implode("\n\n");

    expect(ManualVerifier::missing($archive, $text))->toBe([]);
    $this->artisan('manuale:verifica')->assertSuccessful();
});

test('the verifier would catch a missing term and accepts a renamed button only under its new name', function () {
    $archive = 'Usa **Elimina definitivamente** e **☑️ Selezione multipla**, fino a 100 MB, poi Ctrl+Z.';

    expect(ManualVerifier::missing($archive, 'Usa ☑️ Selezione, fino a 100 MB, poi Ctrl+Z.'))
        ->toBe([['kind' => 'bold', 'term' => 'Elimina definitivamente', 'expected' => 'Elimina definitivamente']])
        ->and(ManualVerifier::missing($archive, 'Usa «Elimina definitivamente», fino a 100MB, poi Ctrl+Z.'))
        ->toBe([
            ['kind' => 'bold', 'term' => '☑️ Selezione multipla', 'expected' => '☑️ Selezione'],
            ['kind' => 'emoji', 'term' => '☑️', 'expected' => '☑️'],
        ]);
});

test('MANUALE_UTENTE.md is the file generated from the pages (run «php artisan manuale:genera» after editing a page)', function () {
    expect(File::get(config('app.manual_path')))->toBe(ManualCompiler::compile(ManualRepository::fromConfig()))
        ->and(File::get(config('app.manual_path')))->toStartWith(ManualCompiler::HEADER);

    $this->artisan('manuale:genera', ['--check' => true])->assertSuccessful();
});

test('the archive copy of the old manual is never edited', function () {
    expect(sha1_file(config('timone.manual_archive')))->toBe('fd579c4da430f44906e7d91faac82319cf10747e');
});

test('every row of the inventory (docs/manuale/INVENTARIO.md) has a destination page that exists', function () {
    $slugs = manualPages()->keys()->all();
    $rows = array_filter(file(base_path('docs/manuale/INVENTARIO.md')), fn ($line) => preg_match('/^\| S[0-9]/', $line));

    expect($rows)->toHaveCount(583);

    foreach ($rows as $row) {
        // «\|» dentro una cella è un carattere, non un separatore.
        $cells = array_map('trim', preg_split('/(?<!\\\\)\|/', $row));
        expect(in_array($cells[3], $slugs, true))->toBeTrue("{$cells[1]} → «{$cells[3]}»: pagina inesistente")
            ->and(['B', 'D', 'N', 'A', 'R'])->toContain($cells[4]);
    }
});
