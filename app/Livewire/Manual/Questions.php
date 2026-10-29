<?php

namespace App\Livewire\Manual;

use App\Support\Manual\ManualInsights;
use App\Support\Manual\ManualRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * «Cosa chiedono gli utenti» (2026-10-29), solo per gli admin: domande
 * frequenti senza risposta, domande imparate, risposte probabilmente
 * sbagliate, ultimo ricalcolo e ultimo blocco del cancello. Un'unica
 * azione, «Disattiva», su una domanda imparata o una correzione: tutto il
 * resto si corregge nelle pagine del manuale o in domande.yml, non qui.
 */
#[Layout('layouts.app')]
class Questions extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function disable(string $type, string $key, string $slug, ManualInsights $insights): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $insights->disable($type, $key, $slug);
    }

    public function render(ManualInsights $insights): View
    {
        $titles = ManualRepository::fromConfig()->all()->map(fn ($page) => $page->titolo);

        return view('livewire.manual.questions', [
            'unanswered' => $insights->unanswered(),
            'learned' => $insights->learnedQuestions(),
            'corrections' => $insights->corrections(),
            'suspects' => $insights->suspects(),
            'lastRun' => $insights->lastRun(),
            'blocked' => $insights->lastBlockedRun(),
            'titles' => $titles,
        ]);
    }
}
