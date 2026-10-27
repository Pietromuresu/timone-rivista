<?php

namespace App\Support;

use App\Models\ContentFile;
use Illuminate\Support\Facades\Storage;

/**
 * Rapporto larghezza/altezza della pagina di un file caricato (revisione 2,
 * Fase 2.4, 2026-10-27), letto dalla sua miniatura: per un PDF è la pagina
 * rasterizzata in proporzione, per un'immagine è la copia del file. Non
 * serve rileggere il PDF (lento su file grandi): le proporzioni della
 * miniatura sono quelle della pagina a meno di un pixel su 400.
 * Usato dal job delle miniature (file nuovi) e da `timone:file-ratios`
 * (file già caricati). null se la miniatura manca o non è leggibile.
 */
final class ContentFileAspectRatio
{
    public static function measure(ContentFile $file): ?float
    {
        if ($file->thumbnail_path === null || ! Storage::disk($file->disk)->exists($file->thumbnail_path)) {
            return null;
        }

        $size = @getimagesize(Storage::disk($file->disk)->path($file->thumbnail_path));

        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            return null;
        }

        return round($size[0] / $size[1], 5);
    }
}
