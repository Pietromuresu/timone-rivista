<?php

namespace App\Support;

use App\Enums\ThumbnailStatus;
use App\Models\ContentFile;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;
use Throwable;

/**
 * Quello che serve al ritaglio oltre alla miniatura normale (2026-10-30):
 * proporzioni, misure della pagina (MediaBox/TrimBox, PdfBoxes) e la
 * miniatura grande da 1200 px. Un solo punto, usato dal job delle
 * miniature (file nuovi), dal job di completamento (file vecchi, accodato
 * dalla card la prima volta che servono) e da `timone:file-ratios`.
 *
 * Ogni passo è indipendente: se manca Imagick (la miniatura grande non si
 * genera) le misure si salvano lo stesso, e la card usa quella normale.
 */
final class ContentFileExtras
{
    public const LARGE_WIDTH = 1200;

    /** Manca qualcosa da calcolare? (la card accoda il completamento solo allora) */
    public static function incomplete(ContentFile $file): bool
    {
        return $file->thumbnail_status === ThumbnailStatus::Ready
            && ($file->aspect_ratio === null || $file->large_thumbnail_path === null || (! $file->isImage() && $file->trim_box === null));
    }

    public static function complete(ContentFile $file): void
    {
        if ($file->thumbnail_status !== ThumbnailStatus::Ready) {
            return;
        }

        $changes = [];

        if ($file->aspect_ratio === null) {
            $changes['aspect_ratio'] = ContentFileAspectRatio::measure($file);
        }

        if ($file->isImage()) {
            // La miniatura di un'immagine è già la copia a piena risoluzione.
            if ($file->large_thumbnail_path === null) {
                $changes['large_thumbnail_path'] = $file->thumbnail_path;
            }
        } else {
            $path = Storage::disk($file->disk)->path($file->path);

            if ($file->trim_box === null) {
                $changes['trim_box'] = PdfBoxes::measure($path, max(1, (int) $file->pdf_page_number)) ?? ['media' => null, 'trim' => null];
            }

            if ($file->large_thumbnail_path === null) {
                $changes['large_thumbnail_path'] = self::renderLarge($file, $path);
            }
        }

        $changes = array_filter($changes, fn ($value) => $value !== null);

        if ($changes !== []) {
            $file->forceFill($changes)->save();
        }
    }

    /** Pagina del PDF a 1200 px, accanto alla miniatura normale; null se non si può. */
    private static function renderLarge(ContentFile $file, string $absolutePdfPath): ?string
    {
        try {
            $relative = 'contents/'.$file->content_id.'/thumbnails/'.pathinfo($file->path, PATHINFO_FILENAME).'-p'.max(1, (int) $file->pdf_page_number).'-'.self::LARGE_WIDTH.'.png';
            Storage::disk('local')->makeDirectory(dirname($relative));

            (new Pdf($absolutePdfPath))
                ->selectPage(max(1, (int) $file->pdf_page_number))
                ->format(OutputFormat::Png)
                ->resolution(200)
                ->thumbnailSize(self::LARGE_WIDTH)
                ->save(Storage::disk('local')->path($relative));

            return $relative;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
