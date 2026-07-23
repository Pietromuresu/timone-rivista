<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PageSpreadBuilder
{
    /**
     * Raggruppa le pagine ordinate in "aperture" come si vedrebbero
     * fisicamente sfogliando la rivista stampata: la prima pagina da sola
     * (copertina), poi coppie affiancate fino all'ultima pagina.
     *
     * @param  Collection<int, \App\Models\Page>  $pages
     * @return list<list<\App\Models\Page>>
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
}
