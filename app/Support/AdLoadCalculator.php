<?php

namespace App\Support;

use App\Enums\ContentType;
use Illuminate\Support\Collection;

class AdLoadCalculator
{
    /**
     * Cruscotto pubblicitario di un'issue: pagine pubblicitarie/editoriali
     * equivalenti e carico percentuale (§7 della specifica), calcolati a
     * partire dalle pagine già caricate da Grid::render() — nessuna query
     * propria, stesso pattern "puro/testabile" di PageSpreadBuilder e
     * PageReorderer. La formula usa le percentuali *effettivamente
     * occupate* in `page_content` (non il default del formato), perché
     * sono modificabili manualmente pagina per pagina.
     *
     * @param  Collection<int, \App\Models\Page>  $pages
     * @return array{
     *     totalPages: int,
     *     adEquivalentPages: float,
     *     editorialEquivalentPages: float,
     *     adLoadPercentage: float,
     *     assignedAdCount: int,
     *     formatBreakdown: array<string, int>,
     *     confirmationBreakdown: array<string, int>,
     * }
     */
    public static function summarize(Collection $pages): array
    {
        $totalPages = $pages->count();
        $adPercentageSum = 0.0;
        $editorialPercentageSum = 0.0;
        $assignedAdCount = 0;
        $formatBreakdown = [];
        $confirmationBreakdown = [];

        foreach ($pages as $page) {
            foreach ($page->contents as $content) {
                $occupied = (float) $content->pivot->occupied_percentage;

                if ($content->type !== ContentType::Pubblicita) {
                    $editorialPercentageSum += $occupied;

                    continue;
                }

                $adPercentageSum += $occupied;
                $assignedAdCount++;

                if ($content->advertisement !== null) {
                    $formatKey = $content->advertisement->format->value;
                    $formatBreakdown[$formatKey] = ($formatBreakdown[$formatKey] ?? 0) + 1;

                    $statusKey = $content->advertisement->confirmation_status->value;
                    $confirmationBreakdown[$statusKey] = ($confirmationBreakdown[$statusKey] ?? 0) + 1;
                }
            }
        }

        $adEquivalentPages = round($adPercentageSum / 100, 2);
        $editorialEquivalentPages = round($editorialPercentageSum / 100, 2);

        return [
            'totalPages' => $totalPages,
            'adEquivalentPages' => $adEquivalentPages,
            'editorialEquivalentPages' => $editorialEquivalentPages,
            'adLoadPercentage' => $totalPages > 0 ? round($adEquivalentPages / $totalPages * 100, 2) : 0.0,
            'assignedAdCount' => $assignedAdCount,
            'formatBreakdown' => $formatBreakdown,
            'confirmationBreakdown' => $confirmationBreakdown,
        ];
    }
}
