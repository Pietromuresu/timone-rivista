<?php

namespace App\Support\Deletion;

use App\Models\Content;
use App\Models\Magazine;
use App\Models\Page;
use App\Models\User;
use App\Support\ActivityLogger;

/**
 * Eliminazione DEFINITIVA di una rivista con tutti i suoi numeri (revisione
 * 2, Fase 4.2). I numeri passano da IssueDeletion::deleteIssues() (stessa
 * cronologia, stessi file, stesso avviso a chi li ha aperti); rubriche e
 * accessi degli utenti spariscono con le cascate, gli utenti restano.
 */
final class MagazineDeletion implements DeletionTarget
{
    public function __construct(private readonly Magazine $magazine) {}

    public function model(): object
    {
        return $this->magazine;
    }

    public function label(): string
    {
        return "la rivista «{$this->magazine->name}»";
    }

    public function confirmationText(): string
    {
        return $this->magazine->name;
    }

    public function summary(): DeletionSummary
    {
        $issues = $this->magazine->issues()->orderBy('created_at')->get(['id', 'title']);
        $issueIds = $issues->pluck('id')->all();

        return new DeletionSummary(
            [
                DeletionSummary::count($issues->count(), 'numero', 'numeri'),
                DeletionSummary::count(Page::whereIn('issue_id', $issueIds)->count(), 'pagina (copertine comprese)', 'pagine (copertine comprese)'),
                DeletionSummary::count(Content::whereIn('issue_id', $issueIds)->count(), 'contenuto', 'contenuti'),
                DeletionSummary::filesLine(StoredFiles::forIssues($issueIds)),
                DeletionSummary::count($this->magazine->sections()->count(), 'rubrica', 'rubriche'),
            ],
            $issues->pluck('title')->all(),
            'Numeri che verranno eliminati',
        );
    }

    public function delete(User $by): void
    {
        $magazine = $this->magazine;

        IssueDeletion::deleteIssues(
            $magazine->issues()->with('magazine')->get(),
            $by,
            'issue.deleted_with_magazine',
            function () use ($magazine, $by) {
                ActivityLogger::log(
                    issue: null,
                    entityType: 'Magazine',
                    entityId: $magazine->id,
                    action: 'magazine.deleted',
                    description: "Rivista «{$magazine->name}» eliminata da {$by->name}",
                    old: ['name' => $magazine->name, 'slug' => $magazine->slug],
                );
                $magazine->delete();
            },
        );
    }

    public function redirectUrl(): string
    {
        return route('magazines.index');
    }
}
