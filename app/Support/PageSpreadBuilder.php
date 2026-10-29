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

    /**
     * Lato di una pagina interna nella rivista aperta (griglia a doppia
     * pagina, 2026-10-29): pari a sinistra, dispari a destra — la 1 è
     * sempre a destra. Dalla posizione, mai salvato: dopo uno spostamento
     * le pagine che scorrono cambiano lato da sole.
     */
    public static function side(int $position): string
    {
        return $position % 2 === 0 ? 'left' : 'right';
    }

    /**
     * Aperture della griglia a doppia pagina (2026-10-29): coppie
     * [sinistra, destra] ricavate dalla POSIZIONE di ogni pagina (coppia n =
     * pagine 2n e 2n+1), null = slot vuoto. Le copertine stanno nella fascia
     * in alto (scelta dell'utente), quindi la 1 ha sempre lo slot sinistro
     * vuoto (lì sarebbe la II) e un'ultima pagina pari ha vuoto il destro
     * (la III). A differenza di slots(), che conta le pagine nell'ordine,
     * qui conta la posizione: una posizione mancante resta uno slot vuoto
     * invece di far slittare di lato tutte le pagine successive.
     *
     * @template T of object
     *
     * @param  Collection<int, T>  $pages  oggetti con `position` (pagine interne)
     * @return list<array{0: T|null, 1: T|null}>
     */
    public static function layout(Collection $pages): array
    {
        $pairs = [];

        foreach ($pages as $page) {
            $position = (int) $page->position;
            $pairs[intdiv($position, 2)][self::side($position) === 'left' ? 0 : 1] = $page;
        }

        ksort($pairs);

        return array_values(array_map(fn (array $pair) => [$pair[0] ?? null, $pair[1] ?? null], $pairs));
    }
}
