<?php

namespace App\Http\Controllers;

use App\Models\PageFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PageFileController extends Controller
{
    public function show(PageFile $pageFile): StreamedResponse
    {
        Gate::authorize('view', $pageFile->page->issue);

        return Storage::disk($pageFile->disk)->response(
            $pageFile->path,
            $pageFile->original_name,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function thumbnail(PageFile $pageFile): StreamedResponse|Response
    {
        Gate::authorize('view', $pageFile->page->issue);

        if (! $pageFile->thumbnail_path) {
            abort(404);
        }

        return Storage::disk($pageFile->disk)->response(
            $pageFile->thumbnail_path,
            null,
            ['Content-Type' => 'image/png']
        );
    }
}
