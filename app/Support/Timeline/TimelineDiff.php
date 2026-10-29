<?php

namespace App\Support\Timeline;

/**
 * Differenza fra due fotografie del timone (annulla/ripeti, 2026-10-28):
 * solo le righe e i campi cambiati. Formato di un cambiamento:
 * ['t' => tabella, 'id' => id, 'b' => prima, 'a' => dopo], dove per un
 * aggiornamento `b` e `a` contengono solo i campi cambiati, per una riga
 * nuova `b` è null e `a` è la riga intera, per una riga tolta il contrario.
 *
 * Campi ignorati: i timestamp (cambiano a ogni scrittura senza dire nulla
 * sullo stato) e, nelle righe dei file, tutto ciò che il job delle
 * miniature aggiorna dopo l'azione (non deve sembrare un conflitto).
 */
final class TimelineDiff
{
    public const IGNORED = ['created_at', 'updated_at'];

    /** content_files: conta solo che la riga ci sia o no. */
    public const EXISTENCE_ONLY = ['content_files'];

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $before
     * @param  array<string, array<int, array<string, mixed>>>  $after
     * @return list<array{t: string, id: int, b: array<string, mixed>|null, a: array<string, mixed>|null}>
     */
    public static function between(array $before, array $after): array
    {
        $changes = [];

        foreach (TimelineSnapshot::TABLES as $table) {
            $old = $before[$table] ?? [];
            $new = $after[$table] ?? [];

            foreach ($old + $new as $id => $_) {
                $b = $old[$id] ?? null;
                $a = $new[$id] ?? null;

                if ($b === null || $a === null) {
                    $changes[] = ['t' => $table, 'id' => (int) $id, 'b' => $b, 'a' => $a];

                    continue;
                }

                if (in_array($table, self::EXISTENCE_ONLY, true)) {
                    continue;
                }

                $fields = array_keys(array_filter(
                    $a,
                    fn ($value, $field) => ! in_array($field, self::IGNORED, true) && self::normalize($value) !== self::normalize($b[$field] ?? null),
                    ARRAY_FILTER_USE_BOTH,
                ));

                if ($fields !== []) {
                    $changes[] = [
                        't' => $table,
                        'id' => (int) $id,
                        'b' => array_intersect_key($b, array_flip($fields)),
                        'a' => array_intersect_key($a, array_flip($fields)),
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Due passi consecutivi come uno solo (caricamento multiplo): per ogni
     * riga «prima» dal primo, «dopo» dal secondo; campi uniti. Una riga nata
     * e poi tolta sparisce.
     */
    public static function merge(array $first, array $second): array
    {
        $byKey = [];

        foreach ($first as $change) {
            $byKey[$change['t'].'#'.$change['id']] = $change;
        }

        foreach ($second as $change) {
            $key = $change['t'].'#'.$change['id'];
            $prev = $byKey[$key] ?? null;

            if ($prev === null) {
                $byKey[$key] = $change;

                continue;
            }

            $b = $prev['b'] === null ? null : ($change['b'] === null ? $prev['b'] : $prev['b'] + $change['b']);
            $a = $change['a'] === null ? null : ($prev['a'] === null ? $change['a'] : $change['a'] + $prev['a']);
            $byKey[$key] = ['t' => $change['t'], 'id' => $change['id'], 'b' => $b, 'a' => $a];

            if ($b === null && $a === null) {
                unset($byKey[$key]);
            }
        }

        return array_values($byKey);
    }

    /**
     * Solo i cambiamenti che riguardano i contenuti indicati: le loro righe
     * (contents, articles, advertisements, content_files, page_content), le
     * pagine su cui stanno prima o dopo e i gruppi di quelle pagine.
     *
     * Serve per le azioni registrate senza transazione (creazione/modifica
     * con file, TimelineActionService::run() con $scope): tra le due
     * fotografie un collega può scrivere sullo stesso numero, e la sua
     * modifica finirebbe nel MIO passo. Annullandolo la disfarei senza
     * conflitto (il valore attuale è proprio il «dopo» del passo). Così
     * Ctrl+Z annulla solo le mie azioni, mai quelle degli altri.
     *
     * @param  list<int>  $contentIds
     */
    public static function onlyContents(array $changes, array $contentIds, array $before, array $after): array
    {
        $contentIds = array_map('intval', $contentIds);
        $mine = fn (mixed $id) => $id !== null && in_array((int) $id, $contentIds, true);

        $pageIds = [];

        foreach ([$before, $after] as $snapshot) {
            foreach ($snapshot['page_content'] ?? [] as $row) {
                if ($mine($row['content_id'] ?? null)) {
                    $pageIds[(int) $row['page_id']] = true;
                }
            }
        }

        $groupIds = [];

        foreach ([$before, $after] as $snapshot) {
            foreach (array_keys($pageIds) as $pageId) {
                if (($groupId = $snapshot['pages'][$pageId]['page_group_id'] ?? null) !== null) {
                    $groupIds[(int) $groupId] = true;
                }
            }
        }

        return array_values(array_filter($changes, fn (array $change) => match ($change['t']) {
            'contents' => $mine($change['id']),
            'pages' => isset($pageIds[$change['id']]),
            'page_groups' => isset($groupIds[$change['id']]),
            default => $mine($change['a']['content_id'] ?? $change['b']['content_id']
                ?? $after[$change['t']][$change['id']]['content_id'] ?? $before[$change['t']][$change['id']]['content_id'] ?? null),
        }));
    }

    /** Confronto per valore: '1' e 1, '' e null, decimali come stringhe. */
    public static function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_float($value) ? rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') : (string) $value;
    }
}
