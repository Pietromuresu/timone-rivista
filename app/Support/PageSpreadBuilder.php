<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Collection;

class PageSpreadBuilder
{
    /**
     * Raggruppa le pagine ordinate in "aperture" come si vedrebbero
     * fisicamente sfogliando la rivista stampata: la prima pagina da sola
     * (copertina), poi coppie affiancate fino all'ultima pagina.
     *
     * @param  Collection<int, Page>  $pages
     * @return list<list<Page>>
     */
    public static function build(Collection $pages): array
    {
        $remaining = $pages->values();

        if ($remaining->isEmpty()) {
            return [];
        }

        $spreads = [[$remaining->shift()]];

        while ($remaining->isNotEmpty()) {
            $pair = [$remaining->shift()];

            if ($remaining->isNotEmpty()) {
                $pair[] = $remaining->shift();
            }

            $spreads[] = $pair;
        }

        return $spreads;
    }

    /**
     * Aperture con il lato di ogni pagina (export PDF del timone,
     * 2026-10-27): [sinistra, destra], null dove non c'è pagina — la 1 sta
     * da sola a destra, l'ultima (se il totale è pari) da sola a sinistra,
     * come sfogliando la rivista.
     *
     * @return list<array{0: mixed, 1: mixed}>
     */
    public static function slots(Collection $pages): array
    {
        $spreads = self::build($pages);

        return array_map(
            fn (array $spread, int $index) => match (true) {
                $index === 0 => [null, $spread[0]],
                count($spread) === 1 => [$spread[0], null],
                default => [$spread[0], $spread[1]],
            },
            $spreads,
            array_keys($spreads),
        );
    }
}
