<?php

namespace App\Support;

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Page;
use Illuminate\Support\Collection;

/**
 * Filtri, ordinamento ed etichette del pannello contenuti del timone
 * (2026-10-28, «contenuti sempre raggiungibili»). Puro, su collezioni già
 * caricate da Grid::render(): la scelta di QUALI contenuti (scheda Da
 * assegnare / Tutti / Segnaposto) resta a Grid, che conosce le query.
 */
final class ContentPanelFilter
{
    public const TABS = ['unassigned', 'all', 'placeholders'];

    public const SORTS = ['recent', 'alpha'];

    /** @param  Collection<int, Content>  $contents */
    public static function apply(Collection $contents, string $type, string $search, string $sort): Collection
    {
        $type = ContentType::tryFrom($type);
        $search = trim($search);

        $filtered = $contents
            ->when($type !== null, fn (Collection $c) => $c->filter(fn (Content $content) => $content->type === $type))
            ->when($search !== '', fn (Collection $c) => $c->filter(fn (Content $content) => mb_stripos($content->title, $search) !== false || mb_stripos($content->displayLabel(), $search) !== false));

        return ($sort === 'alpha'
            ? $filtered->sortBy(fn (Content $content) => mb_strtolower($content->displayLabel()))
            : $filtered->sortByDesc(fn (Content $content) => [$content->created_at?->getTimestamp() ?? 0, $content->id])
        )->values();
    }

    /**
     * Dove sta un contenuto già assegnato: «p. 12–14», «I di copertina»,
     * «II di copertina, p. 3». Richiede `pages` caricate. Null se non è in pagina.
     */
    public static function placementLabel(Content $content): ?string
    {
        if (! $content->relationLoaded('pages') || $content->pages->isEmpty()) {
            return null;
        }

        $covers = $content->pages->filter(fn (Page $page) => $page->isCover())->map(fn (Page $page) => $page->cover_slot->label());
        $internal = $content->pages->reject(fn (Page $page) => $page->isCover())->pluck('position')->all();

        return $covers->when($internal !== [], fn (Collection $c) => $c->push('p. '.PageSelectionActions::ranges($internal)))->implode(', ');
    }
}
