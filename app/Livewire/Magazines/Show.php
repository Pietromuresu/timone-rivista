<?php

namespace App\Livewire\Magazines;

use App\Models\Magazine;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Magazine $magazine;

    public function mount(Magazine $magazine): void
    {
        $this->authorize('view', $magazine);

        $this->magazine = $magazine;
    }

    public function render(): View
    {
        $issues = $this->magazine->issues()
            ->orderByDesc('issue_date')
            ->get();

        return view('livewire.magazines.show', [
            'issues' => $issues,
        ]);
    }
}
