<?php

namespace App\Jobs;

use App\Enums\MagazineExportStatus;
use App\Enums\MagazineExportVariant;
use App\Models\MagazineExport;
use App\Support\MagazineExport\Ghostscript;
use App\Support\MagazineExport\MagazinePdfComposer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Genera il PDF sfogliabile di un numero (revisione 2, Fase 5,
 * 2026-10-28). In coda perché i file sorgente possono pesare centinaia di
 * MB: la composizione (MagazinePdfComposer) avviene in una cartella di
 * lavoro temporanea e il risultato sostituisce il file precedente della
 * stessa variante solo a lavoro finito, quindi un errore non lascia un
 * PDF a metà al posto di quello buono.
 *
 * Memoria: TCPDF costruisce il PDF in RAM, quindi il limite si alza qui e
 * solo qui (config timone.magazine_export.memory_limit), non nel php.ini
 * del worker. Un solo tentativo: rifare lo stesso lavoro pesante dopo un
 * errore deterministico (file corrotto, memoria) non serve; si rigenera
 * dal timone.
 */
class GenerateMagazinePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $exportId)
    {
        $this->timeout = (int) config('timone.magazine_export.timeout', 1800);
    }

    public function handle(): void
    {
        $export = MagazineExport::with('issue.magazine')->find($this->exportId);

        if ($export === null || $export->issue === null) {
            return; // numero eliminato nel frattempo
        }

        ini_set('memory_limit', (string) config('timone.magazine_export.memory_limit', '1024M'));

        $export->update(['status' => MagazineExportStatus::Processing, 'started_at' => now(), 'error' => null]);

        $diskName = (string) config('timone.magazine_export.disk', 'local');
        $directory = trim((string) config('timone.magazine_export.directory', 'exports'), '/');
        $disk = Storage::disk($diskName);
        $workRelative = "{$directory}/tmp-{$export->id}-".Str::lower(Str::random(8));
        $disk->makeDirectory($workRelative);
        $workDir = $disk->path($workRelative);

        try {
            $composed = $workDir.'/rivista.pdf';
            $result = (new MagazinePdfComposer($workDir))->compose($export->issue, $composed);
            $final = $composed;

            if ($export->variant === MagazineExportVariant::Preview) {
                $final = $workDir.'/anteprima.pdf';

                if (! Ghostscript::compressForPreview($composed, $final, (string) config('timone.magazine_export.preview_settings', '/ebook'))) {
                    throw new RuntimeException('Ghostscript non è riuscito a creare la versione a bassa risoluzione.');
                }
            }

            $path = "{$directory}/issue-{$export->issue_id}/{$export->variant->value}.pdf";
            $stream = fopen($final, 'rb');
            $disk->put($path, $stream);
            is_resource($stream) && fclose($stream);

            $export->update([
                'status' => MagazineExportStatus::Ready,
                'disk' => $diskName,
                'path' => $path,
                'size' => $disk->size($path),
                'page_count' => $result->pageCount,
                'missing_files' => $result->missingFiles,
                'finished_at' => now(),
            ]);

            Log::info('magazine_export.ready', ['export_id' => $export->id, 'issue_id' => $export->issue_id, 'variant' => $export->variant->value, 'pages' => $result->pageCount, 'missing_files' => $result->missingFiles, 'bytes' => $export->size]);
        } catch (Throwable $e) {
            $this->markFailed($export, $e);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /** Errori non catturabili in handle() (timeout, memoria esaurita): il worker chiama questo. */
    public function failed(Throwable $e): void
    {
        if (($export = MagazineExport::find($this->exportId)) !== null) {
            $this->markFailed($export, $e);
        }
    }

    private function markFailed(MagazineExport $export, Throwable $e): void
    {
        report($e);

        $export->update([
            'status' => MagazineExportStatus::Failed,
            'error' => Str::limit($e->getMessage(), 500),
            'finished_at' => now(),
        ]);
    }
}
