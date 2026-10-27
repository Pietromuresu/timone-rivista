<?php

namespace App\Support;

use App\Enums\PageFillStatus;
use App\Models\Content;
use App\Models\Page;
use App\Support\Weight\MagazineWeightResult;
use Illuminate\Support\Collection;

/**
 * Riepilogo stile dashboard del timone (Fase 6, 2026-10-27): la barra
 * sempre visibile sotto l'header. Nessun calcolo nuovo: riusa le fonti
 * uniche già esistenti — PageCapacity (riempimento delle pagine),
 * AdLoadCalculator (percentuale pubblicitaria in dodicesimi), il calcolo
 * del peso (MagazineWeightCalculator, passato già calcolato) — e lavora
 * sulle collezioni già caricate da Grid::render(), senza query proprie.
 *
 * Sostituisce App\Support\AdPlacement\PlaceholderAdCounter (Fase 5), che
 * faceva una query a ogni render: i segnaposto si contano qui dalle
 * pagine, dalle copertine e dai contenuti non assegnati già in memoria.
 */
final class IssueSummary
{
    /**
     * @param  Collection<int, Page>  $pages  pagine interne con `contents`
     * @param  Collection<int, Page>  $covers  copertine con `contents`
     * @param  Collection<int, Content>  $unassigned  TUTTI i contenuti non assegnati (non filtrati dalla ricerca) con `advertisement`
     * @param  array{adLoadPercentage: float}  $adLoad  risultato di AdLoadCalculator::summarize()
     * @return array{
     *     internalPages: int, fullPages: int, partialPages: int, blankPages: int,
     *     freeTwelfths: int, freeLabel: string, adPercentage: float,
     *     placeholderCount: int, placeholderByFormat: array<string, int>,
     *     coversTotal: int, coversAssigned: int,
     *     weight: array{complete: bool, copyGrams: float|null, bracket: int|null, atRisk: bool, outOfBracket: bool},
     *     blankPageIds: list<int>, placeholderPageIds: list<int>,
     * }
     */
    public static function summarize(Collection $pages, Collection $covers, Collection $unassigned, array $adLoad, ?MagazineWeightResult $weight): array
    {
        $counts = [PageFillStatus::Empty->value => 0, PageFillStatus::Partial->value => 0, PageFillStatus::Full->value => 0, PageFillStatus::Overfull->value => 0];
        $freeTwelfths = 0;
        $blankPageIds = [];

        foreach ($pages as $page) {
            $used = $page->occupiedTwelfths();
            $status = PageCapacity::status($used);
            $counts[$status->value]++;
            $freeTwelfths += max(0, Twelfths::PER_PAGE - $used);

            if ($status === PageFillStatus::Empty) {
                $blankPageIds[] = $page->id;
            }
        }

        // Segnaposto: una volta per contenuto, ovunque sia (pagine,
        // copertine, non posizionato), con le pagine che li ospitano.
        $placeholderFormats = [];
        $placeholderPageIds = [];

        foreach ($pages->concat($covers) as $page) {
            foreach ($page->contents as $content) {
                if ($content->isPlaceholder() && $content->advertisement !== null) {
                    $placeholderFormats[$content->id] = $content->advertisement->format->value;
                    $placeholderPageIds[] = $page->id;
                }
            }
        }

        foreach ($unassigned as $content) {
            if ($content->isPlaceholder() && $content->advertisement !== null) {
                $placeholderFormats[$content->id] = $content->advertisement->format->value;
            }
        }

        return [
            'internalPages' => $pages->count(),
            // "Occupate" = piene; le oltre capienza (solo da dati convertiti) contano come piene.
            'fullPages' => $counts[PageFillStatus::Full->value] + $counts[PageFillStatus::Overfull->value],
            'partialPages' => $counts[PageFillStatus::Partial->value],
            'blankPages' => $counts[PageFillStatus::Empty->value],
            'freeTwelfths' => $freeTwelfths,
            'freeLabel' => Twelfths::pagesLabel($freeTwelfths),
            'adPercentage' => (float) $adLoad['adLoadPercentage'],
            'placeholderCount' => count($placeholderFormats),
            'placeholderByFormat' => array_count_values($placeholderFormats),
            'coversTotal' => $covers->count(),
            'coversAssigned' => $covers->filter(fn (Page $cover) => $cover->contents->isNotEmpty())->count(),
            'weight' => [
                'complete' => (bool) $weight?->complete,
                'copyGrams' => $weight?->pesoCopiaG,
                'bracket' => $weight?->scaglione,
                'atRisk' => (bool) $weight?->aRischio,
                'outOfBracket' => (bool) $weight?->fuoriScaglione,
            ],
            'blankPageIds' => $blankPageIds,
            'placeholderPageIds' => array_values(array_unique($placeholderPageIds)),
        ];
    }
}
