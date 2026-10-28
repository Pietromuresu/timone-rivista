<?php

namespace App\Livewire\Timone;

use App\Enums\MagazineExportVariant;
use App\Models\Issue;
use App\Models\MagazineExport;
use App\Support\MagazineExport\MagazineExportRequester;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Pannello «PDF sfogliabile della rivista» (revisione 2, Fase 5,
 * 2026-10-28): avvia la generazione in coda e ne mostra lo stato (in coda /
 * in generazione / pronto / errore) con il download quando è pronto.
 * Componente a sé, fuori da Grid: non dipende dal timone e si aggiorna da
 * solo con un poll attivo SOLO mentre un job è in corso.
 *
 * Permesso: come gli altri export, basta poter vedere il numero
 * (`view`); un secondo click mentre è in coda non accoda un altro job
 * (MagazineExportRequester).
 */
class MagazineExportPanel extends Component
{
    public Issue $issue;

    public bool $preview = false;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    public function generate(): void
    {
        $this->authorize('view', $this->issue);

        MagazineExportRequester::request(
            $this->issue,
            $this->preview ? MagazineExportVariant::Preview : MagazineExportVariant::Full,
            auth()->id(),
        );
    }

    public function render(): View
    {
        $exports = MagazineExport::where('issue_id', $this->issue->id)->get()->keyBy(fn (MagazineExport $export) => $export->variant->value);

        return view('livewire.timone.magazine-export-panel', [
            'variants' => MagazineExportVariant::cases(),
            'exports' => $exports,
            'running' => $exports->contains(fn (MagazineExport $export) => $export->status->isRunning()),
        ]);
    }
}
