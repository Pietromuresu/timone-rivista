<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * PDF minimo ma valido a una pagina, con xref table — verificato con una
 * conversione Imagick/Ghostscript reale. Usato dai test del Job di generazione
 * thumbnail per evitare di versionare un fixture binario nel repo.
 */
function minimalValidPdfBytes(): string
{
    return <<<'PDF'
    %PDF-1.4
    1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
    2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
    3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>endobj
    4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj
    5 0 obj<</Length 44>>stream
    BT /F1 24 Tf 20 100 Td (Test PDF) Tj ET
    endstream
    endobj
    xref
    0 6
    trailer<</Size 6/Root 1 0 R>>
    startxref
    0
    %%EOF
    PDF;
}

/**
 * Converte millimetri in punti PDF (1pt = 1/72 pollice) — usato dai test
 * di App\Support\PdfFormatChecker/PdfPageMeasurer per costruire fixture
 * PDF con una dimensione mm nota, invece di lavorare a occhio in punti.
 */
function mmToPt(float $mm): float
{
    return round($mm / 25.4 * 72, 2);
}

/**
 * PDF valido a più pagine, ciascuna con la propria MediaBox in punti —
 * stesso stile "minimo ma valido" di minimalValidPdfBytes() sopra (xref
 * volutamente incompleto, Ghostscript/Imagick lo ricostruiscono
 * scansionando gli oggetti), esteso a N pagine invece di una sola. Usato
 * dai test della Fase 2 (upload PDF multipagina, controllo formato) che
 * richiedono ext-imagick — marcati `skip` altrimenti, stesso trattamento
 * di GeneratePageFileThumbnailTest.
 *
 * @param  list<array{0: float, 1: float}>  $pageSizesPt  Una coppia [larghezza, altezza] in punti per ciascuna pagina
 */
function multiPagePdfBytes(array $pageSizesPt): string
{
    $firstPageObjNum = 3;
    $kids = [];
    $pageObjects = [];

    foreach (array_values($pageSizesPt) as $i => [$width, $height]) {
        $objNum = $firstPageObjNum + $i;
        $kids[] = "{$objNum} 0 R";
        $pageObjects[] = "{$objNum} 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 {$width} {$height}]/Resources<<>>>>endobj";
    }

    $kidsList = implode(' ', $kids);
    $count = count($pageSizesPt);

    return "%PDF-1.4\n"
        ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[{$kidsList}]/Count {$count}>>endobj\n"
        .implode("\n", $pageObjects)."\n"
        ."xref\n0 1\ntrailer<</Size 1/Root 1 0 R>>\nstartxref\n0\n%%EOF";
}
