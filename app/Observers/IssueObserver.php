<?php

namespace App\Observers;

use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Models\Issue;
use App\Models\Page;

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
    }
}
