<?php

namespace App\Livewire\Timone;

use App\Models\Issue;
use App\Support\ActivityLogger;
use App\Support\Weight\MagazineWeightCalculator;
use App\Support\Weight\WeightLimits;
use App\Support\Weight\WeightParameters;
use App\Support\Weight\WeightPreview;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pannello "Peso" del numero: peso teorico di una copia ± margine,
 * scaglione, dettaglio interno/copertina/maggiorazione, tiratura,
 * calibrazione con il peso reale e simulazione +/- pagine.
 *
 * Livello sottile di orchestrazione (CLAUDE.md, regola 2): tutto il calcolo
 * è in App\Support\Weight\MagazineWeightCalculator (anteprima della modale
 * compresa, via WeightPreview), qui ci sono solo input
 * utente, autorizzazione, salvataggio e cronologia. Componente separato da
 * Timone\Grid (già 2000+ righe) di proposito.
 *
 * Aggiornamento quando cambiano le pagine del timone: render() ricalcola
 * sempre da issues.total_pages; il componente si ri-renderizza su
 * IssuePageCountUpdated via Reverb (modifiche di altri utenti) e
 * sull'evento Livewire `issue-page-count-changed`, che Grid emette dopo un
 * proprio resizePages() e quando il polling di fallback (checkForUpdates)
 * rileva una modifica.
 */
class WeightPanel extends Component
{
    public Issue $issue;

    /** Simulazione: pagine interne da aggiungere (positive) o togliere (negative). */
    public string $simulateDelta = '';

    /**
     * Parametri del numero in modifica nella modale (FIELDS + ISSUE_ONLY_FIELDS).
     * Vuoto finché la modale non è stata aperta: render() calcola
     * l'anteprima solo quando contiene qualcosa. Il salvataggio scrive solo
     * sul numero, mai sui default della rivista.
     *
     * @var array<string, string>
     */
    public array $params = [];

    public function mount(Issue $issue): void
    {
        // Ridondante con Issues\Show::mount(), di proposito: vedi audit di
        // sicurezza Fase 6.2 in HANDOFF.md (componenti annidati senza un
        // authorize proprio).
        $this->authorize('view', $issue);

        $this->issue = $issue;
    }

    #[On('echo-presence:issue.{issue.id},.IssuePageCountUpdated')]
    // Fase 2: i battenti (prima la bandella) cambiano le pagine di copertina del peso.
    #[On('echo-presence:issue.{issue.id},.CoversChanged')]
    #[On('issue-page-count-changed')]
    public function onPageCountChanged(): void
    {
        // Corpo vuoto: il re-render ricalcola il peso da total_pages,
        // riletto dal database a ogni richiesta Livewire.
    }

    public function openEditor(): void
    {
        $this->authorize('update', $this->issue);

        $this->resetErrorBag();
        $this->params = WeightParameters::formValuesFrom(
            $this->issue,
            [...WeightParameters::FIELDS, ...WeightParameters::ISSUE_ONLY_FIELDS],
        );
        // Fase 2 (2026-10-26): per un numero le pagine di copertina non si
        // scrivono a mano, derivano dalle copertine esistenti (campo in
        // sola lettura nella modale, ignorato da saveParams()).
        $this->params['pagine_copertina'] = (string) $this->issue->coverPageCount();

        $this->dispatch('open-modal', 'weight-params-'.$this->issue->id);
    }

    public function saveParams(): void
    {
        $this->authorize('update', $this->issue);

        // "80,5" → "80.5" prima di validare, così virgola e punto salvano lo stesso valore.
        $this->params = WeightParameters::normalizeInput($this->params);

        $values = WeightParameters::toDatabase($this->validate(
            WeightParameters::prefixedRules('params', includeIssueOnly: true),
            WeightParameters::messages('params'),
        )['params']);

        // Derivato dalle copertine (IssueCovers::syncPrintedPages()), mai dal form.
        unset($values['pagine_copertina']);

        $old = [];
        $new = [];

        foreach ($values as $field => $value) {
            if (! WeightParameters::equals($this->issue->{$field}, $value)) {
                $old[$field] = $this->issue->{$field};
                $new[$field] = $value;
            }
        }

        if ($new !== []) {
            $this->issue->update($new);

            ActivityLogger::log(
                issue: $this->issue,
                entityType: 'Issue',
                entityId: $this->issue->id,
                action: 'issue.weight_params_changed',
                description: 'Parametri peso del numero modificati: '.implode(', ', array_map(
                    fn (string $field) => WeightParameters::LABELS[$field] ?? $field,
                    array_keys($new),
                )),
                old: $old,
                new: $new,
            );
        }

        $this->dispatch('close-modal', 'weight-params-'.$this->issue->id);
    }

