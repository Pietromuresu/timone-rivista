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

            if ($change['t'] === 'pages' && array_diff(array_keys($row), ['position', 'id', 'created_at', 'updated_at', 'content_type']) !== []) {
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
}
