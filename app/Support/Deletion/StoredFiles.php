<?php

namespace App\Support\Deletion;

use App\Models\ContentFile;
use App\Models\MagazineExport;
use App\Models\PageFile;
use Illuminate\Support\Facades\Storage;

/**
 * File su disco legati a un insieme di numeri (content_files dei loro
 * contenuti, page_files delle loro pagine, comprese le miniature): quanti,
 * quanto pesano, e l'elenco [disco, percorso] da cancellare DOPO il commit
 * (App\Jobs\DeleteStoredFiles). Le righe spariscono con le cascate del
 * database, i file no: senza questo elenco resterebbero orfani su disco.
 */
final class StoredFiles
{
    /** @param  list<array{disk: string, path: string}>  $paths */
    private function __construct(
        public readonly int $count,
        public readonly int $bytes,
        public readonly array $paths,
    ) {}

    /** @param  list<int>  $issueIds */
    public static function forIssues(array $issueIds): self
    {
        if ($issueIds === []) {
            return new self(0, 0, []);
        }

        $contentFiles = ContentFile::query()
            ->whereHas('content', fn ($query) => $query->withTrashed()->whereIn('issue_id', $issueIds)) // anche i contenuti eliminati da poco (annulla/ripeti)
            ->get(['content_id', 'disk', 'path', 'thumbnail_path', 'large_thumbnail_path', 'size']);

        $pageFiles = PageFile::query()
            ->whereHas('page', fn ($query) => $query->whereIn('issue_id', $issueIds))
            ->get(['disk', 'path', 'thumbnail_path', 'size']);

        $all = $contentFiles->concat($pageFiles);
        $paths = [];

        foreach ($all as $file) {
            foreach (array_unique(array_filter([$file->path, $file->thumbnail_path, $file->large_thumbnail_path ?? null])) as $path) {
                $paths[] = ['disk' => $file->disk, 'path' => $path];
            }
        }

        // 2026-10-30: ritagli per l'export del timone (CroppedThumbnails), rigenerabili.
        foreach ($contentFiles->pluck('content_id')->unique() as $contentId) {
            foreach (Storage::disk('local')->files('contents/'.$contentId.'/crops') as $crop) {
                $paths[] = ['disk' => 'local', 'path' => $crop];
            }
        }

        // Revisione 2, Fase 5: anche i PDF sfogliabili generati (non contati
        // tra i «file caricati» del riepilogo, ma da togliere dal disco).
        foreach (MagazineExport::whereIn('issue_id', $issueIds)->whereNotNull('path')->get(['disk', 'path']) as $export) {
            $paths[] = ['disk' => $export->disk, 'path' => $export->path];
        }

        return new self($all->count(), (int) $all->sum('size'), $paths);
    }
}
