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

        $with = [
            'contents.article',
            'contents.advertisement',
            'files' => fn ($query) => $query->latest()->limit(1),
        ];

        $pages = $issue->pages()->with($with)->get();

        // Fase 2 (2026-10-26): copertine in una query a sé (fuori da
        // $issue->pages()), stessi filtri delle interne qui sotto.
        $covers = $issue->covers()->with($with)->get()
            ->sortBy(fn ($cover) => $cover->cover_slot->order())
            ->values();

        if ($request->boolean('onlyAds')) {
            $hasAd = fn ($page) => $page->contents->contains(fn ($content) => $content->type === ContentType::Pubblicita);
            $pages = $pages->filter($hasAd)->values();
            $covers = $covers->filter($hasAd)->values();
        }

        if ($request->boolean('onlyUnapproved')) {
            $approved = [PageStatus::Revisionata, PageStatus::OkStampa];
            $notApproved = fn ($page) => ! in_array($page->status, $approved, true);
            $pages = $pages->filter($notApproved)->values();
            $covers = $covers->filter($notApproved)->values();
        }

        $filename = Str::slug("timone-{$magazine->slug}-{$issue->title}").'.pdf';

        // Griglia semplice a righe (8 pagine per riga, come la modalità
        // "griglia" più densa dell'interfaccia), non le "doppie pagine"
        // affiancate come in PageSpreadBuilder: per un foglio di
        // riferimento stampato conta vedere tante pagine possibili a
        // colpo d'occhio, non la disposizione esatta di stampa.
        return Pdf::loadView('exports.timone', [
            'issue' => $issue,
            'coverPages' => $covers,
            'pageRows' => $pages->chunk(8),
            // Solo pagine interne: le copertine sono elencate a parte.
            'totalPages' => $pages->count(),
            'withThumbnails' => $request->boolean('withThumbnails'),
            'filtersApplied' => $request->boolean('onlyAds') || $request->boolean('onlyUnapproved'),
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
