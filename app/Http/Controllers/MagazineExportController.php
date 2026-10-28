<?php

namespace App\Http\Controllers;

use App\Enums\MagazineExportStatus;
use App\Enums\MagazineExportVariant;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\MagazineExport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download del PDF sfogliabile già generato (revisione 2, Fase 5). Il file
 * sta sul disco privato: si scarica solo da qui, con lo stesso permesso di
 * vedere il numero degli altri export.
 */
class MagazineExportController extends Controller
{
    public function download(Magazine $magazine, Issue $issue, string $variant): StreamedResponse
    {
        abort_unless($issue->magazine_id === $magazine->id, 404);
        Gate::authorize('view', $issue);

        $variant = MagazineExportVariant::tryFrom($variant) ?? abort(404);
        $export = MagazineExport::where('issue_id', $issue->id)->where('variant', $variant)->first();

        abort_unless(
            $export !== null && $export->status === MagazineExportStatus::Ready && $export->path !== null && Storage::disk($export->disk)->exists($export->path),
            404,
        );

        $filename = Str::slug($magazine->name.' '.$issue->title).$variant->suffix().'.pdf';

        return Storage::disk($export->disk)->download($export->path, $filename, ['Content-Type' => 'application/pdf']);
    }
}
