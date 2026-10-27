<?php

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\Page;
use App\Models\PageContent;
use App\Support\AdLoadCalculator;
use Illuminate\Support\Collection;

/**
 * Costruisce una pagina "finta" (nessuna query, nessun DB) con i suoi
 * contenuti già assegnati e la relativa pubblicità, replicando a mano le
 * relazioni che normalmente arrivano da Grid::render() via Eloquent.
 */
function fakePageWithContents(array $contents): Page
{
    $page = new Page;
    $page->setRelation('contents', new Collection($contents));

    return $page;
}

// Fase 3 (2026-10-27): lo spazio sul pivot è in dodicesimi (12 = pagina intera).
function fakeArticleContent(int $twelfths): Content
{
    $content = new Content(['type' => ContentType::Articolo]);
    $content->setRelation('pivot', new PageContent(['occupied_twelfths' => $twelfths]));

    return $content;
}

function fakeAdContent(int $twelfths, AdFormat $format, AdConfirmationStatus $status): Content
{
    $content = new Content(['type' => ContentType::Pubblicita]);
    $content->setRelation('pivot', new PageContent(['occupied_twelfths' => $twelfths]));
    $content->setRelation('advertisement', new Advertisement([
        'format' => $format,
        'confirmation_status' => $status,
    ]));

    return $content;
}

test('an issue with no pages has zero load', function () {
    $summary = AdLoadCalculator::summarize(new Collection);

    expect($summary['totalPages'])->toBe(0)
        ->and($summary['adLoadPercentage'])->toBe(0.0);
});

test('editorial-only pages contribute nothing to the ad load', function () {
    $pages = new Collection([
        fakePageWithContents([fakeArticleContent(12)]),
        fakePageWithContents([fakeArticleContent(12)]),
    ]);

    $summary = AdLoadCalculator::summarize($pages);

    expect($summary['totalPages'])->toBe(2)
        ->and($summary['adEquivalentPages'])->toBe(0.0)
        ->and($summary['editorialEquivalentPages'])->toBe(2.0)
        ->and($summary['adLoadPercentage'])->toBe(0.0)
        ->and($summary['assignedAdCount'])->toBe(0);
});

test('full page ads sum to whole equivalent pages and the correct percentage', function () {
    $pages = new Collection([
        fakePageWithContents([fakeAdContent(12, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata)]),
        fakePageWithContents([fakeAdContent(12, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata)]),
        fakePageWithContents([fakeArticleContent(12)]),
        fakePageWithContents([fakeArticleContent(12)]),
    ]);

    $summary = AdLoadCalculator::summarize($pages);

    expect($summary['totalPages'])->toBe(4)
        ->and($summary['adEquivalentPages'])->toBe(2.0)
        ->and($summary['adLoadPercentage'])->toBe(50.0)
        ->and($summary['assignedAdCount'])->toBe(2)
        ->and($summary['formatBreakdown'])->toBe(['pagina_intera' => 2])
        ->and($summary['confirmationBreakdown'])->toBe(['confermata' => 2]);
});

test('a mixed page sums ad and editorial shares independently from the pivot twelfths', function () {
    $pages = new Collection([
        fakePageWithContents([
            fakeAdContent(3, AdFormat::UnQuartoPagina, AdConfirmationStatus::InTrattativa),
            fakeArticleContent(9),
        ]),
    ]);

    $summary = AdLoadCalculator::summarize($pages);

    expect($summary['totalPages'])->toBe(1)
        ->and($summary['adEquivalentPages'])->toBe(0.25)
        ->and($summary['editorialEquivalentPages'])->toBe(0.75)
        ->and($summary['adLoadPercentage'])->toBe(25.0)
        ->and($summary['formatBreakdown'])->toBe(['un_quarto_pagina' => 1])
        ->and($summary['confirmationBreakdown'])->toBe(['in_trattativa' => 1]);
});

// Fase 3 (§3): pubblicità "prenotate" — non presenti su nessuna pagina,
// quindi mai nel loop su $pages sopra, passate a summarize() come secondo
// argomento. fakeReservedAdvertisement() replica solo i campi letti dal
// calcolatore: formato, stato e (Fase 3) l'occupazione del suo Content,
// proposta dal formato se non indicata — nessuna query/DB richiesta.

function fakeReservedAdvertisement(AdFormat $format, AdConfirmationStatus $status, ?int $twelfths = null): Advertisement
{
    $advertisement = new Advertisement([
        'format' => $format,
        'confirmation_status' => $status,
    ]);
    $advertisement->setRelation('content', new Content([
        'type' => ContentType::Pubblicita,
        'occupancy_twelfths' => $twelfths ?? $format->twelfths(),
    ]));

    return $advertisement;
}

