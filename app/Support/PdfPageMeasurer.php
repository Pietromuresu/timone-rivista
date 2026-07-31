<?php

namespace App\Support;

use Imagick;
use Throwable;

/**
 * Estrae numero di pagine e dimensioni reali (mm) da un PDF via Imagick —
 * esplicitamente NON puro (I/O sul filesystem + estensione nativa), stesso
 * trattamento già riservato ad ActivityLogger: nessuna interfaccia/DI per
 * un'unica implementazione (CLAUDE.md, regola 3). Richiede `ext-imagick`
 * (assente sul PHP locale di sviluppo, presente in Docker — stesso vincolo
 * già documentato in HANDOFF.md per App\Jobs\GeneratePageFileThumbnail).
 *
 * Ogni metodo torna `null` invece di lanciare eccezioni su un file
 * illeggibile/corrotto: chiamato sia in un'azione Livewire interattiva
 * (Grid::uploadPageFile(), che deve degradare a "trattalo come 1 pagina"
 * senza rompere l'upload) sia in un Job in coda (che deve poter marcare lo
 * stato "non verificabile" invece di far fallire il job — §2.3 del prompt).
 */
class PdfPageMeasurer
{
    private const POINTS_PER_INCH = 72;

    private const MM_PER_INCH = 25.4;

    public static function pageCount(string $absolutePath): ?int
    {
        try {
            $imagick = new Imagick;
            $imagick->pingImage($absolutePath);
            $count = $imagick->getNumberImages();
            $imagick->clear();

            return $count;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Dimensioni reali (mm) della pagina interna indicata (1-based).
     *
     * La risoluzione viene forzata a 72 DPI prima della lettura: a 72 DPI
     * un pixel Imagick corrisponde esattamente a un punto PDF (1/72 di
     * pollice), quindi la geometria letta è la dimensione del MediaBox
     * senza dover dipendere dalla risoluzione "di stampa" con cui il PDF è
     * stato generato.
     *
     * Usa readImage(), non pingImage(): verificato con un PDF multipagina
     * reale dentro Docker che pingImage() con il selettore di pagina
     * `file.pdf[N]` non seleziona in modo affidabile la pagina richiesta
     * su un PDF con xref ricostruito da Ghostscript (torna sempre la
     * geometria della prima pagina) — readImage() (usato anche da
     * Spatie\PdfToImage per lo stesso motivo) decodifica davvero la
     * pagina indicata. Costo accettabile: a 72 DPI la decodifica resta
     * comunque leggera, lo stesso ordine di grandezza di un ping.
     *
     * @return array{width: float, height: float}|null
     */
    public static function pageSizeMm(string $absolutePath, int $pageNumber): ?array
    {
        try {
            $imagick = new Imagick;
            $imagick->setResolution(self::POINTS_PER_INCH, self::POINTS_PER_INCH);
            $imagick->readImage(sprintf('%s[%d]', $absolutePath, $pageNumber - 1));

            $geometry = $imagick->getImageGeometry();
            $imagick->clear();

            return [
                'width' => round($geometry['width'] / self::POINTS_PER_INCH * self::MM_PER_INCH, 1),
                'height' => round($geometry['height'] / self::POINTS_PER_INCH * self::MM_PER_INCH, 1),
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
