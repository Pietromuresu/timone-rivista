<?php

namespace App\Support;

use App\Enums\ThumbnailStatus;
use App\Models\PageFile;
use Illuminate\Support\Collection;

/**
 * Stima — da dati REALI, mai un placeholder — quanto manca al rendering
 * delle miniature PDF ancora in coda/in corso. Non puro (interroga il
 * database per il tempo medio osservato), stesso trattamento già riservato
 * a PdfPageMeasurer/ActivityLogger.
 *
 * Deliberatamente NON legata a un singolo "batch" di upload multipagina:
 * la prima versione (2026-07-31) teneva lo stato in una proprietà Livewire
 * effimera (Grid::$thumbnailBatchProgress), che spariva a ogni refresh
 * della pagina — bug segnalato dall'utente. Qui invece tutto è ricavato
 * dallo stato persistito di ciascun PageFile (thumbnail_status,
 * created_at/updated_at), quindi è identico prima e dopo un refresh, e
 * naturalmente copre OGNI pagina ancora in elaborazione nel numero, non
 * solo l'ultimo batch confermato.
 */
class ThumbnailProgressEstimator
{
    /**
     * Tempo medio reale (secondi) impiegato dalle miniature completate più
     * di recente per le pagine indicate — null se non c'è ancora nessun
     * campione da cui stimare (onestà: nessuna stima è meglio di una
     * inventata).
     */
    public static function averageProcessingSeconds(Collection $pageIds): ?float
    {
        if ($pageIds->isEmpty()) {
            return null;
        }

        $recentlyCompleted = PageFile::query()
            ->whereIn('page_id', $pageIds)
            ->where('thumbnail_status', ThumbnailStatus::Ready)
            ->latest('updated_at')
            ->limit(20)
            ->get(['created_at', 'updated_at']);

        if ($recentlyCompleted->isEmpty()) {
            return null;
        }

        // abs(): Carbon 3 restituisce diffInSeconds() con segno (negativo
        // se l'argomento è nel passato rispetto al chiamante) — qui
        // interessa solo la durata, mai la direzione. (int): Carbon 3 può
        // restituire una frazione di secondo (microsecondi inclusi) — una
        // media in secondi interi resta comunque una stima onesta, non ha
        // senso mostrare "11.483829s" all'utente.
        return $recentlyCompleted->avg(
            fn (PageFile $file) => (int) abs($file->updated_at->diffInSeconds($file->created_at))
        );
    }

    /**
     * Avanzamento per una singola pagina ancora in coda/elaborazione —
     * tempo REALMENTE trascorso da quando il file è stato caricato, e (se
     * disponibile una media) una stima di quanto manca. `remainingSeconds`
     * resta null se non c'è ancora una media affidabile, o se il tempo
     * trascorso ha già superato la media (mostrato invece come "quasi
     * pronto" lato vista, non un numero negativo o azzerato che
     * suggerirebbe una precisione che non abbiamo).
     *
     * @return array{elapsedSeconds: int, remainingSeconds: ?int}
     */
    public static function forPageFile(PageFile $file, ?float $avgSeconds): array
    {
        // (int): stesso motivo di averageProcessingSeconds() sopra — Carbon
        // 3 può restituire una frazione di secondo, non ha senso mostrarla.
        $elapsedSeconds = (int) abs(now()->diffInSeconds($file->created_at));

        $remainingSeconds = null;

        if ($avgSeconds !== null && $elapsedSeconds < $avgSeconds) {
            $remainingSeconds = (int) round($avgSeconds - $elapsedSeconds);
        }

        return [
            'elapsedSeconds' => $elapsedSeconds,
            'remainingSeconds' => $remainingSeconds,
        ];
    }

    /**
     * Avanzamento aggregato su tutte le pagine del numero ancora in coda o
     * in elaborazione — null se non ce n'è nessuna. La coda è sequenziale
     * (un solo processo worker, vedi docker-compose.yml), quindi per una
     * pagina già "in elaborazione" (Processing) si sottrae il tempo già
     * speso dalla stima; per le altre, ancora in coda (Pending), si conta
     * l'intera media.
     *
     * @param  Collection<int, PageFile>  $pendingFiles
     * @return array{pending: int, remainingSeconds: ?int}|null
     */
    public static function aggregate(Collection $pendingFiles, ?float $avgSeconds): ?array
    {
        if ($pendingFiles->isEmpty()) {
            return null;
        }

        $remainingSeconds = null;

        if ($avgSeconds !== null) {
            $remainingSeconds = (int) round($pendingFiles->sum(function (PageFile $file) use ($avgSeconds) {
                if ($file->thumbnail_status !== ThumbnailStatus::Processing) {
                    return $avgSeconds;
                }

                $elapsed = (int) abs(now()->diffInSeconds($file->updated_at));

                return max(0, $avgSeconds - $elapsed);
            }));
        }

        return [
            'pending' => $pendingFiles->count(),
            'remainingSeconds' => $remainingSeconds,
        ];
    }
}
