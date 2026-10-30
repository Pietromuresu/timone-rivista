<?php

namespace App\Support\AdPlacement;

use App\Enums\AdConfirmationStatus;
use App\Enums\ContentType;
use App\Enums\PlannedAdKind;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\Issue;
use App\Support\ActivityLogger;
use App\Support\ContentPageAttacher;
use App\Support\PageStatusAutomation;
use App\Support\Timeline\TimelineSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Crea le pubblicità segnaposto di un numero appena creato (Fase 5,
 * 2026-10-27) seguendo il piano di AdPlacementService: senza
 * inserzionista, senza PDF, titolo automatico («Pubblicità 1/2 — da
 * assegnare»), già posizionate nel timone (non tra i "contenuti da
 * assegnare"); quelle senza posto restano non posizionate.
 *
 * Chiamata SOLO da Issues\Create alla creazione del numero: nessun altro
 * punto la richiama, quindi il posizionamento non si ripete mai.
 */
final class PlaceholderAdCreator
{
    /** @param  array<string, int|null>  $counts  valore di PlannedAdKind => quante */
    public static function create(Issue $issue, array $counts): AdPlacementPlan
    {
        $plan = AdPlacementService::plan((int) $issue->total_pages, $counts);

        if ($plan->count() === 0) {
            return $plan;
        }

        DB::transaction(function () use ($issue, $plan) {
            // Non passa da TimelineActionService (il numero sta nascendo,
            // niente annulla): lo stato automatico si applica qui, così una
            // pubblicità prevista da pagina intera rende la pagina «Assegnata».
            $before = TimelineSnapshot::take($issue->id);
            $covers = $issue->covers()->get()->keyBy(fn ($cover) => $cover->cover_slot->value);
            $pages = $issue->pages()->get()->keyBy('position');

            foreach ($plan->placements as $placement) {
                $page = $placement['cover'] !== null ? $covers->get($placement['cover']->value) : $pages->get($placement['position']);
                $content = self::createPlaceholder($issue, $placement['kind']);

                // Pagina appena creata, vuota: ci sta sempre (al più una
                // pubblicità per pagina). Un errore qui sarebbe un bug.
                ContentPageAttacher::attach($content, $page, null, min(12, $placement['kind']->twelfths()));
            }

            foreach ($plan->unplaced as $kind) {
                self::createPlaceholder($issue, $kind);
            }

            PageStatusAutomation::apply($issue, $before, TimelineSnapshot::take($issue->id), 'pubblicità previste alla creazione del numero');
        });

        // Revisione 2, Fase 1, bug 2 (2026-10-27): la descrizione elencava
        // tutte le posizioni e con decine di pubblicità superava i 255
        // caratteri della colonna ("Text too long" alla creazione del
        // numero). Ora è una frase di lunghezza fissa; le posizioni restano
        // complete nei dati strutturati (new_values.placed).
        $coverCount = count(array_filter($plan->placements, fn ($placement) => $placement['cover'] !== null));
        $internalCount = count($plan->placements) - $coverCount;

        ActivityLogger::log(
            issue: $issue,
            entityType: 'Issue',
            entityId: $issue->id,
            action: 'issue.placeholder_ads_created',
            description: $plan->count().' pubblicità previste create come segnaposto: '
                .$coverCount.' sulle copertine, '.$internalCount.' nelle pagine interne'
                .($plan->unplaced !== [] ? ', '.count($plan->unplaced).' senza posto' : ''),
            new: ['counts' => array_filter($counts), 'placed' => $plan->labels(), 'unplaced' => count($plan->unplaced)],
        );

        return $plan;
    }

    private static function createPlaceholder(Issue $issue, PlannedAdKind $kind): Content
    {
        $content = Content::create([
            'issue_id' => $issue->id,
            'type' => ContentType::Pubblicita->value,
            'title' => $kind->placeholderTitle(),
            'occupancy_twelfths' => $kind->twelfths(),
            'is_placeholder' => true,
        ]);

        Advertisement::create([
            'content_id' => $content->id,
            'client' => null,
            'format' => $kind->format()->value,
            'confirmation_status' => AdConfirmationStatus::InTrattativa->value,
        ]);

        return $content;
    }
}
