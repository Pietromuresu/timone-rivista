<?php

namespace App\Support\MagazineExport;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Le due operazioni Ghostscript dell'export sfogliabile (revisione 2, Fase
 * 5). Ghostscript è già nell'immagine Docker (serve a Imagick e al
 * conteggio pagine, PdfPageMeasurer): nessuna dipendenza nuova di sistema.
 * -dSAFER con permesso di lettura del solo file indicato, come altrove.
 */
final class Ghostscript
{
    /**
     * Riscrive un PDF in versione 1.4 senza object stream compressi: la
     * versione gratuita di FPDI non legge le tabelle xref compresse (PDF
     * 1.5+ salvati da InDesign/Acrobat). Il contenuto resta vettoriale.
     */
    public static function rewriteAsPdf14(string $input, string $output): bool
    {
        return self::run([
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/prepress',
            '-sOutputFile='.$output,
            $input,
        ], $input) && is_file($output) && filesize($output) > 0;
    }

    /**
     * Variante «bassa risoluzione per anteprima»: immagini ricompresse
     * (/ebook ≈ 150 dpi). I pdfmark ripristinano l'apertura a doppia pagina,
     * che pdfwrite non garantisce di conservare; i segnalibri li conserva.
     */
    public static function compressForPreview(string $input, string $output, string $settings): bool
    {
        return self::run([
            '-dPDFSETTINGS='.$settings,
            '-dCompatibilityLevel=1.5',
            '-sOutputFile='.$output,
            $input,
            '-c', '[ /PageLayout /TwoPageRight /DOCVIEW pdfmark',
        ], $input) && is_file($output) && filesize($output) > 0;
    }

    /** @param  list<string>  $arguments */
    private static function run(array $arguments, string $readable): bool
    {
        try {
            $process = new Process([
                'gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite',
                '--permit-file-read='.$readable,
                ...$arguments,
            ]);
            $process->setTimeout((int) config('timone.magazine_export.timeout', 1800));
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
