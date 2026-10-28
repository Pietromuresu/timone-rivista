<?php

namespace App\Support\MagazineExport;

/** Esito della composizione: pagine scritte e contenuti disegnati come «File mancante». */
final class ComposeResult
{
    public function __construct(
        public readonly int $pageCount,
        public readonly int $missingFiles,
    ) {}
}
