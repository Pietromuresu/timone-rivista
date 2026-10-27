<?php

namespace App\Support;

/**
 * Ordine dei contenuti dentro una pagina (Fase 7b, 2026-10-27). Pura:
 * riceve gli id nell'ordine attuale e restituisce il nuovo ordine dopo uno
 * spostamento di un posto (prima/dopo). Ai bordi non cambia nulla.
 * L'ordine decide la disposizione (PageLayoutPlanner): tra le colonne
 * verticali da sinistra a destra, tra le righe dall'alto in basso.
 */
final class PageContentOrder
{
    /**
     * @param  list<int>  $ids  ordine attuale
     * @return list<int>|null nuovo ordine, o null se lo spostamento non è possibile
     */
    public static function move(array $ids, int $contentId, int $delta): ?array
    {
        $index = array_search($contentId, $ids, true);

        if ($index === false || ! in_array($delta, [-1, 1], true)) {
            return null;
        }

        $target = $index + $delta;

        if ($target < 0 || $target >= count($ids)) {
            return null;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        return array_values($ids);
    }
}
