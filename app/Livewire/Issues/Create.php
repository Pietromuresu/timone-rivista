<?php

namespace App\Livewire\Issues;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Magazine;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public Magazine $magazine;

    public string $title = '';

    public ?string $issue_date = '';

    public int $total_pages = 32;

    public string $notes = '';

    public ?int $duplicateFromIssueId = null;

    public function mount(Magazine $magazine): void
    {
        $this->authorize('create', Issue::class);

        abort_unless(auth()->user()->canAccessMagazine($magazine), 403);

        $this->magazine = $magazine;
    }

    /**
     * Pre-compila il numero di pagine con quello del numero scelto da
     * duplicare, così l'utente parte già da un valore sensato ma può
     * comunque correggerlo prima di salvare.
     */
    public function updatedDuplicateFromIssueId(): void
    {
        if ($this->duplicateFromIssueId === null) {
            return;
        }

        $source = $this->magazine->issues()->find($this->duplicateFromIssueId);

        if ($source !== null) {
            $this->total_pages = $source->total_pages;
        }
    }

    public function save()
    {
        $this->authorize('create', Issue::class);

        abort_unless(auth()->user()->canAccessMagazine($this->magazine), 403);

        if ($this->issue_date === '') {
            $this->issue_date = null;
        }

        $validated = $this->validate([
            'title' => 'required|string|max:180',
            'issue_date' => 'nullable|date',
            'total_pages' => 'required|integer|min:0|max:2000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $issue = Issue::create([
            'magazine_id' => $this->magazine->id,
            'title' => $validated['title'],
            'issue_date' => $validated['issue_date'],
            'total_pages' => $validated['total_pages'],
            'notes' => $validated['notes'],
            'status' => IssueStatus::Bozza,
        ]);

        if ($this->duplicateFromIssueId !== null) {
            $source = $this->magazine->issues()->find($this->duplicateFromIssueId);

            if ($source !== null) {
                $issue->duplicateStructureFrom($source);
            }
        }

        return redirect()->route('issues.show', [$this->magazine, $issue]);
    }

    public function render(): View
    {
        return view('livewire.issues.create', [
            'previousIssues' => $this->magazine->issues()->orderByDesc('issue_date')->get(),
        ]);
    }
}
