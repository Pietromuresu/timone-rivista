<?php

namespace App\Console\Commands;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Support\ActivityLogger;
use App\Support\PageStatusAutomation;
use App\Support\Twelfths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan timone:sync-page-status [--dry-run] [--issue=]` (2026-10-30):
 * lo stato automatico (PageStatusAutomation) scatta solo quando cambia lo
 * spazio, quindi le pagine riempite prima non sono allineate. Elenca le
 * pagine piene ancora «Da assegnare» e quelle non piene in «Assegnata»;
 * senza --dry-run corregge SOLO le prime (piena + «Da assegnare» →
 * «Assegnata»). Le seconde restano un elenco da guardare: tornare indietro
 * in automatico potrebbe cancellare una scelta fatta a mano. Le pagine
 * bloccate non si toccano. Non è una migration: si lancia dopo aver letto
 * il dry-run (vedi HANDOFF.md).
 */
class SyncPageStatus extends Command
{
    protected $signature = 'timone:sync-page-status {--issue= : Solo questo numero (id)} {--dry-run : Mostra cosa cambierebbe senza salvare}';

    protected $description = 'Pagine piene ancora «Da assegnare» → «Assegnata» (stato automatico della pagina)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $used = DB::table('page_content')
            ->join('contents', 'contents.id', '=', 'page_content.content_id')
            ->whereNull('contents.deleted_at')
            ->groupBy('page_content.page_id')
            ->selectRaw('page_content.page_id as page_id, SUM(page_content.occupied_twelfths) as used')
            ->pluck('used', 'page_id');

        $pages = Page::query()
            ->with('issue')
            ->when($this->option('issue'), fn ($query, $issueId) => $query->where('issue_id', $issueId))
            ->whereIn('status', [PageStatus::DaAssegnare->value, PageStatus::Assegnata->value])
            ->orderBy('issue_id')->orderBy('id')
            ->get();

        $fixed = 0;
        $toCheck = 0;
        $changedIssues = collect();

        foreach ($pages as $page) {
            $twelfths = (int) ($used[$page->id] ?? 0);
            $line = sprintf('  [%d] numero «%s», %s (%s)', $page->id, $page->issue?->title, $page->label(), Twelfths::pagesLabel($twelfths));

            if ($page->status === PageStatus::Assegnata && $twelfths < Twelfths::PER_PAGE) {
                $toCheck++;
                $this->line($line.': «Assegnata» ma non piena — da controllare a mano, non cambia');

                continue;
            }

            // Stessa regola dell'automatismo: da vuota a com'è ora.
            if (PageStatusAutomation::decide($page->status, 0, $twelfths) !== PageStatus::Assegnata) {
                continue;
            }

            if ($page->isLocked()) {
                $this->line($line.': piena, «Da assegnare», bloccata — non cambia');

                continue;
            }

            $fixed++;
            $this->line($line.': piena, «Da assegnare» → «Assegnata»');

            if (! $dryRun) {
                $page->update(['status' => PageStatus::Assegnata]);
                $changedIssues->put($page->issue_id, $page->issue);
                ActivityLogger::log(
                    issue: $page->issue,
                    entityType: 'Page',
                    entityId: $page->id,
                    action: 'page.status_auto_changed',
                    description: 'Stato '.$page->label().': Da assegnare → Assegnata (automatico, riallineamento con timone:sync-page-status)',
                    old: ['status' => PageStatus::DaAssegnare->value],
                    new: ['status' => PageStatus::Assegnata->value],
                );
            }
        }

        // Polling degli utenti con il timone aperto.
        $changedIssues->filter()->each->markPagesUpdated();

        $this->newLine();
        $this->info(($dryRun ? 'Dry-run: ' : '')."{$fixed} pagine piene ".($dryRun ? 'passerebbero' : 'passate')." ad «Assegnata»; {$toCheck} «Assegnata» non piene da controllare a mano.".($dryRun ? ' Nessuna modifica scritta.' : ''));

        return self::SUCCESS;
    }
}
