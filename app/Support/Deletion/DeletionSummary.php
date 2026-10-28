<?php

namespace App\Support\Deletion;

use App\Support\Uploads\UploadLimits;

/**
 * Riepilogo mostrato prima di un'eliminazione: righe già pronte da leggere
 * («3 numeri», «128 pagine», «412 file, 1,2 GB») ed eventuale elenco (i
 * numeri di una rivista).
 */
final class DeletionSummary
{
    /**
     * @param  list<string>  $lines
     * @param  list<string>  $items
     */
    public function __construct(
        public readonly array $lines,
        public readonly array $items = [],
        public readonly ?string $itemsTitle = null,
    ) {}

    /** «412 file caricati (1,2 GB)», «nessun file caricato». */
    public static function filesLine(StoredFiles $files): string
    {
        if ($files->count === 0) {
            return 'nessun file caricato';
        }

        return $files->count.' '.($files->count === 1 ? 'file caricato' : 'file caricati').' ('.self::size($files->bytes).')';
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1024 ** 3
            ? number_format($bytes / 1024 ** 3, 1, ',', '').' GB'
            : UploadLimits::humanMegabytes(max($bytes, 0));
    }

    public static function count(int $n, string $one, string $many): string
    {
        return $n.' '.($n === 1 ? $one : $many);
    }
}
