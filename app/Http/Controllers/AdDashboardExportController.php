<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Issue;
use App\Models\Magazine;
use App\Support\AdLoadCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdDashboardExportController extends Controller
{
    public function csv(Magazine $magazine, Issue $issue): StreamedResponse
    {
        [$issue, $adLoad] = $this->loadReportData($magazine, $issue);

        $filename = $this->filename($magazine, $issue, 'csv');

        return response()->streamDownload(function () use ($issue, $adLoad) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Rivista', 'Numero', 'Soglia allarme %']);
            fputcsv($handle, [
                $issue->magazine->name,
                $issue->title,
                $issue->magazine->ad_threshold_percentage ?? '',
            ]);
            fputcsv($handle, []);

            fputcsv($handle, ['Metrica', 'Valore']);
            fputcsv($handle, ['Pagine totali', $adLoad['totalPages']]);
            fputcsv($handle, ['Pagine equivalenti pubblicità (assegnate + prenotate)', $adLoad['adEquivalentPages']]);
            fputcsv($handle, ['di cui pagine equivalenti già assegnate', $adLoad['placedAdEquivalentPages']]);
            fputcsv($handle, ['di cui pagine equivalenti prenotate', $adLoad['reservedAdEquivalentPages']]);
            fputcsv($handle, ['Pagine equivalenti editoriali', $adLoad['editorialEquivalentPages']]);
            fputcsv($handle, ['Carico pubblicitario %', $adLoad['adLoadPercentage']]);
            fputcsv($handle, ['Inserzioni assegnate', $adLoad['assignedAdCount']]);
            fputcsv($handle, ['Inserzioni prenotate (non ancora assegnate)', $adLoad['reservedAdCount']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Formato pubblicitario', 'Conteggio']);
            foreach ($adLoad['formatBreakdown'] as $format => $count) {
                fputcsv($handle, [\App\Enums\AdFormat::from($format)->label(), $count]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Stato commerciale', 'Conteggio']);
            foreach ($adLoad['confirmationBreakdown'] as $status => $count) {
                fputcsv($handle, [\App\Enums\AdConfirmationStatus::from($status)->label(), $count]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function pdf(Magazine $magazine, Issue $issue): Response
    {
        [$issue, $adLoad] = $this->loadReportData($magazine, $issue);

        $filename = $this->filename($magazine, $issue, 'pdf');

        return Pdf::loadView('exports.ad-dashboard', [
            'issue' => $issue,
            'adLoad' => $adLoad,
            'adThreshold' => $issue->magazine->ad_threshold_percentage,
        ])->download($filename);
    }

    /**
     * @return array{0: Issue, 1: array}
     */
    private function loadReportData(Magazine $magazine, Issue $issue): array
    {
        if ($issue->magazine_id !== $magazine->id) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('view', $issue);

        $issue->load('magazine');

        $pages = $issue->pages()
            ->orderBy('position')
            ->with(['contents.advertisement'])
            ->get();

        // Fase 3 (§3): le pubblicità prenotate (non ancora assegnate a una
        // pagina) vanno incluse anche nel report esportato, stessa logica
        // di Grid::render() — non condivisa in codice tra i due (il
        // controller non ha accesso al componente Livewire, la query è
        // breve e non vale la pena estrarla, stesso principio già seguito
        // per l'esportazione del timone completo).
        $reservedAdvertisements = Advertisement::whereHas(
            'content', fn ($query) => $query->where('issue_id', $issue->id)->whereDoesntHave('pages')
        )->get();

        return [$issue, AdLoadCalculator::summarize($pages, $reservedAdvertisements)];
    }

    private function filename(Magazine $magazine, Issue $issue, string $extension): string
    {
        return Str::slug("carico-pubblicitario-{$magazine->slug}-{$issue->title}").'.'.$extension;
    }
}
