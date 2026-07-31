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

function fakeArticleContent(float $occupiedPercentage): Content
{
    $content = new Content(['type' => ContentType::Articolo]);
    // occupied_percentage è un cast decimal:2: passarla come stringa evita
    // il deprecation warning di brick/math sui float, stesso accorgimento
    // già usato in Grid::assignContent()/updateContentPercentage().
    $content->setRelation('pivot', new PageContent(['occupied_percentage' => (string) $occupiedPercentage]));

    return $content;
}

function fakeAdContent(float $occupiedPercentage, AdFormat $format, AdConfirmationStatus $status): Content
{
    $content = new Content(['type' => ContentType::Pubblicita]);
    $content->setRelation('pivot', new PageContent(['occupied_percentage' => (string) $occupiedPercentage]));
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
        fakePageWithContents([fakeArticleContent(100)]),
        fakePageWithContents([fakeArticleContent(100)]),
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
        fakePageWithContents([fakeAdContent(100, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata)]),
        fakePageWithContents([fakeAdContent(100, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata)]),
        fakePageWithContents([fakeArticleContent(100)]),
        fakePageWithContents([fakeArticleContent(100)]),
    ]);

    $summary = AdLoadCalculator::summarize($pages);

    expect($summary['totalPages'])->toBe(4)
        ->and($summary['adEquivalentPages'])->toBe(2.0)
        ->and($summary['adLoadPercentage'])->toBe(50.0)
        ->and($summary['assignedAdCount'])->toBe(2)
        ->and($summary['formatBreakdown'])->toBe(['pagina_intera' => 2])
        ->and($summary['confirmationBreakdown'])->toBe(['confermata' => 2]);
});

test('a mixed page sums ad and editorial shares independently from occupied_percentage', function () {
    $pages = new Collection([
        fakePageWithContents([
            fakeAdContent(40, AdFormat::UnQuartoPagina, AdConfirmationStatus::InTrattativa),
            fakeArticleContent(60),
        ]),
    ]);

    $summary = AdLoadCalculator::summarize($pages);

    expect($summary['totalPages'])->toBe(1)
        ->and($summary['adEquivalentPages'])->toBe(0.4)
        ->and($summary['editorialEquivalentPages'])->toBe(0.6)
        ->and($summary['adLoadPercentage'])->toBe(40.0)
        ->and($summary['formatBreakdown'])->toBe(['un_quarto_pagina' => 1])
        ->and($summary['confirmationBreakdown'])->toBe(['in_trattativa' => 1]);
});

// Fase 3 (§3): pubblicità "prenotate" — non presenti su nessuna pagina,
// quindi mai nel loop su $pages sopra, passate a summarize() come secondo
// argomento. fakeReservedAdvertisement() replica solo i campi letti da
// Advertisement::occupiedPercentage() (format + eventuale override),
// nessuna query/DB richiesta.

function fakeReservedAdvertisement(AdFormat $format, AdConfirmationStatus $status, ?float $percentageOverride = null): Advertisement
{
    return new Advertisement([
        'format' => $format,
        'confirmation_status' => $status,
        'occupied_percentage_override' => $percentageOverride,
    ]);
}

test('a reservation contributes its format default percentage to the ad load even with no pages assigned', function () {
    $reserved = new Collection([fakeReservedAdvertisement(AdFormat::PaginaIntera, AdConfirmationStatus::InTrattativa)]);

    $summary = AdLoadCalculator::summarize(new Collection([
        fakePageWithContents([fakeArticleContent(100)]),
        fakePageWithContents([fakeArticleContent(100)]),
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
        fakePageWithContents([fakeAdContent(100, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata)]),
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

test('a manual percentage override on a reservation is respected, same as for a placed ad', function () {
    $reserved = new Collection([fakeReservedAdvertisement(AdFormat::PaginaIntera, AdConfirmationStatus::Confermata, percentageOverride: 30.0)]);

    $summary = AdLoadCalculator::summarize(new Collection, $reserved);

    expect($summary['reservedAdEquivalentPages'])->toBe(0.3);
});

test('omitting the reserved advertisements argument keeps the previous behaviour unchanged', function () {
    $pages = new Collection([fakePageWithContents([fakeAdContent(100, AdFormat::PaginaIntera, AdConfirmationStatus::Confermata)])]);

    $summary = AdLoadCalculator::summarize($pages);

    expect($summary['adEquivalentPages'])->toBe(1.0)
        ->and($summary['placedAdEquivalentPages'])->toBe(1.0)
        ->and($summary['reservedAdEquivalentPages'])->toBe(0.0)
        ->and($summary['reservedAdCount'])->toBe(0);
});

test('the format and confirmation breakdowns tally multiple ads of the same kind', function () {
    $pages = new Collection([
        fakePageWithContents([fakeAdContent(50, AdFormat::MezzaPaginaOrizzontale, AdConfirmationStatus::Confermata)]),
        fakePageWithContents([fakeAdContent(50, AdFormat::MezzaPaginaOrizzontale, AdConfirmationStatus::Confermata)]),
        fakePageWithContents([fakeAdContent(50, AdFormat::MezzaPaginaVerticale, AdConfirmationStatus::Annullata)]),
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
