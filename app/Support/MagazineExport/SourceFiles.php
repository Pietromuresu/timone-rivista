<?php

namespace App\Support\MagazineExport;

use App\Models\ContentFile;
use App\Models\PageFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * File sorgente dell'export (revisione 2, Fase 5): da un ContentFile o
 * PageFile al percorso locale da leggere, o null se il file manca su disco
 * o è vuoto — il composer lo disegna allora come «File mancante» invece di
 * far fallire tutto il job (richiesta della specifica). Tiene anche le
 * copie riscritte in PDF 1.4 (Ghostscript) nella cartella di lavoro del
 * job, una per file anche se usato su più pagine.
 */
final class SourceFiles
{
    /** @var array<string, string|false> percorso originale => copia 1.4 (false = non riscrivibile) */
    private array $rewritten = [];

    public function __construct(private readonly string $workDir) {}

    public function path(ContentFile|PageFile $file): ?string
    {
        try {
            $disk = Storage::disk($file->disk);

            if (! $disk->exists($file->path) || $disk->size($file->path) === 0) {
                return null;
            }

            return $disk->path($file->path);
        } catch (Throwable) {
            return null;
        }
    }

    public static function isPdf(ContentFile|PageFile $file, string $path): bool
    {
        return ($file->mime_type ?? null) === 'application/pdf' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    /** Copia in PDF 1.4 (creata alla prima richiesta), o null se Ghostscript non ci riesce. */
    public function rewrittenPdf(string $path): ?string
    {
        if (! array_key_exists($path, $this->rewritten)) {
            $output = $this->workDir.'/src-'.md5($path).'.pdf';
            $this->rewritten[$path] = Ghostscript::rewriteAsPdf14($path, $output) ? $output : false;
        }

        return $this->rewritten[$path] ?: null;
    }
}
