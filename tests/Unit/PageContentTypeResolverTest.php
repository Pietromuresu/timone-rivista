<?php

use App\Enums\ContentType;
use App\Enums\PageContentType;
use App\Support\PageContentTypeResolver;

test('no contents resolves to bianca', function () {
    expect(PageContentTypeResolver::resolve([]))->toBe(PageContentType::Bianca);
});

test('only articles resolves to editoriale', function () {
    expect(PageContentTypeResolver::resolve(['articolo']))->toBe(PageContentType::Editoriale)
        ->and(PageContentTypeResolver::resolve(['articolo', 'articolo']))->toBe(PageContentType::Editoriale);
});

test('only ads resolves to pubblicita', function () {
    expect(PageContentTypeResolver::resolve(['pubblicita']))->toBe(PageContentType::Pubblicita);
});

test('a mix of articles and ads resolves to mista', function () {
    expect(PageContentTypeResolver::resolve(['articolo', 'pubblicita']))->toBe(PageContentType::Mista);
});

/**
 * Regressione: Eloquent::pluck('type') su Page::contents() restituisce
 * istanze di ContentType (il cast del modello si applica anche dentro
 * pluck()), non la stringa grezza della colonna — Grid::syncPageContentType()
 * passa esattamente questo a resolve(). Se questo test fallisse di nuovo,
 * il bug del colore della card mai aggiornato dopo un'assegnazione (scoperto
 * e corretto in questa sessione) si ripresenterebbe silenziosamente.
 */
test('resolves correctly when given ContentType enum instances instead of raw strings', function () {
    expect(PageContentTypeResolver::resolve([ContentType::Articolo]))->toBe(PageContentType::Editoriale)
        ->and(PageContentTypeResolver::resolve([ContentType::Pubblicita]))->toBe(PageContentType::Pubblicita)
        ->and(PageContentTypeResolver::resolve([ContentType::Articolo, ContentType::Pubblicita]))->toBe(PageContentType::Mista);
});

test('resolves correctly with a mix of enum instances and raw strings', function () {
    expect(PageContentTypeResolver::resolve([ContentType::Articolo, 'articolo']))->toBe(PageContentType::Editoriale)
        ->and(PageContentTypeResolver::resolve([ContentType::Articolo, 'pubblicita']))->toBe(PageContentType::Mista);
});
