<?php

namespace App\Support;

use App\Enums\PageStatus;
use App\Events\PageStatusUpdated;
use App\Models\Issue;
use App\Models\Page;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;

/**
 * Stato automatico della pagina (2026-10-30): quando un'operazione rende
 * una pagina piena (12/12, o oltre capienza) da «Da assegnare» passa ad
 * «Assegnata»; quando smette di essere piena torna «Da assegnare»,
 * qualunque fosse lo stato. Se resta non piena (o resta piena) lo stato
 * non cambia: chi ha messo a mano «In bozza» su una mezza pagina non se lo
 * vede cambiare.
 *
 * Si decide confrontando due fotografie del timone (TimelineSnapshot)
 * prese prima e dopo l'operazione. Il punto unico da cui passa è
 * TimelineActionService::run(): così il cambio di stato finisce nello
 * stesso passo di annulla/ripeti. I due percorsi che non passano da lì
 * (pubblicità previste alla creazione del numero, «Sposta le pagine
 * esistenti dopo») la chiamano direttamente. Niente observer Eloquent:
 * attach/detach, gli update di massa e l'applicatore di annulla/ripeti
 * non li scatenano.
 *
 * L'utente non serve: il cambio è conseguenza dell'operazione, quindi
 * vale anche per chi non può cambiare lo stato a mano (Commerciale).
 */
final class PageStatusAutomation
{
    /** @var list<string> avvisi della richiesta corrente, letti da pullNotices() */
    private static array $notices = [];

    /** La regola, pura: null = lo stato non cambia. */
    public static function decide(PageStatus $current, int $usedBefore, int $usedAfter): ?PageStatus
    {
        $wasFull = $usedBefore >= Twelfths::PER_PAGE;
        $isFull = $usedAfter >= Twelfths::PER_PAGE;

        return match (true) {
            ! $wasFull && $isFull && $current === PageStatus::DaAssegnare => PageStatus::Assegnata,
            $wasFull && ! $isFull && $current !== PageStatus::DaAssegnare => PageStatus::DaAssegnare,
            default => null,
        };
    }

    /**
     * Dodicesimi occupati per pagina in una fotografia. I contenuti
     * eliminati (soft delete) non contano, come in Page::contents().
     *
     * @return array<int, int> page_id => dodicesimi
     */
    public static function usedByPage(array $snapshot): array
    {
        $used = [];

        foreach ($snapshot['page_content'] ?? [] as $row) {
            if (($snapshot['contents'][(int) $row['content_id']]['deleted_at'] ?? null) !== null) {
                continue;
            }

            $used[(int) $row['page_id']] = ($used[(int) $row['page_id']] ?? 0) + (int) $row['occupied_twelfths'];
        }

        return $used;
    }

    /**
     * Applica la regola alle pagine il cui spazio è cambiato fra le due
     * fotografie. $onlyContentIds: per le operazioni senza transazione
     * (creazione/modifica con file) solo le pagine dove stanno o stavano
     * quei contenuti, così una scrittura di un collega nel frattempo non
     * cambia stati a mio nome. $reason finisce nel registro attività.
     *
     * @param  list<int>|null  $onlyContentIds
     * @return int quante pagine hanno cambiato stato
     */
    public static function apply(Issue $issue, array $before, array $after, ?string $reason = null, ?array $onlyContentIds = null): int
    {
        $usedBefore = self::usedByPage($before);
        $usedAfter = self::usedByPage($after);
        $candidates = $onlyContentIds === null ? null : self::pagesOf($onlyContentIds, $before, $after);
        $changed = 0;

        foreach ($after['pages'] ?? [] as $pageId => $row) {
            $old = $usedBefore[$pageId] ?? 0;
            $new = $usedAfter[$pageId] ?? 0;

            if ($old === $new || $row['locked_at'] !== null || ($candidates !== null && ! isset($candidates[$pageId]))) {
                continue;
            }

            // Lo stato attuale, non quello della fotografia «prima»: se
            // l'operazione l'ha già deciso lei (es. «Sposta le pagine
            // esistenti dopo» lo porta con i contenuti) si parte da lì.
            $current = PageStatus::from($row['status']);
            $next = self::decide($current, $old, $new);

            if ($next === null) {
                continue;
            }

            self::change($issue, Page::findOrFail($pageId), $current, $next, $reason);
            $changed++;
        }

        if ($changed > 0) {
            $issue->markPagesUpdated();
        }

        return $changed;
    }

    /**
     * Avvisi da mostrare a chi ha fatto l'operazione («La pagina 12 è
     * tornata «Da assegnare» (era «Ok stampa»)»), poi azzerati. Statici
     * perché nascono dentro TimelineActionService::run(), che restituisce
     * il risultato dell'azione e non può portarli fino al componente.
     *
     * @return list<string>
     */
    public static function pullNotices(): array
    {
        $notices = self::$notices;
        self::$notices = [];

        return $notices;
    }

    private static function change(Issue $issue, Page $page, PageStatus $from, PageStatus $to, ?string $reason): void
    {
        $page->update(['status' => $to]);

        $label = $page->label();
        $why = $reason !== null && $reason !== '' ? "automatico, dopo: {$reason}" : 'automatico';

        ActivityLogger::log(
            issue: $issue,
            entityType: 'Page',
            entityId: $page->id,
            action: 'page.status_auto_changed',
            description: 'Stato '.$label.': '.$from->label().' → '.$to->label()." ({$why})",
            old: ['status' => $from->value],
            new: ['status' => $to->value],
        );

        // Solo se si torna indietro da uno stato più avanti di «Assegnata»:
        // una pagina pronta per la stampa non deve sparire in silenzio.
        if ($to === PageStatus::DaAssegnare && $from !== PageStatus::Assegnata) {
            self::$notices[] = 'La '.$label.' è tornata «'.$to->label().'» (era «'.$from->label().'»).';
        }

        if (auth()->check()) {
            self::broadcastAfterCommit(new PageStatusUpdated(
                issueId: $issue->id,
                pageId: $page->id,
                status: $to->value,
                updatedByUserId: auth()->id(),
                updatedByUserName: auth()->user()->name,
            ));
        }
    }

    /**
     * Dopo il commit: gli altri client, ricevuto l'evento, rileggono il
     * timone dal database e devono trovarci lo stato nuovo. Un Reverb
     * irraggiungibile non deve far fallire un'operazione già salvata
     * (gli altri lo vedono comunque col polling su pages_updated_at, già
     * aggiornato da apply()): si registra l'errore e si va avanti.
     */
    private static function broadcastAfterCommit(PageStatusUpdated $event): void
    {
        DB::afterCommit(function () use ($event) {
            try {
                broadcast($event)->toOthers();
            } catch (BroadcastException $e) {
                report($e);
            }
        });
    }

    /** @return array<int, true> pagine dove stanno o stavano i contenuti indicati */
    private static function pagesOf(array $contentIds, array $before, array $after): array
    {
        $contentIds = array_flip(array_map('intval', $contentIds));
        $pages = [];

        foreach ([$before, $after] as $snapshot) {
            foreach ($snapshot['page_content'] ?? [] as $row) {
                if (isset($contentIds[(int) $row['content_id']])) {
                    $pages[(int) $row['page_id']] = true;
                }
            }
        }

        return $pages;
    }
}
