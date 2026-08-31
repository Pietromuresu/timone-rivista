<?php

namespace App\Observers;

use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Models\Issue;
use App\Models\Page;
use App\Support\PageRoleAssigner;

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
        if ($issue->total_pages < 1) {
            return;
        }

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

        Page::insert($pages);

        // Fase 3.1: la prima/ultima pagina appena create diventano
        // Copertina1/Copertina4 (§4.3 dello spec) — Copertina2/Copertina3/
        // Battente restano da assegnare a mano, nessuna posizione fissa le
        // identifica in modo affidabile per ogni rivista.
        PageRoleAssigner::syncCoverRoles($issue);
    }
}
