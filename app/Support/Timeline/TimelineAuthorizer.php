<?php

namespace App\Support\Timeline;

use App\Enums\ContentType;
use App\Models\Issue;
use App\Models\Page;
use App\Models\User;
use App\Support\Permissions\TimoneAbilities;
use Illuminate\Support\Facades\DB;

/**
 * Chi può annullare o ripetere un passo (2026-10-28): le stesse regole
 * delle azioni (TimoneAbilities), applicate alle righe che il passo tocca,
 * lato server. La redazione sempre; il Commerciale solo se il passo
 * riguarda soltanto pubblicità e, per le pagine, solo la posizione di
 * pagine senza articoli (niente stati, blocchi, gruppi).
 */
final class TimelineAuthorizer
{
    /** @param  list<array>  $changes */
    public static function allows(User $user, Issue $issue, array $changes): bool
    {
        $abilities = TimoneAbilities::for($user, $issue);

        if ($abilities->editsTimone()) {
            return true;
        }

        if (! $abilities->handlesAds()) {
            return false;
        }

        $contentIds = [];
        $pageIds = [];
        $spacePageIds = self::pagesWithSpaceChanges($changes);

        foreach ($changes as $change) {
            $row = ($change['a'] ?? []) + ($change['b'] ?? []);

            match ($change['t']) {
                'contents' => $contentIds[] = $change['id'],
                'articles' => $contentIds[] = -1, // un articolo: mai
                'advertisements', 'content_files' => $contentIds[] = (int) ($row['content_id'] ?? DB::table($change['t'])->where('id', $change['id'])->value('content_id')),
                'page_content' => $contentIds[] = (int) ($row['content_id'] ?? DB::table('page_content')->where('id', $change['id'])->value('content_id')),
                'pages' => $pageIds[] = $change['id'],
                'page_groups' => $contentIds[] = -1, // gruppi: solo redazione
                default => null,
            };

            // Lo stato è ammesso solo dove nello stesso passo cambia lo
            // spazio della pagina: è il cambio automatico che ne è seguito
            // (PageStatusAutomation, 2026-10-30), non uno manuale.
            $allowed = ['position', 'id', 'created_at', 'updated_at', 'content_type'];

            if ($change['t'] === 'pages' && isset($spacePageIds[(int) $change['id']])) {
                $allowed[] = 'status';
            }

            if ($change['t'] === 'pages' && array_diff(array_keys($row), $allowed) !== []) {
                return false; // stato, blocco, gruppo, copertina: solo redazione
            }
        }

        if (in_array(-1, $contentIds, true)) {
            return false;
        }

        $types = DB::table('contents')->whereIn('id', array_unique($contentIds))->pluck('type')->unique();

        if ($types->contains(fn ($type) => $type !== ContentType::Pubblicita->value)) {
            return false;
        }

        return $pageIds === [] || $abilities->canMovePages(Page::with('contents')->whereIn('id', $pageIds)->get());
    }

    /**
     * Pagine su cui il passo cambia righe di page_content (assegnazioni o
     * spazio). Le righe aggiornate portano solo i campi cambiati: la
     * pagina si cerca nel database.
     *
     * @param  list<array>  $changes
     * @return array<int, true>
     */
    private static function pagesWithSpaceChanges(array $changes): array
    {
        $pages = [];

        foreach ($changes as $change) {
            if ($change['t'] !== 'page_content') {
                continue;
            }

            $pageId = $change['a']['page_id'] ?? $change['b']['page_id'] ?? DB::table('page_content')->where('id', $change['id'])->value('page_id');

            if ($pageId !== null) {
                $pages[(int) $pageId] = true;
            }
        }

        return $pages;
    }
}
