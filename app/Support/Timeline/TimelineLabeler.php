<?php

namespace App\Support\Timeline;

use App\Enums\CoverSlot;
use App\Enums\PageStatus;
use App\Support\PageSelectionActions;

/**
 * Etichetta leggibile di un passo (annulla/ripeti, 2026-10-28), ricavata
 * dal tipo e dalle righe cambiate: «Sposta pagine 12–14 → 20–22»,
 * «Assegna «Acme» (pagina 5)», «Crea 5 contenuti»… Usata nel tooltip di ↶ ↷,
 * nel toast e nel menu Cronologia. Pura: lavora sulle fotografie.
 */
final class TimelineLabeler
{
    /** @param  list<array>  $changes  @param  array  $before  @param  array  $after  fotografie */
    public static function label(string $type, array $changes, array $before, array $after): string
    {
        $titles = fn () => collect($changes)->filter(fn ($c) => $c['t'] === 'contents')->map(fn ($c) => $after['contents'][$c['id']]['title'] ?? $before['contents'][$c['id']]['title'] ?? null)
            ->merge(collect($changes)->filter(fn ($c) => $c['t'] === 'page_content')->map(fn ($c) => self::contentTitle($c, $before, $after)))
            ->filter()->unique()->values();
        $one = fn () => '«'.($titles()->first() ?? '?').'»'.($titles()->count() > 1 ? ' e altri '.($titles()->count() - 1) : '');
        $pages = fn () => self::pagesLabel(collect($changes)->filter(fn ($c) => in_array($c['t'], ['page_content', 'pages'], true))->flatMap(fn ($c) => self::pageIdsOf($c))->unique()->all(), $after + $before);

        return mb_substr(match ($type) {
            'page.move' => self::moveLabel($changes),
            'page.swap' => 'Scambia '.$pages(),
            'cover.swap' => 'Scambia copertine '.$pages(),
            'content.assign' => 'Assegna '.$one().' ('.$pages().')',
            'content.extend' => 'Estendi '.$one().' ('.$pages().')',
            'content.unassign' => 'Togli '.$one().' ('.$pages().')',
            'content.grid' => 'Sposta '.$one().' ('.$pages().')',
            'content.rotate' => 'Ruota '.$one().' ('.$pages().')',
            'content.occupancy' => 'Spazio di '.$one().' ('.$pages().')',
            'content.rename' => 'Rinomina '.$one(),
            'content.update' => 'Modifica '.$one(),
            'content.create' => $titles()->count() > 1 ? 'Crea '.$titles()->count().' contenuti' : 'Crea '.$one(),
            'content.archive' => 'Archivia '.$one(),
            'content.unarchive' => 'Ripristina '.$one(),
            'content.delete' => 'Elimina '.$one(),
            'group.link' => 'Collega '.$pages(),
            'group.unlink' => 'Scollega '.$pages(),
            'page.status' => 'Stato '.$pages().' → '.self::statusOf($changes),
            'page.lock' => (collect($changes)->contains(fn ($c) => ($c['a']['locked_at'] ?? null) !== null) ? 'Blocca ' : 'Sblocca ').$pages(),
            default => 'Modifica al timone',
        }, 0, 250);
    }

    private static function moveLabel(array $changes): string
    {
        $moves = collect($changes)->filter(fn ($c) => $c['t'] === 'pages' && isset($c['b']['position'], $c['a']['position']))
            ->map(fn ($c) => ['from' => (int) $c['b']['position'], 'to' => (int) $c['a']['position']]);

        if ($moves->isEmpty()) {
            return 'Sposta pagine';
        }

        // Le pagine spostate davvero sono quelle con lo scarto maggiore; le altre scorrono.
        $max = $moves->max(fn ($m) => abs($m['to'] - $m['from']));
        $moved = $moves->filter(fn ($m) => abs($m['to'] - $m['from']) === $max);

        // Pari merito in versi opposti (spostare una pagina di un posto, cioè
        // uno scambio): «Scambia pagina 2 e pagina 3», non «2–3 → 2–3».
        if ($moved->count() === 2 && $moved->sum(fn ($m) => $m['to'] - $m['from']) === 0) {
            [$first, $second] = $moved->sortBy('from')->values()->all();

            return "Scambia pagina {$first['from']} e pagina {$second['from']}";
        }
        $from = PageSelectionActions::ranges($moved->pluck('from')->all());
        $to = PageSelectionActions::ranges($moved->pluck('to')->all());

        return $moved->count() === 1 ? "Sposta pagina {$from} → {$to}" : "Sposta pagine {$from} → {$to}";
    }

    private static function contentTitle(array $change, array $before, array $after): ?string
    {
        $contentId = $change['a']['content_id'] ?? $change['b']['content_id'] ?? $after['page_content'][$change['id']]['content_id'] ?? $before['page_content'][$change['id']]['content_id'] ?? null;

        return $contentId === null ? null : ($after['contents'][$contentId]['title'] ?? $before['contents'][$contentId]['title'] ?? null);
    }

    /** @return list<int> */
    private static function pageIdsOf(array $change): array
    {
        if ($change['t'] === 'pages') {
            return [$change['id']];
        }

        return array_values(array_filter([(int) ($change['a']['page_id'] ?? 0), (int) ($change['b']['page_id'] ?? 0)]));
    }

    /** «pagina 5», «pagine 3–5», «II di copertina», «pagina 3 e II di copertina». */
    private static function pagesLabel(array $pageIds, array $snapshot): string
    {
        $pages = collect($pageIds)->map(fn ($id) => $snapshot['pages'][$id] ?? null)->filter();
        $covers = $pages->filter(fn ($p) => $p['cover_slot'] !== null)->map(fn ($p) => CoverSlot::from($p['cover_slot'])->label());
        $positions = $pages->filter(fn ($p) => $p['cover_slot'] === null)->pluck('position')->map(fn ($p) => (int) $p)->all();
        $parts = $covers->values()->all();

        if ($positions !== []) {
            array_unshift($parts, (count($positions) === 1 ? 'pagina ' : 'pagine ').PageSelectionActions::ranges($positions));
        }

        return $parts === [] ? 'pagine' : implode(' e ', $parts);
    }

    private static function statusOf(array $changes): string
    {
        $status = collect($changes)->first(fn ($c) => isset($c['a']['status']))['a']['status'] ?? null;

        return $status ? (PageStatus::tryFrom($status)?->label() ?? $status) : '?';
    }
}
