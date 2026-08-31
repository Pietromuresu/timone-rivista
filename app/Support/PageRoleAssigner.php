<?php

namespace App\Support;

use App\Enums\PageRole;
use App\Models\Issue;

/**
 * Tiene sincronizzati i ruoli Copertina1/Copertina4 con la prima/ultima
 * pagina fisica dell'issue (Fase 3.1) — vanno ricalcolati ogni volta che
 * total_pages cambia (IssueObserver alla creazione, Grid::resizePages() dopo
 * ogni resize), perché un aumento/riduzione può spostare quale pagina è
 * fisicamente la prima o l'ultima. Copertina2/Copertina3/Battente NON sono
 * toccati qui: sono assegnati manualmente (nessuna posizione fissa li
 * identifica in modo affidabile per ogni rivista) e restano sulla pagina a
 * cui sono stati assegnati finché non viene rimossa o riassegnata a mano.
 *
 * Non pura (scrive su database) — stesso trattamento di ThumbnailProgress
 * Estimator/ActivityLogger, non di PageReorderer/PageGroupResolver.
 */
class PageRoleAssigner
{
    public static function syncCoverRoles(Issue $issue): void
    {
        $pages = $issue->pages()->orderBy('position')->get(['id', 'position', 'role']);

        if ($pages->isEmpty()) {
            return;
        }

        $first = $pages->first();
        $last = $pages->last();

        // Libera Copertina1/Copertina4 da qualunque pagina che le aveva ma
        // non è più all'estremo (es. una nuova pagina inserita prima della
        // vecchia pagina 1): senza questo passo resterebbero "copertina"
        // due pagine contemporaneamente dopo un resize.
        $pages
            ->filter(fn ($page) => in_array($page->role, [PageRole::Copertina1, PageRole::Copertina4], true))
            ->filter(fn ($page) => $page->id !== $first->id && $page->id !== $last->id)
            ->each(fn ($page) => $page->update(['role' => PageRole::Interna->value]));

        if ($first->role !== PageRole::Copertina1) {
            $first->update(['role' => PageRole::Copertina1->value]);
        }

        // Con una sola pagina nell'issue, first === last: resta Copertina1,
        // non ha senso essere anche Copertina4 (nessuna quarta di copertina
        // distinta da mostrare).
        if ($pages->count() > 1 && $last->role !== PageRole::Copertina4) {
            $last->update(['role' => PageRole::Copertina4->value]);
        }
    }
}
