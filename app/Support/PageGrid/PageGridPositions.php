<?php

namespace App\Support\PageGrid;

use App\Enums\ContentOrientation;
use App\Models\Content;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ponte tra la griglia 6×6 pura (PageGridPlanner) e il database
 * (revisione 2, Fase 2.3, 2026-10-27): legge i rettangoli dalle colonne
 * page_content.grid_x/y/w/h e scrive quelli decisi dal planner. Unico
 * punto che scrive quelle colonne: assegnazione (ContentPageAttacher),
 * cambio di spazio sulla pagina, frecce, rotazione, cambio di formato e
 * conversione dei dati vecchi passano tutti da qui.
 *
 * Statico come ContentPageAttacher/ContentPlacer: un solo modo di farlo,
 * nessuna alternativa da iniettare (CLAUDE.md, regola 3).
 */
final class PageGridPositions
{
    /**
     * Elementi per il planner dai contenuti di una pagina (con pivot).
     *
     * @param  Collection<int, Content>  $contents
     * @return list<array{id: int, twelfths: int, orientation: ContentOrientation, rect: GridRect|null}>
     */
    public static function items(Collection $contents): array
    {
        return $contents->map(fn (Content $content) => [
            'id' => $content->id,
            'twelfths' => (int) $content->pivot->occupied_twelfths,
            'orientation' => self::orientationOf($content),
            'rect' => self::storedRect($content->pivot),
        ])->values()->all();
    }

    public static function orientationOf(Content $content): ContentOrientation
    {
        return $content->advertisement?->format?->orientation() ?? ContentOrientation::Horizontal;
    }

    public static function storedRect(object $pivot): ?GridRect
    {
        foreach (['grid_x', 'grid_y', 'grid_w', 'grid_h'] as $column) {
            if ($pivot->{$column} === null) {
                return null;
            }
        }

        return new GridRect((int) $pivot->grid_x, (int) $pivot->grid_y, (int) $pivot->grid_w, (int) $pivot->grid_h);
    }

    /** Disposizione risolta della pagina (dai contenuti già caricati se ci sono). */
    public static function resolve(Page $page): array
    {
        return PageGridPlanner::resolve(self::items($page->relationLoaded('contents') ? $page->contents : $page->contents()->with('advertisement')->get()));
    }

    /**
     * Scrive i rettangoli decisi dal planner (solo le righe che cambiano):
     * i contenuti appena assegnati o cambiati di misura ricevono il loro
     * posto; quelli a L o oltre capienza restano senza rettangolo (si
     * ricalcolano a ogni disegno).
     */
    public static function persist(Page $page): void
    {
        $contents = $page->contents()->with('advertisement')->get();
        $layout = PageGridPlanner::resolve(self::items($contents));

        DB::transaction(function () use ($page, $contents, $layout) {
            foreach ($contents as $content) {
                $block = $layout['blocks'][$content->id] ?? null;
                $rect = $block !== null && $block['kind'] === 'rect' ? $block['rect'] : null;
                $stored = self::storedRect($content->pivot);

                if (($rect === null && $stored === null) || ($rect !== null && $stored !== null && $rect->equals($stored))) {
                    continue;
                }

                self::write($page, $content->id, $rect);
            }
        });
    }

    /**
     * Freccia: sposta (o scambia) e salva. null = mossa non possibile.
     *
     * @return array<int, GridRect>|null i rettangoli cambiati
     */
    public static function move(Page $page, int $contentId, string $direction): ?array
    {
        // Prima si fissano tutti i rettangoli calcolati: dopo la mossa un
        // contenuto senza posizione salvata non deve "saltare" altrove.
        self::persist($page);
        $changes = PageGridPlanner::moveTarget(PageGridPlanner::rectsOf(self::resolve($page->unsetRelation('contents'))), $contentId, $direction);

        if ($changes === null) {
            return null;
        }

        self::writeAll($page, $changes);

        return $changes;
    }

    /** Rotazione: salva il nuovo rettangolo. null = non ruotabile o senza posto. */
    public static function rotate(Page $page, int $contentId): ?GridRect
    {
        self::persist($page);
        $rect = PageGridPlanner::rotateTarget(PageGridPlanner::rectsOf(self::resolve($page->unsetRelation('contents'))), $contentId);

        if ($rect !== null) {
            self::writeAll($page, [$contentId => $rect]);
        }

        return $rect;
    }

    /**
     * Dopo un cambio di formato (modale di modifica): se la forma salvata
     * non corrisponde più all'orientamento (es. mezza orizzontale diventata
     * verticale) la si ruota nello stesso angolo; persist() la sposta se lì
     * non ci sta.
     */
    public static function reshape(Page $page, Content $content): void
    {
        $pivot = $page->contents()->where('content_id', $content->id)->first()?->pivot;
        $stored = $pivot !== null ? self::storedRect($pivot) : null;

        if ($stored !== null && $stored->w !== $stored->h) {
            $vertical = self::orientationOf($content->fresh(['advertisement'])) === ContentOrientation::Vertical;

            if ($vertical !== ($stored->h > $stored->w)) {
                self::write($page, $content->id, $stored->transposed());
            }
        }

        self::persist($page);
    }

    /** @param  array<int, GridRect>  $rects */
    private static function writeAll(Page $page, array $rects): void
    {
        DB::transaction(function () use ($page, $rects) {
            foreach ($rects as $contentId => $rect) {
                self::write($page, $contentId, $rect);
            }
        });
    }

    private static function write(Page $page, int $contentId, ?GridRect $rect): void
    {
        DB::table('page_content')
            ->where('page_id', $page->id)
            ->where('content_id', $contentId)
            ->update([
                'grid_x' => $rect?->x,
                'grid_y' => $rect?->y,
                'grid_w' => $rect?->w,
                'grid_h' => $rect?->h,
            ]);
    }
}
