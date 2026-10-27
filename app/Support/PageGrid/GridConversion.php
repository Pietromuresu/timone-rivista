<?php

namespace App\Support\PageGrid;

use App\Models\Content;
use App\Models\Page;
use App\Support\PageLayoutPlanner;
use App\Support\Twelfths;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Conversione dei dati esistenti alla griglia 6×6 (revisione 2, Fase 2.3,
 * 2026-10-27), usata dalla migration
 * 2026_10_27_600000_add_grid_cells_to_page_content_table e dal dry-run
 * `php artisan timone:grid-dry-run` (stesso calcolo, nessuna scrittura).
 *
 * Regola: ogni contenuto riceve il rettangolo in cui la disposizione della
 * Fase 7a lo disegnava (App\Support\PageLayoutPlanner, stesso ordine
 * della pagina), convertito da percentuali a celle, quando è esatto
 * (celle intere, area giusta); altrimenti — o se si sovrappone — viene
 * messo nel primo posto libero (PageGridPlanner). Il risultato è quindi
 * IDENTICO a ciò che si vedeva prima per tutte le combinazioni comuni
 * (colonne verticali + righe, 1/4, 1/3, 1/2, 2/3, pagina intera).
 * 7/12, 3/4, 11/12 diventano forme a L, senza rettangolo salvato.
 */
final class GridConversion
{
    /**
     * @return list<array{page: Page, rows: list<array{content: Content, twelfths: int, before: array|null, after: GridRect|null, kind: string, how: string}>, overfull: bool}>
     */
    public static function planAll(?int $issueId = null): array
    {
        // Pivot letti col query builder, solo con le colonne che esistono
        // già PRIMA della migration (Page::contents() seleziona anche
        // grid_*): il dry-run deve funzionare sul server prima del migrate.
        $pivots = DB::table('page_content')
            ->join('pages', 'pages.id', '=', 'page_content.page_id')
            ->when($issueId !== null, fn ($query) => $query->where('pages.issue_id', $issueId))
            ->orderBy('pages.issue_id')
            ->orderByRaw('pages.position is null')
            ->orderBy('pages.position')
            ->orderBy('pages.id')
            ->orderByRaw('page_content.position is null')
            ->orderBy('page_content.position')
            ->orderBy('page_content.id')
            ->get(['page_content.page_id', 'page_content.content_id', 'page_content.occupied_twelfths']);

        $pages = Page::with('issue')->findMany($pivots->pluck('page_id')->unique())->keyBy('id');
        $contents = Content::with('advertisement')->findMany($pivots->pluck('content_id')->unique())->keyBy('id');

        return $pivots->groupBy('page_id')
            ->map(fn (Collection $rows, int $pageId) => self::planPage(
                $pages[$pageId],
                $rows->map(fn ($row) => ['content' => $contents[$row->content_id], 'twelfths' => (int) $row->occupied_twelfths])->values()->all(),
            ))
            ->values()
            ->all();
    }

    /** @param  list<array{content: Content, twelfths: int}>  $entries  nell'ordine della pagina */
    public static function planPage(Page $page, array $entries): array
    {
        $old = PageLayoutPlanner::plan(array_map(fn (array $entry) => [
            'id' => $entry['content']->id,
            'twelfths' => $entry['twelfths'],
            'orientation' => PageGridPositions::orientationOf($entry['content']),
        ], $entries));
        $oldById = collect($old['blocks'])->keyBy('id');

        $items = array_map(fn (array $entry) => [
            'id' => $entry['content']->id,
            'twelfths' => $entry['twelfths'],
            'orientation' => PageGridPositions::orientationOf($entry['content']),
            'rect' => self::toCells($oldById->get($entry['content']->id), $entry['twelfths']),
        ], $entries);

        $layout = PageGridPlanner::resolve($items);
        $rows = [];

        foreach ($items as $index => $item) {
            $content = $entries[$index]['content'];
            $block = $layout['blocks'][$item['id']] ?? null;
            $kind = $block['kind'] ?? 'overflow';
            $after = $kind === 'rect' ? $block['rect'] : null;

            $rows[] = [
                'content' => $content,
                'twelfths' => $item['twelfths'],
                'before' => $oldById->get($item['id']),
                'after' => $after,
                'pieces' => $block['pieces'] ?? [],
                'kind' => $kind,
                'how' => match (true) {
                    $kind === 'fluid' => 'a L (riempie lo spazio libero)',
                    $kind === 'overflow' => 'NON ENTRA (pagina oltre capienza)',
                    $item['rect'] !== null && $after->equals($item['rect']) => 'identica a prima',
                    default => 'riposizionata (primo posto libero)',
                },
            ];
        }

        return ['page' => $page, 'rows' => $rows, 'overfull' => $layout['overfull']];
    }

    /** Scrive i rettangoli di tutte le pagine (migration). */
    public static function applyAll(): void
    {
        foreach (self::planAll() as $plan) {
            DB::transaction(function () use ($plan) {
                foreach ($plan['rows'] as $row) {
                    DB::table('page_content')
                        ->where('page_id', $plan['page']->id)
                        ->where('content_id', $row['content']->id)
                        ->update([
                            'grid_x' => $row['after']?->x,
                            'grid_y' => $row['after']?->y,
                            'grid_w' => $row['after']?->w,
                            'grid_h' => $row['after']?->h,
                        ]);
                }
            });
        }
    }

    /** Rettangolo della Fase 7a (percentuali) → celle, solo se esatto. */
    private static function toCells(?array $percent, int $twelfths): ?GridRect
    {
        if ($percent === null || ! GridShapes::isRectangular($twelfths) || $twelfths > Twelfths::PER_PAGE) {
            return null;
        }

        $cells = [];

        foreach (['x', 'y', 'w', 'h'] as $key) {
            $value = $percent[$key] * GridRect::SIZE / 100;

            if (abs($value - round($value)) > 0.001) {
                return null;
            }

            $cells[$key] = (int) round($value);
        }

        $rect = new GridRect($cells['x'], $cells['y'], $cells['w'], $cells['h']);

        return $rect->insidePage() && $rect->area() === GridShapes::cells($twelfths) ? $rect : null;
    }
}
