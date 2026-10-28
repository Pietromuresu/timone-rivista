<?php

namespace App\Support;

use App\Models\Content;

/**
 * Esito di BulkContentImporter::import() per un file: il contenuto creato
 * e, se il titolo era già usato nel numero, il titolo originale (per
 * l'avviso «esisteva già: creato come … (2)»).
 */
final class BulkImportResult
{
    public function __construct(
        public readonly Content $content,
        public readonly ?string $duplicateOf = null,
    ) {}
}
