<?php

namespace App\Livewire\Timone;

use App\Models\ActivityLog;
use App\Models\Issue;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Componente Livewire a sé (stessa scelta di ContentCreate/PageFileHistory,
 * vedi HANDOFF.md "Decisioni architetturali da ricordare"): la cronologia
 * generale è un pannello di sola lettura, occasionale, non c'entra con
 * l'orchestrazione realtime che già affolla Grid.php.
 *
 * Deliberatamente **non** in ascolto di alcun evento broadcast per
 * aggiornarsi da solo mentre resta aperto (a differenza dello storico
 * spostamenti dentro Grid.php): dovrebbe ascoltare *tutti* gli eventi che
 * scrivono in activity_logs — quasi ogni evento del progetto — solo per
 * rifare la stessa query che farebbe comunque al prossimo giro. Non vale
 * la complessità per un pannello di controllo occasionale: chi lo tiene
 * aperto può chiuderlo e riaprirlo per vedere le voci più recenti.
 */
class ActivityLogPanel extends Component
{
    public Issue $issue;

    public bool $show = false;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    public function toggle(): void
    {
        $this->show = ! $this->show;
    }

    public function render(): View
    {
        $logs = $this->show
            ? ActivityLog::where('issue_id', $this->issue->id)->with('user')->latest('created_at')->limit(100)->get()
            : null;

        return view('livewire.timone.activity-log-panel', [
            'logs' => $logs,
        ]);
    }
}
