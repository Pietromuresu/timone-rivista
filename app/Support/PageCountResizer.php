<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PageCountResizer
{
    /**
     * Anteprima dell'impatto di un cambio di `total_pages`, calcolata sulle
     * pagine già caricate da Grid::render() (con `contents`/`files` già
     * eager-loaded) — nessuna query propria, stesso pattern "puro" di
     * AdLoadCalculator/PageSpreadBuilder/PageReorderer. Le pagine rimosse
     * sono sempre quelle in coda (posizione > $newTotal): la riduzione non
     * permette di scegliere quale pagina eliminare, solo se procedere o no.
     *
     * @param  Collection<int, \App\Models\Page>  $pages
     * @return array{
     *     type: 'noop'|'increase'|'decrease',
     *     delta: int,
     *     removedCount?: int,
     *     affectedPages?: list<array{position: int, contentCount: int, hasFiles: bool}>,
     *     lockedCount?: int,
     * }
     */
    public static function impact(Collection $pages, int $currentTotal, int $newTotal): array
    {
        if ($newTotal === $currentTotal) {
            return ['type' => 'noop', 'delta' => 0];
        }

        if ($newTotal > $currentTotal) {
            return ['type' => 'increase', 'delta' => $newTotal - $currentTotal];
        }

        $removedPages = $pages->filter(fn ($page) => $page->position > $newTotal);

        // $pages arriva da Grid::render(), dove la relazione "files" è
        // già limitata all'ultimo caricamento per pagina (vedi query in
        // Grid::render()): sufficiente per sapere SE una pagina ha
        // almeno un file (booleano), non per contarli con precisione —
        // per questo qui si segnala solo la presenza, non un conteggio.
        $affectedPages = $removedPages
            ->map(fn ($page) => [
                'position' => $page->position,
                'contentCount' => $page->contents->count(),
                'hasFiles' => $page->files->isNotEmpty(),
            ])
            ->filter(fn (array $info) => $info['contentCount'] > 0 || $info['hasFiles'])
            ->values()
            ->all();

        return [
            'type' => 'decrease',
            'delta' => $currentTotal - $newTotal,
            'removedCount' => $removedPages->count(),
            'affectedPages' => $affectedPages,
            'lockedCount' => $removedPages->filter(fn ($page) => $page->isLocked())->count(),
        ];
    }
}
