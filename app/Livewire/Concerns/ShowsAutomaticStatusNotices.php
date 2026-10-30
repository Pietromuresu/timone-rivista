<?php

namespace App\Livewire\Concerns;

use App\Support\PageStatusAutomation;

/**
 * Stato automatico della pagina (2026-10-30): mostra a chi ha fatto
 * l'operazione gli avvisi di PageStatusAutomation («La pagina 12 è tornata
 * «Da assegnare» (era «Ok stampa»)»). Riusa il toast di annulla/ripeti
 * (evento `timeline-result`, ascoltato da grid.blade.php), più lungo
 * perché è una notizia da non perdere.
 */
trait ShowsAutomaticStatusNotices
{
    protected function showAutomaticStatusNotices(): void
    {
        $notices = PageStatusAutomation::pullNotices();

        if ($notices !== []) {
            $this->dispatch('timeline-result', ok: true, message: implode(' ', $notices), canRedo: false, duration: 10000);
        }
    }
}
