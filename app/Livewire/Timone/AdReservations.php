<?php

namespace App\Livewire\Timone;

use App\Enums\AdMaterialStatus;
use App\Enums\IssueStatus;
use App\Models\Advertisement;
use App\Models\Issue;
use App\Support\ActivityLogger;
use App\Support\AdMaterialStatusResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * "Pubblicità prenotate" (Fase 3, §3) — componente Livewire a sé, stesso
 * principio già seguito per ContentCreate/PageFileHistory/ActivityLogPanel
 * (vedi HANDOFF.md, "Decisioni architetturali da ricordare"): gestione
 * indipendente dalla griglia timone, bottone+pannello insieme come
 * ActivityLogPanel. Non crea nuove pubblicità (riusa ContentCreate,
 * già in grado di creare un Content pubblicitario senza assegnarlo a
 * nessuna pagina — che è esattamente cosa significa "prenotato" in questo
 * schema, vedi AdMaterialStatusResolver): solo elenco, eliminazione di una
 * prenotazione non ancora assegnata, e chiusura del numero.
 *
 * Deliberatamente **non** in ascolto di eventi broadcast per aggiornarsi
 * da solo (stesso principio di ActivityLogPanel: pannello occasionale, non
 * vale la complessità di ascoltare ContentCreated/ContentAssigned/
 * ContentUnassigned/PageFileUploaded solo per rifare la stessa query).
 */
class AdReservations extends Component
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

    /**
     * Elimina una prenotazione — solo se non è ancora assegnata a nessuna
     * pagina (spec: "salvo eliminazione esplicita della prenotazione"):
     * una pubblicità già assegnata/con materiale è un'operazione più
     * delicata (coinvolge page_content/page_files reali), fuori scopo per
     * questa azione rapida — va rimossa dalla pagina prima (già possibile
     * da Grid.php, bottone "✕" esistente).
     */
    public function deleteReservation(int $contentId): void
    {
        $this->authorize('update', $this->issue);

        $advertisement = Advertisement::with('content')->whereHas(
            'content', fn ($query) => $query->whereKey($contentId)->where('issue_id', $this->issue->id)
        )->first();

        if ($advertisement === null || $advertisement->content->pages()->exists()) {
            return;
        }

        $client = $advertisement->client;
        $advertisement->content->delete(); // cascata su advertisements (content_id, cascadeOnDelete)

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Content',
            entityId: $contentId,
            action: 'content.reservation_deleted',
            description: "Prenotazione pubblicitaria «{$client}» eliminata",
            old: ['client' => $client],
        );
    }

    /**
     * Chiude il numero (§3: "un'edizione non può essere marcata come
     * chiusa se esistono contenuti pubblicitari ancora prenotati senza
     * pagina assegnata o senza materiale") — bloccato finché anche una
     * sola pubblicità dell'issue non ha raggiunto AdMaterialStatus::Completo.
     * Non broadcastato: stessa scelta già presa per updateAdThreshold()
     * in Grid.php (un'azione rara, chi altro è collegato si allinea al
     * prossimo giro di render()/poll).
     */
    public function closeIssue(): void
    {
        $this->authorize('update', $this->issue);

        $this->resetErrorBag('close');

        if ($this->issue->status === IssueStatus::Chiuso) {
            return;
        }

        $incomplete = $this->incompleteAdvertisements();

        if ($incomplete->isNotEmpty()) {
            $clients = $incomplete->pluck('client')->implode(', ');
            $count = $incomplete->count();

            $this->addError(
                'close',
                ($count === 1 ? 'Il cliente' : 'I clienti')." {$clients} ".($count === 1 ? 'ha' : 'hanno').' uno spazio pubblicitario prenotato o assegnato ma non ancora completo: chiudi il numero solo dopo aver ricevuto il materiale, oppure elimina la prenotazione.'
            );

            return;
        }

        $oldStatus = $this->issue->status;
        $this->issue->update(['status' => IssueStatus::Chiuso]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Issue',
            entityId: $this->issue->id,
            action: 'issue.closed',
            description: 'Numero chiuso',
            old: ['status' => $oldStatus->value],
            new: ['status' => IssueStatus::Chiuso->value],
        );
    }

    /**
     * @return Collection<int, Advertisement>
     */
    private function incompleteAdvertisements(): Collection
    {
        return $this->issueAdvertisements()
            ->filter(fn (Advertisement $ad) => AdMaterialStatusResolver::resolve($ad->content->pages) !== AdMaterialStatus::Completo)
            ->values();
    }

    /**
     * @return Collection<int, Advertisement>
     */
    private function issueAdvertisements(): Collection
    {
        return Advertisement::whereHas('content', fn ($query) => $query->where('issue_id', $this->issue->id))
            ->with(['content.pages.files'])
            ->get();
    }

    public function render(): View
    {
        $rows = null;

        if ($this->show) {
            // Ordine di priorità (non alfabetico sul value dell'enum, che
            // darebbe assegnato/completo/prenotato): prenotato per primo,
            // è lo stato che richiede più attenzione da parte della
            // redazione — completo per ultimo, non richiede più nulla.
            $priority = [AdMaterialStatus::Prenotato->value => 0, AdMaterialStatus::Assegnato->value => 1, AdMaterialStatus::Completo->value => 2];

            $rows = $this->issueAdvertisements()
                ->map(fn (Advertisement $ad) => [
                    'advertisement' => $ad,
                    'status' => AdMaterialStatusResolver::resolve($ad->content->pages),
                    'positions' => $ad->content->pages->pluck('position')->sort()->values()->all(),
                ])
                ->sortBy(fn (array $row) => $priority[$row['status']->value])
                ->values();
        }

        return view('livewire.timone.ad-reservations', [
            'rows' => $rows,
        ]);
    }
}
