<?php

namespace App\Support\MagazineExport;

use App\Enums\MagazineExportStatus;
use App\Enums\MagazineExportVariant;
use App\Jobs\GenerateMagazinePdf;
use App\Models\Issue;
use App\Models\MagazineExport;

/**
 * Richiesta di un export sfogliabile (revisione 2, Fase 5): una riga per
 * (numero, variante), messa «in coda» e affidata al job. Se per quella
 * variante un job è già in coda o in generazione non se ne accoda un
 * secondo (lavoro pesante: due click non devono raddoppiarlo), a meno che
 * sia fermo da più del timeout del job (worker caduto a metà): allora si
 * riparte.
 */
final class MagazineExportRequester
{
    public static function request(Issue $issue, MagazineExportVariant $variant, ?int $userId): MagazineExport
    {
        $export = MagazineExport::firstOrNew(['issue_id' => $issue->id, 'variant' => $variant]);

        if ($export->exists && $export->status->isRunning() && ! self::isStale($export)) {
            return $export;
        }

        $export->fill([
            'status' => MagazineExportStatus::Queued,
            'error' => null,
            'requested_by' => $userId,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        GenerateMagazinePdf::dispatch($export->id);

        return $export;
    }

    public static function isStale(MagazineExport $export): bool
    {
        $limit = (int) config('timone.magazine_export.timeout', 1800) + 300;

        return $export->updated_at !== null && $export->updated_at->lt(now()->subSeconds($limit));
    }
}
