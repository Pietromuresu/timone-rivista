<?php

namespace App\Support\AdPlacement;

use App\Enums\CoverSlot;
use App\Enums\PlannedAdKind;

/**
 * Regola di posizionamento delle pubblicità previste alla creazione di un
 * numero (Fase 5, 2026-10-27). Pura: riceve il numero di pagine interne e
 * quante pubblicità di ogni tipo, restituisce un AdPlacementPlan. Nessuna
 * query, nessuna scrittura (quelle le fa PlaceholderAdCreator).
 *
 * Regola (dalla specifica, parametri in config/timone.php `ad_placement`):
 * 1. Le prime pubblicità, di QUALSIASI formato (prima le pagine intere,
 *    poi 2/3, 1/2, 1/3, 1/4), riempiono tutte le copertine di fila,
 *    nell'ordine `cover_order` (default II, III, IV, I) — precisazione
 *    dell'utente alla consegna della Fase 5: le copertine sono tutte
 *    preassegnate alla pubblicità, non una sì e una no. La I è l'ultima
 *    e, se usata, il piano lo segnala (avviso confermato dall'utente).
 * 2. Le pagine intere rimaste si alternano nelle pagine interne una sì e
 *    una no, da `start_page` (2) con passo `step` (2): 2, 4, 6…
 * 3. Le frazionarie rimaste (2/3, 1/2, 1/3, 1/4, in quest'ordine)
 *    continuano la stessa sequenza, una per pagina: il resto della pagina
 *    resta libero per l'editoriale.
 * 4. Finite le posizioni alternate, si riempiono le pagine libere rimaste
 *    partendo dal fondo; se non c'è più posto la pubblicità resta da
 *    assegnare (nel piano: `unplaced`).
 *
 * Il posizionamento avviene SOLO alla creazione del numero: dopo, tutto è
 * modificabile a mano e nulla lo ricalcola.
 */
final class AdPlacementService
{
    /**
     * @param  array<string, int|null>  $counts  valore di PlannedAdKind => quante
     * @param  array{cover_order?: list<string>, start_page?: int, step?: int}|null  $config  default: config('timone.ad_placement')
     */
    public static function plan(int $totalPages, array $counts, ?array $config = null): AdPlacementPlan
    {
        $config ??= (array) config('timone.ad_placement', []);
        $coverOrder = array_map(fn (string $slot) => CoverSlot::from($slot), $config['cover_order'] ?? ['II', 'III', 'IV', 'I']);
        $start = max(1, (int) ($config['start_page'] ?? 2));
        $step = max(1, (int) ($config['step'] ?? 2));

        // In ordine di posizionamento: intere, poi frazioni dalla più grande.
        $queue = [];
        foreach (PlannedAdKind::cases() as $kind) {
            $queue = array_merge($queue, array_fill(0, max(0, (int) ($counts[$kind->value] ?? 0)), $kind));
        }

        $placements = [];
        $warnings = [];
        $usedPositions = [];
        $alternating = $totalPages >= $start ? range($start, $totalPages, $step) : [];

        foreach ($queue as $kind) {
            // 1. Copertine: le prime pubblicità, di qualsiasi formato.
            if ($coverOrder !== []) {
                $cover = array_shift($coverOrder);
                $placements[] = ['kind' => $kind, 'cover' => $cover, 'position' => null];

                if ($cover === CoverSlot::I) {
                    $warnings[] = 'Una pubblicità è stata messa sulla I di copertina (di solito non è pubblicità): controlla.';
                }

                continue;
            }

            // 2–3. Sequenza alternata.
            $position = array_shift($alternating);

            // 4. Pagine libere dal fondo.
            if ($position === null) {
                for ($candidate = $totalPages; $candidate >= 1; $candidate--) {
                    if (! isset($usedPositions[$candidate])) {
                        $position = $candidate;
                        break;
                    }
                }
            }

            if ($position === null) {
                $unplaced[] = $kind;

                continue;
            }

            $usedPositions[$position] = true;
            $placements[] = ['kind' => $kind, 'cover' => null, 'position' => $position];
        }

        $unplaced ??= [];

        if ($unplaced !== []) {
            $warnings[] = count($unplaced) === 1
                ? '1 pubblicità prevista non ha trovato posto nel timone: resta tra le pubblicità da assegnare.'
                : count($unplaced).' pubblicità previste non hanno trovato posto nel timone: restano tra le pubblicità da assegnare.';
        }

        return new AdPlacementPlan($totalPages, $placements, $unplaced, $warnings);
    }
}
