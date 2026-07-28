<?php

namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Enums\PageStatus;
use App\Models\Issue;
use App\Models\Magazine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Esportazione del timone completo in PDF (§11 dello spec originale) —
 * distinta da AdDashboardExportController, che esporta solo il cruscotto
 * pubblicitario: due controller separati perché sono due report diversi,
 * con dati e filtri propri, non varianti dello stesso export.
 */
class TimoneExportController extends Controller
{
    public function pdf(Request $request, Magazine $magazine, Issue $issue): Response
    {
        if ($issue->magazine_id !== $magazine->id) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('view', $issue);

        $issue->load('magazine');

        $pages = $issue->pages()
            ->orderBy('position')
            ->with([
                'contents.article',
                'contents.advertisement',
                'files' => fn ($query) => $query->latest()->limit(1),
            ])
            ->get();

        if ($request->boolean('onlyAds')) {
            $pages = $pages->filter(
                fn ($page) => $page->contents->contains(fn ($content) => $content->type === ContentType::Pubblicita)
            )->values();
        }

        if ($request->boolean('onlyUnapproved')) {
            $approved = [PageStatus::Revisionata, PageStatus::OkStampa];
            $pages = $pages->reject(fn ($page) => in_array($page->status, $approved, true))->values();
        }

        $filename = Str::slug("timone-{$magazine->slug}-{$issue->title}").'.pdf';

        // Griglia semplice a righe (8 pagine per riga, come la modalità
        // "griglia" più densa dell'interfaccia), non le "doppie pagine"
        // affiancate come in PageSpreadBuilder: per un foglio di
        // riferimento stampato conta vedere tante pagine possibili a
        // colpo d'occhio, non la disposizione esatta di stampa.
        return Pdf::loadView('exports.timone', [
            'issue' => $issue,
            'pageRows' => $pages->chunk(8),
            'totalPages' => $pages->count(),
            'withThumbnails' => $request->boolean('withThumbnails'),
            'filtersApplied' => $request->boolean('onlyAds') || $request->boolean('onlyUnapproved'),
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
