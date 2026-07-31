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
     * $reservedAdvertisements (Fase 3, §3): pubblicità "prenotate" — un
     * `Content` di tipo pubblicità non ancora assegnato a nessuna pagina,
     * quindi mai presente nel loop su `$pages` sopra. Il carico che
     * occuperebbero *una volta piazzate* (il default del loro formato, o
     * la percentuale manuale se impostata — `Advertisement::occupiedPercentage()`,
     * la stessa logica già usata da `Grid::assignContent()`) va comunque
     * incluso nel carico pubblicitario totale ("occupa comunque il carico
     * pubblicitario nel cruscotto percentuali", richiesta esplicita della
     * Fase 3) — ma tenuto separato in `placedAdEquivalentPages`/
     * `reservedAdEquivalentPages` per trasparenza: non è la stessa cosa di
     * spazio già fisicamente occupato su una pagina. Parametro opzionale
     * (default nessuna prenotazione) per restare compatibile con le
     * chiamate/i test esistenti da prima della Fase 3.
     *
     * @param  Collection<int, \App\Models\Page>  $pages
     * @param  Collection<int, \App\Models\Advertisement>|null  $reservedAdvertisements
     * @return array{
     *     totalPages: int,
     *     adEquivalentPages: float,
     *     placedAdEquivalentPages: float,
     *     reservedAdEquivalentPages: float,
     *     editorialEquivalentPages: float,
     *     adLoadPercentage: float,
     *     assignedAdCount: int,
     *     reservedAdCount: int,
     *     formatBreakdown: array<string, int>,
     *     confirmationBreakdown: array<string, int>,
     * }
     */
    public static function summarize(Collection $pages, ?Collection $reservedAdvertisements = null): array
    {
        $reservedAdvertisements ??= new Collection;

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
                    self::tally($formatBreakdown, $content->advertisement->format->value);
                    self::tally($confirmationBreakdown, $content->advertisement->confirmation_status->value);
                }
            }
        }

        $placedAdEquivalentPages = round($adPercentageSum / 100, 2);

        $reservedPercentageSum = 0.0;

        foreach ($reservedAdvertisements as $advertisement) {
            $reservedPercentageSum += $advertisement->occupiedPercentage();
            self::tally($formatBreakdown, $advertisement->format->value);
            self::tally($confirmationBreakdown, $advertisement->confirmation_status->value);
        }

        $reservedAdEquivalentPages = round($reservedPercentageSum / 100, 2);
        $adEquivalentPages = round(($adPercentageSum + $reservedPercentageSum) / 100, 2);
        $editorialEquivalentPages = round($editorialPercentageSum / 100, 2);

        return [
            'totalPages' => $totalPages,
            'adEquivalentPages' => $adEquivalentPages,
            'placedAdEquivalentPages' => $placedAdEquivalentPages,
            'reservedAdEquivalentPages' => $reservedAdEquivalentPages,
            'editorialEquivalentPages' => $editorialEquivalentPages,
            'adLoadPercentage' => $totalPages > 0 ? round($adEquivalentPages / $totalPages * 100, 2) : 0.0,
            'assignedAdCount' => $assignedAdCount,
            'reservedAdCount' => $reservedAdvertisements->count(),
            'formatBreakdown' => $formatBreakdown,
            'confirmationBreakdown' => $confirmationBreakdown,
        ];
    }

    private static function tally(array &$breakdown, string $key): void
    {
        $breakdown[$key] = ($breakdown[$key] ?? 0) + 1;
    }
}
