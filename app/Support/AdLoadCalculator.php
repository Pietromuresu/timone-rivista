<?php

namespace App\Support;

use App\Enums\ContentType;
use App\Models\Advertisement;
use App\Models\Page;
use Illuminate\Support\Collection;

class AdLoadCalculator
{
    /**
     * Cruscotto pubblicitario di un'issue: pagine pubblicitarie/editoriali
     * equivalenti e carico percentuale (§7 della specifica), calcolati a
     * partire dalle pagine già caricate da Grid::render() — nessuna query
     * propria, stesso pattern "puro/testabile" di PageSpreadBuilder e
     * PageReorderer. La formula usa lo spazio *effettivamente occupato*
     * in `page_content` (non il default del formato), perché è
     * modificabile manualmente pagina per pagina.
     *
     * Fase 3 (2026-10-27): tutto in dodicesimi interi (pivot
     * `occupied_twelfths`), la percentuale è calcolata una sola volta alla
     * fine sui dodicesimi totali delle pagine interne (pagine × 12): niente
     * più somme di decimali come 33.3 + 33.3 + 33.3. Le "pagine
     * equivalenti" restituite sono solo per la lettura (Twelfths::toPages()).
     *
     * $reservedAdvertisements (Fase 3, §3): pubblicità "prenotate" — un
     * `Content` di tipo pubblicità non ancora assegnato a nessuna pagina,
     * quindi mai presente nel loop su `$pages` sopra. Il carico che
     * occuperebbero *una volta piazzate* (dalla Fase 3 l'occupazione del
     * loro Content, `occupancy_twelfths`) va comunque
     * incluso nel carico pubblicitario totale ("occupa comunque il carico
     * pubblicitario nel cruscotto percentuali", richiesta esplicita della
     * Fase 3) — ma tenuto separato in `placedAdEquivalentPages`/
     * `reservedAdEquivalentPages` per trasparenza: non è la stessa cosa di
     * spazio già fisicamente occupato su una pagina. Parametro opzionale
     * (default nessuna prenotazione) per restare compatibile con le
     * chiamate/i test esistenti da prima della Fase 3.
     *
     * @param  Collection<int, Page>  $pages
     * @param  Collection<int, Advertisement>|null  $reservedAdvertisements
     * @return array{
     *     totalPages: int,
     *     adEquivalentPages: float,
     *     placedAdEquivalentPages: float,
     *     reservedAdEquivalentPages: float,
     *     editorialEquivalentPages: float,
     *     adLoadPercentage: float,
     *     adTwelfths: int,
     *     capacityTwelfths: int,
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
        $placedAdTwelfths = 0;
        $editorialTwelfths = 0;
        $assignedAdCount = 0;
        $formatBreakdown = [];
        $confirmationBreakdown = [];

        foreach ($pages as $page) {
            foreach ($page->contents as $content) {
                $occupied = (int) $content->pivot->occupied_twelfths;

                if ($content->type !== ContentType::Pubblicita) {
                    $editorialTwelfths += $occupied;

                    continue;
                }

                $placedAdTwelfths += $occupied;
                $assignedAdCount++;

                if ($content->advertisement !== null) {
                    self::tally($formatBreakdown, $content->advertisement->format->value);
                    self::tally($confirmationBreakdown, $content->advertisement->confirmation_status->value);
                }
            }
        }

        $reservedAdTwelfths = 0;

        foreach ($reservedAdvertisements as $advertisement) {
            $reservedAdTwelfths += (int) $advertisement->content?->occupancy_twelfths;
            self::tally($formatBreakdown, $advertisement->format->value);
            self::tally($confirmationBreakdown, $advertisement->confirmation_status->value);
        }

        $adTwelfths = $placedAdTwelfths + $reservedAdTwelfths;
        $capacityTwelfths = $totalPages * Twelfths::PER_PAGE;

        return [
            'totalPages' => $totalPages,
            'adEquivalentPages' => Twelfths::toPages($adTwelfths),
            'placedAdEquivalentPages' => Twelfths::toPages($placedAdTwelfths),
            'reservedAdEquivalentPages' => Twelfths::toPages($reservedAdTwelfths),
            'editorialEquivalentPages' => Twelfths::toPages($editorialTwelfths),
            'adLoadPercentage' => Twelfths::percentage($adTwelfths, $capacityTwelfths),
            'adTwelfths' => $adTwelfths,
            'capacityTwelfths' => $capacityTwelfths,
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
