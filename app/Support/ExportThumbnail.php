<?php

namespace App\Support;

use App\Enums\ThumbnailStatus;
use App\Models\ContentFile;
use App\Models\PageFile;
use Illuminate\Support\Facades\Storage;

/**
 * Miniatura di una pagina incorporata nell'export PDF del timone, come
 * data URI (dompdf non legge le rotte autenticate dell'app).
 *
 * Bug corretto il 2026-10-27 (segnalato dall'utente: "nel pdf esportato non
 * vengono riportate le miniature"): l'export leggeva solo `$page->files`,
 * cioè i PDF caricati direttamente sulla pagina — il percorso rimosso col
 * Punto 4 del 2026-09-02, da quando il materiale si carica sui contenuti.
 * Per ogni pagina nuova non c'era quindi mai nulla da mostrare. Ora la
 * fonte è Page::previewFile(), la stessa della card del timone (file del
 * contenuto, con ripiego sul vecchio PageFile).
 */
final class ExportThumbnail
{
    /** Formati che dompdf sa disegnare senza estensioni particolari. */
    private const SUPPORTED = ['image/png', 'image/jpeg', 'image/gif'];

    public static function dataUri(PageFile|ContentFile|null $file): ?string
    {
        if ($file === null || $file->thumbnail_status !== ThumbnailStatus::Ready || $file->thumbnail_path === null) {
            return null;
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->thumbnail_path)) {
            return null;
        }

        // Per un PDF la miniatura è sempre un PNG generato; per un'immagine
        // è la copia dell'originale (vedi GenerateContentFileThumbnail),
        // quindi ha il suo formato. Un WEBP si mostra solo se la GD del
        // server lo sa leggere (quella del container di sviluppo no).
        $mime = $file instanceof ContentFile && $file->isImage() ? $file->mime_type : 'image/png';

        if (! in_array($mime, self::SUPPORTED, true) && ! ($mime === 'image/webp' && function_exists('imagecreatefromwebp'))) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($file->thumbnail_path));
    }
}
