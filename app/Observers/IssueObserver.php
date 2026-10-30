<?php

namespace App\Observers;

use App\Enums\CoverFlaps;
use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Models\Issue;
use App\Models\Page;
use App\Support\IssueCovers;

class IssueObserver
{
    /**
     * Alla creazione di un'Issue si generano subito le pagine bianche
     * corrispondenti a total_pages, pronte da assegnare.
     *
     * La logica di ridimensionamento a numero già avviato (aggiunta in coda
     * o rimozione con conferma se ci sono contenuti assegnati) è gestita
     * dal componente dedicato alla modifica delle pagine totali, non qui.
     */
    public function created(Issue $issue): void
    {
        $now = now();
        $pages = [];

        for ($position = 1; $position <= $issue->total_pages; $position++) {
            $pages[] = [
                'issue_id' => $issue->id,
                'position' => $position,
                'content_type' => PageContentType::Bianca->value,
                'status' => PageStatus::DaAssegnare->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($pages !== []) {
            Page::insert($pages);
        }

        // Fase 2 (2026-10-26): total_pages sono le sole pagine INTERNE
        // (1…N); le copertine I–IV vengono create SEMPRE come slot separati
        // (prima nascevano solo I e IV, come ruolo della prima/ultima
        // pagina — bug). Battenti (2026-10-30): quelli scelti nel modulo
        // «Nuovo numero» (Issue::$initialCoverFlaps); senza scelta, dalle
        // facciate di copertina (6 = battente di I, come la vecchia bandella).
        $flaps = $issue->initialCoverFlaps ?? CoverFlaps::fromCoverPages($issue->pagine_copertina);
        IssueCovers::ensure($issue, $flaps->flaps());
    }
}
