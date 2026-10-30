<?php

namespace App\Support;

use App\Enums\ThumbnailStatus;
use App\Models\ContentFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Miniature già ritagliate per l'export PDF del timone (2026-10-30). dompdf
 * non ritaglia in modo affidabile con il CSS (provato nella Fase B0:
 * `overflow: hidden` a volte disegna un riquadro vuoto, `clip-path` non
 * esiste), quindi l'export riceve l'immagine della sola finestra
 * (CropPlan), tagliata con GD — già nel server, niente Imagick.
 *
 * Cache su disco legata a file, miniatura, finestra (cioè pagina del PDF,
 * forma, posizione e rotazione del pezzo) e larghezza: cambiando uno di
 * questi il nome cambia e si genera un'immagine nuova. La card le prepara
 * in coda (WarmCroppedThumbnail) appena un contenuto è ritagliato; se
 * all'export ne manca una, si fa sul momento (pochi millisecondi).
 */
final class CroppedThumbnails
{
    /** Larghezza massima di un ritaglio, px (≈ 300 dpi per un riquadro di 10 cm). */
    public const MAX_WIDTH = 1200;

    /** @param  array{0: float, 1: float, 2: float, 3: float}  $window */
    public static function relativePath(ContentFile $file, array $window): string
    {
        $key = sha1(implode('|', [$file->id, self::source($file), ...array_map(fn ($n) => round($n, 2), $window)]));

        return 'contents/'.$file->content_id.'/crops/'.$key.'.png';
    }

    public static function exists(ContentFile $file, array $window): bool
    {
        return Storage::disk('local')->exists(self::relativePath($file, $window));
    }

    /**
     * Percorso del ritaglio, generandolo se manca; null se la miniatura non
     * c'è o GD non sa leggerla.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $window
     */
    public static function ensure(ContentFile $file, array $window): ?string
    {
        $relative = self::relativePath($file, $window);
        $disk = Storage::disk('local');

        if ($disk->exists($relative)) {
            return $relative;
        }

        $source = self::source($file);

        if ($file->thumbnail_status !== ThumbnailStatus::Ready || $source === null || ! Storage::disk($file->disk)->exists($source)) {
            return null;
        }

        try {
            $image = @imagecreatefromstring(Storage::disk($file->disk)->get($source));

            if ($image === false) {
                return null;
            }

            [$x, $y, $w, $h] = $window;
            $sourceW = imagesx($image);
            $sourceH = imagesy($image);
            $cropX = (int) round($x / 100 * $sourceW);
            $cropY = (int) round($y / 100 * $sourceH);
            $cropW = max(1, (int) round($w / 100 * $sourceW));
            $cropH = max(1, (int) round($h / 100 * $sourceH));
            $scale = min(1, self::MAX_WIDTH / $cropW);
            $outW = max(1, (int) round($cropW * $scale));
            $outH = max(1, (int) round($cropH * $scale));

            // Sfondo bianco: una finestra che esce dall'immagine (pagina
            // centrata con proporzioni diverse) non diventa nera.
            $out = imagecreatetruecolor($outW, $outH);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $image, 0, 0, $cropX, $cropY, $outW, $outH, $cropW, $cropH);

            ob_start();
            imagepng($out);
            $disk->put($relative, (string) ob_get_clean());

            return $relative;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Data URI per dompdf (che non legge le rotte autenticate). */
    public static function dataUri(ContentFile $file, array $window): ?string
    {
        $relative = self::ensure($file, $window);

        return $relative === null ? null : 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($relative));
    }

    /** La miniatura grande se c'è (nitida), se no quella normale. */
    private static function source(ContentFile $file): ?string
    {
        return $file->large_thumbnail_path ?? $file->thumbnail_path;
    }
}
