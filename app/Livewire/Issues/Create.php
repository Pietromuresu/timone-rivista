<?php

namespace App\Livewire\Issues;

use App\Enums\IssueStatus;
use App\Livewire\Concerns\NormalizesEmptyNumericInput;
use App\Models\Issue;
use App\Models\Magazine;
use App\Support\Weight\WeightLimits;
use App\Support\Weight\WeightParameters;
use App\Support\Weight\WeightPreview;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    use NormalizesEmptyNumericInput;

    public Magazine $magazine;

    public string $title = '';

    public ?string $issue_date = '';

    public ?string $print_deadline_at = '';

    /**
     * `?int`, non `int` (Fase 1, bug 2, 2026-10-26): svuotando il campo il
     * browser invia '' — su una proprietà `int` Livewire non può né
     * assegnarla né convertirla e la "unset"-ta (vedi HandleComponents::
     * setComponentPropertyAwareOfTypes()), quindi il render successivo
     * esplodeva con PropertyNotFoundException. Con `?int` Livewire la porta
     * a null, la validazione `required` mostra l'errore inline al
     * salvataggio e anteprima peso/avviso multiplo-di-4 restano spenti
     * finché il valore non è valido.
     *
     * Default di classe `null` e 32 impostato in mount(): in idratazione
     * Livewire NON riassegna un null dello snapshot a una proprietà
     * tipizzata (HandleComponents::hydrateProperties, `continue` su
     * is_null), quindi con `= 32` qui il campo svuotato tornava a 32 alla
     * richiesta successiva e il salvataggio passava senza errore.
     */
    public ?int $total_pages = null;

    public string $notes = '';

    public ?int $duplicateFromIssueId = null;

    /**
     * Parametri del peso del nuovo numero (sezione "Carta e peso"),
     * pre-compilati con i default della rivista in mount(): al salvataggio
     * vengono COPIATI sull'issue, che da lì in poi è indipendente dalla
     * rivista (vedi Issue::weightInput()). Stringhe come ogni campo di form,
     * '' = vuoto.
     *
     * @var array<string, string>
     */
    public array $weight = [];

    public function mount(Magazine $magazine): void
    {
        $this->authorize('create', Issue::class);

        abort_unless(auth()->user()->canAccessMagazine($magazine), 403);

        $this->magazine = $magazine;
        $this->total_pages = 32;
        $this->weight = WeightParameters::formValuesFrom($magazine)
            + array_fill_keys(WeightParameters::ISSUE_ONLY_FIELDS, '');
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

        if ($this->print_deadline_at === '') {
            $this->print_deadline_at = null;
        }

        // "80,5" → "80.5" prima di validare, così virgola e punto salvano lo stesso valore.
        $this->weight = WeightParameters::normalizeInput($this->weight);

        $validated = $this->validate(WeightParameters::prefixedRules('weight', includeIssueOnly: true) + [
            'title' => 'required|string|max:180',
            'issue_date' => 'nullable|date',
            'print_deadline_at' => 'nullable|date',
            'total_pages' => 'required|integer|min:0|max:2000',
            'notes' => 'nullable|string|max:2000',
        ], WeightParameters::messages('weight'));

        $issue = Issue::create([
            'magazine_id' => $this->magazine->id,
            'title' => $validated['title'],
            'issue_date' => $validated['issue_date'],
            'print_deadline_at' => $validated['print_deadline_at'],
            'total_pages' => $validated['total_pages'],
            'notes' => $validated['notes'],
            'status' => IssueStatus::Bozza,
            ...WeightParameters::toDatabase($validated['weight']),
        ]);

        if ($this->duplicateFromIssueId !== null) {
            $source = $this->magazine->issues()->find($this->duplicateFromIssueId);

            if ($source !== null) {
                $issue->duplicateStructureFrom($source);
            }
        }

        return redirect()->route('issues.show', [$this->magazine, $issue]);
    }

    /**
     * Riporta formato, carta, maggiorazione e tolleranze ai default della
     * rivista; tiratura e peso reale (solo del numero) restano invariati.
     */
    public function restoreMagazineDefaults(): void
    {
        $this->resetValidation();
        $this->weight = WeightParameters::formValuesFrom($this->magazine) + $this->weight;
    }

    public function render(): View
    {
        return view('livewire.issues.create', [
            'previousIssues' => $this->magazine->issues()->orderByDesc('issue_date')->get(),
            'weightPreview' => $this->weightPreview(),
            'modifiedWeightFields' => $this->modifiedWeightFields(),
        ]);
    }

    /**
     * Anteprima live con i valori del form, senza nulla su database: tutto
     * il calcolo è in WeightPreview/MagazineWeightCalculator.
     */
    private function weightPreview(): WeightPreview
    {
        return WeightPreview::fromForm(
            $this->weight,
            $this->total_pages,
            config('peso_rivista.scaglioni'),
            WeightLimits::fromConfig(),
        );
    }

    /**
     * Campi il cui valore differisce dal default della rivista (badge
     * "modificato" nel form).
     *
     * @return list<string>
     */
    private function modifiedWeightFields(): array
    {
        return WeightParameters::modifiedFields($this->weight, $this->magazine);
    }
}
