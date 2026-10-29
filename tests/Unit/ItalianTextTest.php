<?php

use App\Support\Manual\ItalianText;

// Ricerca nel manuale (2026-10-29): normalizzazione dell'italiano. Gli stessi
// casi li controlla tests/Feature/ManualSearchTest.php con il JavaScript del
// browser, così le due implementazioni non possono divergere.

test('the italian normalization gives the expected words', function (string $testo, array $parole) {
    expect(ItalianText::tokens($testo))->toBe($parole);
})->with(fn () => array_map(fn ($case) => [$case['testo'], $case['parole']], json_decode(file_get_contents(__DIR__.'/../fixtures/manual-text.json'), true)));

test('a stem never gets shorter than the minimum, numbers are kept as they are', function () {
    expect(ItalianText::stem('ore'))->toBe('ore')
        ->and(ItalianText::stem('casa'))->toBe('cas')
        ->and(ItalianText::stem('1000'))->toBe('1000')
        ->and(ItalianText::stem('3mm'))->toBe('3mm')
        ->and(ItalianText::tokens('la e di per'))->toBe([]);
});
