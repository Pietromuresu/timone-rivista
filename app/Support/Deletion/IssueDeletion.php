<?php

namespace App\Support\Deletion;

use App\Events\IssueDeleted;
use App\Jobs\DeleteStoredFiles;
use App\Models\Content;
use App\Models\Issue;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Eliminazione DEFINITIVA di un numero (revisione 2, Fase 4.2; nessun
 * SoftDeletes, scelta dell'utente). Pagine, contenuti, file, gruppi e
 * registri spariscono con le cascate del database; la cronologia
 * (activity_logs) resta con issue_id NULL e una voce «issue.deleted».
 */
final class IssueDeletion implements DeletionTarget
{
    public function __construct(private readonly Issue $issue) {}

    public function model(): object
    {
        return $this->issue;
    }

    public function label(): string
    {
        return "il numero «{$this->issue->title}» della rivista «{$this->issue->magazine->name}»";
    }

    public function confirmationText(): string
    {
        return $this->issue->title;
    }

    public function summary(): DeletionSummary
    {
        $files = StoredFiles::forIssues([$this->issue->id]);

        return new DeletionSummary([
            DeletionSummary::count($this->issue->allPages()->count(), 'pagina (copertine comprese)', 'pagine (copertine comprese)'),
            DeletionSummary::count(Content::where('issue_id', $this->issue->id)->count(), 'contenuto', 'contenuti'),
            DeletionSummary::filesLine($files),
        ]);
    }

    public function delete(User $by): void
    {
        self::deleteIssues(collect([$this->issue]), $by, 'issue.deleted');
    }

    public function redirectUrl(): string
    {
        return route('magazines.show', $this->issue->magazine);
    }

    /**
     * Usata anche da MagazineDeletion (i numeri di una rivista si eliminano
     * allo stesso modo). In transazione: voce di cronologia, file accodati
     * (afterCommit), righe eliminate. L'avviso agli utenti con il numero
     * aperto parte dopo il commit.
     *
     * @param  Collection<int, Issue>  $issues
     */
    public static function deleteIssues($issues, User $by, string $action, ?callable $alsoInTransaction = null): void
    {
        $issueIds = $issues->pluck('id')->all();
        $files = StoredFiles::forIssues($issueIds);

        DB::transaction(function () use ($issues, $files, $by, $action, $alsoInTransaction) {
            foreach ($issues as $issue) {
                ActivityLogger::log(
                    issue: null,
                    entityType: 'Issue',
                    entityId: $issue->id,
                    action: $action,
                    description: "Numero «{$issue->title}» della rivista «{$issue->magazine->name}» eliminato da {$by->name}",
                    old: ['title' => $issue->title, 'magazine' => $issue->magazine->name, 'total_pages' => $issue->total_pages],
                );
                $issue->delete();
            }

            $alsoInTransaction && $alsoInTransaction();

            if ($files->paths !== []) {
                DeleteStoredFiles::dispatch($files->paths, $action);
            }
        });

        Log::info($action, ['issue_ids' => $issueIds, 'by_user_id' => $by->id, 'files' => $files->count, 'bytes' => $files->bytes]);

        foreach ($issueIds as $issueId) {
            broadcast(new IssueDeleted($issueId));
        }
    }
}