    /**
     * Riporta nella modale formato, carta, maggiorazione e tolleranze ai
     * default ATTUALI della rivista (tiratura e peso reale restano). Non
     * salva: l'utente vede l'anteprima e conferma con "Salva".
     */
    public function restoreMagazineDefaults(): void
    {
        $this->authorize('update', $this->issue);

        $this->resetValidation();
        $this->params = WeightParameters::formValuesFrom($this->issue->magazine) + $this->params;
        $this->params['pagine_copertina'] = (string) $this->issue->coverPageCount();
    }

    /**
     * Copia la maggiorazione suggerita dal peso reale nei default della
     * RIVISTA (mai sul numero corrente, mai in automatico): vale per i
     * numeri creati da qui in poi. La conferma è lato client (wire:confirm);
     * il permesso è quello di modifica della rivista (solo Admin).
     */
    public function applySuggestedSurcharge(): void
    {
        $magazine = $this->issue->magazine;
        $this->authorize('update', $magazine);

        $suggested = $this->issue->weight()->maggiorazioneSuggerita;

        if ($suggested === null) {
            return;
        }

        // 2 decimali: la colonna è decimal(5,2).
        $value = round($suggested, 2);

        if ($value < 0 || $value > WeightParameters::MAX_MAGGIORAZIONE) {
            $this->addError('suggestion', 'La maggiorazione suggerita è fuori dall\'intervallo ammesso: controlla il peso reale inserito.');

            return;
        }

        $old = $magazine->maggiorazione_percentuale;
        $magazine->update(['maggiorazione_percentuale' => $value]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Magazine',
            entityId: $magazine->id,
            action: 'magazine.weight_defaults_changed',
            description: "Maggiorazione di default della rivista «{$magazine->name}» impostata a {$value}% dalla calibrazione del numero «{$this->issue->title}» (vale per i nuovi numeri)",
            old: ['maggiorazione_percentuale' => $old],
            new: ['maggiorazione_percentuale' => $value],
        );

        session()->flash('weight-status', "Maggiorazione {$value}% salvata nei default della rivista: verrà usata per i prossimi numeri.");
    }

    public function render(): View
    {
        $brackets = config('peso_rivista.scaglioni');
        $limits = WeightLimits::fromConfig();
        $input = $this->issue->weightInput();
        $result = MagazineWeightCalculator::calculate($input, $brackets, $limits);

        $delta = trim($this->simulateDelta);
        $simulation = $delta !== '' && preg_match('/^[+-]?\d+$/', $delta) === 1 && (int) $delta !== 0 && $result->complete
            ? MagazineWeightCalculator::simulate($input, (int) $delta, $brackets, $limits)
            : null;

        // Anteprima della modale: valori non salvati, confrontati con $result (salvato).
        $preview = $this->params !== []
            ? WeightPreview::fromForm($this->params, $this->issue->total_pages, $brackets, $limits, saved: $result)
            : null;

        return view('livewire.timone.weight-panel', [
            'result' => $result,
            'simulation' => $simulation,
            'simulatedDelta' => $simulation !== null ? (int) $delta : null,
            'addable' => MagazineWeightCalculator::addablePages($input, $brackets, $limits),
            'preview' => $preview,
            'modifiedFields' => $this->params !== [] ? WeightParameters::modifiedFields($this->params, $this->issue->magazine) : [],
            'brackets' => $brackets,
            'canUpdate' => auth()->user()->can('update', $this->issue),
            'canUpdateMagazine' => auth()->user()->can('update', $this->issue->magazine),
        ]);
    }
}