test('a reservation contributes its occupancy to the ad load even with no pages assigned', function () {
    $reserved = new Collection([fakeReservedAdvertisement(AdFormat::PaginaIntera, AdConfirmationStatus::InTrattativa)]);

    $summary = AdLoadCalculator::summarize(new Collection([
        fakePageWithContents([fakeArticleContent(12)]),
        fakePageWithContents([fakeArticleContent(12)]),
    ]), $reserved);

    // 1 pagina intera prenotata (100%) su 2 pagine totali = 50%.
    expect($summary['totalPages'])->toBe(2)
        ->and($summary['placedAdEquivalentPages'])->toBe(0.0)
        ->and($summary['reservedAdEquivalentPages'])->toBe(1.0)
        ->and($summary['adEquivalentPages'])->toBe(1.0)
        ->and($summary['adLoadPercentage'])->toBe(50.0)
        ->and($summary['reservedAdCount'])->toBe(1)
        ->and($summary['assignedAdCount'])->toBe(0);
});

test('placed and reserved ad load add up together, kept separate for transparency', function () {
    $pages = new Collection([
        fakePageWithContents([fakeAdContent(12, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata)]),
    ]);
    $reserved = new Collection([fakeReservedAdvertisement(AdFormat::MezzaPaginaOrizzontale, AdConfirmationStatus::InTrattativa)]);

    $summary = AdLoadCalculator::summarize($pages, $reserved);

    expect($summary['placedAdEquivalentPages'])->toBe(1.0)
        ->and($summary['reservedAdEquivalentPages'])->toBe(0.5)
        ->and($summary['adEquivalentPages'])->toBe(1.5)
        ->and($summary['formatBreakdown'])->toBe([
            'pagina_intera' => 1,
            'mezza_pagina_orizzontale' => 1,
        ]);
});

test('a reservation whose occupancy was changed by hand counts what it says, not its format', function () {
    // Pagina intera ridotta a 1/3 nel modulo.
    $reserved = new Collection([fakeReservedAdvertisement(AdFormat::PaginaIntera, AdConfirmationStatus::Confermata, twelfths: 4)]);

    $summary = AdLoadCalculator::summarize(new Collection, $reserved);

    expect($summary['reservedAdEquivalentPages'])->toBe(0.33)
        ->and($summary['adTwelfths'])->toBe(4);
});

// Fase 3: test richiesto dalla specifica — percentuale esatta in dodicesimi.
// Tre terzi di pagina pubblicitari su 3 pagine = 1 pagina su 3 = 33.33%,
// mentre le vecchie percentuali (33.3 × 3 = 99.9) davano 33.3%.
test('the ad percentage is exact when computed in twelfths', function () {
    $pages = new Collection([
        fakePageWithContents([fakeAdContent(4, AdFormat::UnTerzoPaginaVerticale, AdConfirmationStatus::Confermata), fakeArticleContent(8)]),
        fakePageWithContents([fakeAdContent(4, AdFormat::UnTerzoPaginaVerticale, AdConfirmationStatus::Confermata), fakeArticleContent(8)]),
        fakePageWithContents([fakeAdContent(4, AdFormat::Piedino, AdConfirmationStatus::Confermata), fakeArticleContent(8)]),
    ]);

    $summary = AdLoadCalculator::summarize($pages);

    expect($summary['adTwelfths'])->toBe(12)
        ->and($summary['capacityTwelfths'])->toBe(36)
        ->and($summary['adEquivalentPages'])->toBe(1.0)
        ->and($summary['adLoadPercentage'])->toBe(33.33);
});

test('omitting the reserved advertisements argument keeps the previous behaviour unchanged', function () {
    $pages = new Collection([fakePageWithContents([fakeAdContent(12, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata)])]);

    $summary = AdLoadCalculator::summarize($pages);

    expect($summary['adEquivalentPages'])->toBe(1.0)
        ->and($summary['placedAdEquivalentPages'])->toBe(1.0)
        ->and($summary['reservedAdEquivalentPages'])->toBe(0.0)
        ->and($summary['reservedAdCount'])->toBe(0);
});

test('the format and confirmation breakdowns tally multiple ads of the same kind', function () {
    $pages = new Collection([
        fakePageWithContents([fakeAdContent(6, AdFormat::MezzaPaginaOrizzontale, AdConfirmationStatus::Confermata)]),
        fakePageWithContents([fakeAdContent(6, AdFormat::MezzaPaginaOrizzontale, AdConfirmationStatus::Confermata)]),
        fakePageWithContents([fakeAdContent(6, AdFormat::MezzaPaginaVerticale, AdConfirmationStatus::Annullata)]),
    ]);

    $summary = AdLoadCalculator::summarize($pages);

    expect($summary['formatBreakdown'])->toBe([
        'mezza_pagina_orizzontale' => 2,
        'mezza_pagina_verticale' => 1,
    ])
        ->and($summary['confirmationBreakdown'])->toBe([
            'confermata' => 2,
            'annullata' => 1,
        ]);
});
