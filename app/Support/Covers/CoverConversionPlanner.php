<?php

namespace App\Support\Covers;

use App\Enums\CoverSlot;

/**
 * Calcola, per UN numero, cosa diventa cosa nel passaggio al modello
 * "copertine separate" (Fase 2, 2026-10-26). Puro: nessuna query, lavora
 * sulle righe già lette. Lo usano sia il dry-run
 * (`timone:convert-covers --dry-run`) sia la migration dei dati
 * (CoverConversion), così ciò che si vede nel dry-run è esattamente ciò che
 * verrà applicato.
 *
 * Regole:
 * - una pagina con ruolo copertina1/2/3/4 diventa lo slot I/II/III/IV, una
 *   con ruolo battente la bandella; esce dalla sequenza interna e da un
 *   eventuale gruppo collegato (le copertine non si spostano tra le pagine);
 * - se due pagine hanno lo stesso ruolo (non dovrebbe succedere), solo la
 *   prima per posizione diventa copertina, l'altra resta interna con un
 *   avviso nel dry-run;
 * - le pagine rimaste sono rinumerate 1…M nell'ordine attuale;
 * - gli slot I-IV mancanti vengono creati vuoti. Un numero senza alcun ruolo
 *   copertina (numeri creati prima dei ruoli, mai riaperti) tiene quindi
 *   TUTTE le sue pagine come interne, esattamente come le vedeva l'utente
 *   (senza ruoli erano numerate tutte), e riceve quattro copertine vuote;
 * - un gruppo collegato che resta con una sola pagina viene sciolto.
 */
final class CoverConversionPlanner
{
    /**
     * @param  list<array{id: int, position: int|null, role: string|null, page_group_id: int|null, contents_count?: int, cover_slot?: string|null}>  $pages
     * @return array{
     *     covers: array<string, array{page_id: int, old_position: int|null, old_role: string|null, old_page_group_id: int|null, contents_count: int}>,
     *     created_slots: list<string>,
     *     renumbered: array<int, array{old_position: int, new_position: int}>,
     *     ungrouped: array<int, int>,
     *     new_total_pages: int,
     *     new_pagine_copertina: int,
     *     warnings: list<string>,
     *     already_converted: bool,
     * }
     */
    public static function plan(array $pages): array
    {
        // Già convertito (es. migration rieseguita dopo un rollback parziale):
        // nessuna modifica.
        if (collect($pages)->contains(fn (array $p) => ($p['cover_slot'] ?? null) !== null)) {
            return self::empty($pages, alreadyConverted: true);
        }

        $ordered = collect($pages)->sortBy('position')->values();
        $covers = [];
        $warnings = [];

        foreach ($ordered as $page) {
            $slot = CoverSlot::fromLegacyRole($page['role'] ?? null);

            if ($slot === null) {
                continue;
            }

            if (isset($covers[$slot->value])) {
                $warnings[] = "Due pagine con ruolo «{$page['role']}»: la pagina in posizione {$page['position']} resta interna.";

                continue;
            }

            $covers[$slot->value] = [
                'page_id' => $page['id'],
                'old_position' => $page['position'],
                'old_role' => $page['role'],
                'old_page_group_id' => $page['page_group_id'],
                'contents_count' => $page['contents_count'] ?? 0,
            ];
        }

        $coverPageIds = array_column($covers, 'page_id');
        $internal = $ordered->reject(fn (array $p) => in_array($p['id'], $coverPageIds, true))->values();

        $renumbered = [];
        foreach ($internal as $index => $page) {
            $renumbered[$page['id']] = ['old_position' => $page['position'], 'new_position' => $index + 1];
        }

        // Gruppi che restano con una sola pagina interna dopo l'uscita delle copertine.
        $ungrouped = [];
        $groupSizes = $internal->whereNotNull('page_group_id')->countBy('page_group_id');
        foreach ($internal as $page) {
            if ($page['page_group_id'] !== null && $groupSizes[$page['page_group_id']] < 2) {
                $ungrouped[$page['id']] = $page['page_group_id'];
            }
        }

        $createdSlots = collect(CoverSlot::mandatory())
            ->map(fn (CoverSlot $slot) => $slot->value)
            ->reject(fn (string $slot) => isset($covers[$slot]))
            ->values()
            ->all();

        if ($createdSlots === array_map(fn (CoverSlot $s) => $s->value, CoverSlot::mandatory()) && $internal->isNotEmpty()) {
            $warnings[] = 'Nessuna pagina con ruolo copertina: tutte le pagine restano interne, vengono create quattro copertine vuote.';
        }

        $pagineCopertina = 4 + (isset($covers[CoverSlot::Bandella->value]) ? CoverSlot::Bandella->printedPages() : 0);

        return [
            'covers' => $covers,
            'created_slots' => $createdSlots,
            'renumbered' => $renumbered,
            'ungrouped' => $ungrouped,
            'new_total_pages' => $internal->count(),
            'new_pagine_copertina' => $pagineCopertina,
            'warnings' => $warnings,
            'already_converted' => false,
        ];
    }

    private static function empty(array $pages, bool $alreadyConverted): array
    {
        return [
            'covers' => [],
            'created_slots' => [],
            'renumbered' => [],
            'ungrouped' => [],
            'new_total_pages' => collect($pages)->whereNull('cover_slot')->count(),
            'new_pagine_copertina' => 0,
            'warnings' => [],
            'already_converted' => $alreadyConverted,
        ];
    }
}
