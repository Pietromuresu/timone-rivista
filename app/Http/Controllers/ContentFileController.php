<?php

namespace App\Http\Controllers;

use App\Models\ContentFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentFileController extends Controller
{
    public function show(ContentFile $contentFile): StreamedResponse
    {
        Gate::authorize('view', $contentFile->content->issue);

        // inline esplicito (revisione 2, Fase 2.1): l'occhio 👁 sul contenuto
        // apre il file in una nuova scheda per guardarlo, non per scaricarlo.
        return Storage::disk($contentFile->disk)->response(
            $contentFile->path,
            $contentFile->original_name,
            ['Content-Type' => $contentFile->mime_type],
            'inline'
        );
    }

    public function thumbnail(ContentFile $contentFile): StreamedResponse|Response
    {
        Gate::authorize('view', $contentFile->content->issue);

        if (! $contentFile->thumbnail_path) {
            abort(404);
        }

        // Il tipo della miniatura è sempre un'immagine, ma non sempre PNG:
        // per un file immagine la miniatura è una copia del file originale
        // (vedi GenerateContentFileThumbnail), quindi ne eredita il mime
        // type reale invece di assumere sempre image/png come per i PDF.
        $thumbnailMime = $contentFile->isImage() ? $contentFile->mime_type : 'image/png';

        return Storage::disk($contentFile->disk)->response(
            $contentFile->thumbnail_path,
            null,
            ['Content-Type' => $thumbnailMime]
        );
    }
}
