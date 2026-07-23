<?php

namespace App\Livewire\Issues;

use App\Models\Issue;
use App\Models\Magazine;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Layout('layouts.app')]
class Show extends Component
{
    public Magazine $magazine;

    public Issue $issue;

    public function mount(Magazine $magazine, Issue $issue): void
    {
        if ($issue->magazine_id !== $magazine->id) {
            throw new NotFoundHttpException;
        }

        $this->authorize('view', $issue);

        $this->magazine = $magazine;
        $this->issue = $issue;
    }

    public function render(): View
    {
        return view('livewire.issues.show');
    }
}
