<?php

namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Enums\PageStatus;
use App\Models\Issue;
use App\Models\Magazine;
use App\Support\AdLoadCalculator;
use App\Support\IssueSummary;
use App\Support\PageSpreadBuilder;
use App\Support\Weight\MagazineWeightCalculator;
use App\Support\Weight\WeightLimits;
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
            // Disposizione e miniature (App\Support\ExportPageLayout via
            // Page::layout(), una miniatura per contenuto): i file dei
            // contenuti, più il vecchio PageFile diretto come ripiego.
            'contents.files',
            'files' => fn ($query) => $query->latest()->limit(1),
        ];

        $pages = $issue->pages()->with($with)->get();

        // Fase 2 (2026-10-26): copertine in una query a sé (fuori da
        // $issue->pages()), stessi filtri delle interne qui sotto.
        $covers = $issue->covers()->with($with)->get()
            ->sortBy(fn ($cover) => $cover->cover_slot->order())
            ->values();

        // Riepilogo in testa al PDF (2026-10-27): gli stessi numeri della
        // barra del timone (IssueSummary), calcolati sul numero INTERO prima
        // dei filtri — i filtri riducono le pagine stampate, non il numero.
        $unassigned = $issue->unassignedContents()->with('advertisement')->get();
        $reservedAds = $unassigned->where('type', ContentType::Pubblicita)
            ->map(fn ($content) => $content->advertisement?->setRelation('content', $content))
            ->filter()->values();
        $summary = IssueSummary::summarize(
            $pages,
            $covers,
            $unassigned,
            AdLoadCalculator::summarize($pages, $reservedAds),
            MagazineWeightCalculator::calculate($issue->weightInput(), config('peso_rivista.scaglioni'), WeightLimits::fromConfig()),
        );

        $filtersApplied = $request->boolean('onlyAds') || $request->boolean('onlyUnapproved');

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

        // Restyling del 2026-10-27 (richiesta dell'utente: "il pdf esportato
        // è terribile, rendilo bello"): pagine tutte della stessa misura,
        // con le proporzioni del formato, affiancate in DOPPIE PAGINE come
        // si sfoglia la rivista (PageSpreadBuilder: la 1 da sola a destra,
        // poi 2-3, 4-5…). Con un filtro attivo le pagine non sono più
        // consecutive: niente aperture, una pagina per casella.
        $slots = $filtersApplied
            ? $pages->map(fn ($page) => [$page])->all()
            : PageSpreadBuilder::slots($pages);

        $pageWidthPt = 60;
        $ratio = ($issue->formato_base_mm && $issue->formato_altezza_mm) ? $issue->formato_altezza_mm / $issue->formato_base_mm : 4 / 3;

        $pdf = Pdf::loadView('exports.timone', [
            'issue' => $issue,
            'coverPages' => $covers,
            // 6 aperture (12 pagine) per riga riempiono un A4 orizzontale con
            // pagine larghe 60pt; con un filtro, 12 pagine singole.
            'spreadRows' => array_chunk($slots, $filtersApplied ? 12 : 6),
            'filtersApplied' => $filtersApplied,
            // Solo pagine interne: le copertine sono elencate a parte.
            'totalPages' => $pages->count(),
            'summary' => $summary,
            'withThumbnails' => $request->boolean('withThumbnails'),
            'layoutWidthPt' => $pageWidthPt,
            'layoutHeightPt' => round($pageWidthPt * $ratio, 1),
        ])->setPaper('a4', 'landscape');

        // "Foglio N di M" in basso a destra: il totale dei fogli si conosce
        // solo a impaginazione finita, quindi si scrive sul canvas di dompdf
        // dopo render() (counter(pages) in CSS non è supportato).
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 92,
            $canvas->get_height() - 19,
            'Foglio {PAGE_NUM} di {PAGE_COUNT}',
            $pdf->getDomPDF()->getFontMetrics()->getFont('Helvetica'),
            6,
            [0.61, 0.64, 0.69],
        );

        return $pdf->download($filename);
    }
}
