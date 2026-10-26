<?php

namespace App\Livewire\Magazines;

use App\Enums\Periodicity;
use App\Enums\UserRole;
use App\Livewire\Concerns\NormalizesEmptyNumericInput;
use App\Models\Magazine;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Weight\WeightLimits;
use App\Support\Weight\WeightParameters;
use App\Support\Weight\WeightPreview;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Prima UI di modifica di una Magazine già esistente (Fase 5.2) — fino a
 * questa sessione esisteva solo Create, quindi campi come
 * ad_threshold_percentage/battente_counted_in_total (Punto 5/Fase 3.1)
 * restavano fissati per sempre al valore scelto in creazione. Riusa lo
 * stesso set di campi di Create (stessa validazione) invece di limitarsi al
 * solo redattore, per chiudere quel limite già segnalato in HANDOFF.md
 * insieme al problema che questa fase deve comunque risolvere.
 */
#[Layout('layouts.app')]
class Edit extends Component
{
    use NormalizesEmptyNumericInput;

    public Magazine $magazine;

    public string $name = '';

    public string $periodicity = 'mensile';

    public string $color = '#3B82F6';

    public ?string $ad_threshold_percentage = '';

    public string $notes = '';

    public bool $battente_counted_in_total = false;

    public ?int $redattore_id = null;

    /**
     * Default del calcolo peso della rivista (WeightParameters::FIELDS).
     * Valgono SOLO per i numeri creati dopo il salvataggio: i numeri
     * esistenti hanno una propria copia dei parametri (Issue::weightInput()).
     *
     * @var array<string, string>
     */
    public array $weight = [];

    /**
     * Pagine totali usate SOLO per l'anteprima del peso (mai salvate): la
     * rivista non ha un numero di pagine proprio, quindi si propone quello
     * dell'ultimo numero e l'utente può cambiarlo per provare.
     */
    public string $previewTotalPages = '';

    public function mount(Magazine $magazine): void
    {
        $this->authorize('update', $magazine);

        $this->magazine = $magazine;
        $this->name = $magazine->name;
        $this->periodicity = $magazine->periodicity->value;
        $this->color = $magazine->color;
        $this->ad_threshold_percentage = $magazine->ad_threshold_percentage === null
            ? ''
            : (string) $magazine->ad_threshold_percentage;
        $this->notes = $magazine->notes ?? '';
        // (bool) esplicito: una Magazine passata qui appena creata via
        // factory senza specificare il campo ha 'null' in memoria PHP
        // finché non viene riletta dal DB (il default 'false' è solo a
        // livello di colonna, Eloquent non lo rilegge dopo un insert) —
        // stessa gotcha già documentata in HANDOFF.md per reorder_version/
        // role. In produzione arriva sempre da route model binding (query
        // reale), questo è solo un secondo strato difensivo a costo zero.
        $this->battente_counted_in_total = (bool) $magazine->battente_counted_in_total;
        $this->redattore_id = $magazine->redattore_id;
        $this->weight = WeightParameters::formValuesFrom($magazine);
        $this->previewTotalPages = (string) ($magazine->issues()->orderByDesc('issue_date')->value('total_pages') ?? '');
    }

    public function save()
    {
        $this->authorize('update', $this->magazine);

        if ($this->ad_threshold_percentage === '') {
            $this->ad_threshold_percentage = null;
        }

        // "80,5" → "80.5" prima di validare, così virgola e punto salvano lo stesso valore.
        $this->weight = WeightParameters::normalizeInput($this->weight);

        $validated = $this->validate(WeightParameters::prefixedRules('weight') + [
            'name' => 'required|string|max:180',
            'periodicity' => 'required|in:'.implode(',', array_column(Periodicity::cases(), 'value')),
            'color' => 'required|string|max:7',
            'ad_threshold_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'battente_counted_in_total' => 'boolean',
            'redattore_id' => 'nullable|exists:users,id',
        ], WeightParameters::messages('weight'));

        $weightValues = WeightParameters::toDatabase($validated['weight']);
        unset($validated['weight']);

        $this->logWeightDefaultsChange($weightValues);

        $this->magazine->update($validated + $weightValues);

        // Fase 5.2: il redattore di riferimento deve poter vedere/gestire la
        // sua rivista, non solo comparire come etichetta — canAccessMagazine()
        // guarda esclusivamente il pivot magazine_user, quindi assegnarlo qui
        // deve anche garantirgli l'accesso, o resterebbe un campo cosmetico
        // che rimanda a un utente senza permessi reali sulla rivista.
        // attach() (non sync()): non deve MAI togliere accesso già concesso
        // ad altri utenti, né al redattore precedente se sostituito — questo
        // form gestisce solo "chi è il redattore", non l'elenco di accesso
        // generale (quello resta la UI di Users\Edit già esistente).
        if ($this->redattore_id !== null) {
            $this->magazine->users()->syncWithoutDetaching([$this->redattore_id]);
        }

        return redirect()->route('magazines.show', $this->magazine);
    }

    /**
     * Le modifiche ai default del peso finiscono nella cronologia generale
     * (activity_logs, senza issue: è un'impostazione della rivista), così
     * si sa chi ha cambiato carta/formato e da quando vale per i nuovi numeri.
     *
     * @param  array<string, mixed>  $newValues
     */
    private function logWeightDefaultsChange(array $newValues): void
    {
        $old = [];
        $new = [];

        foreach ($newValues as $field => $value) {
            if (! WeightParameters::equals($this->magazine->getOriginal($field), $value)) {
                $old[$field] = $this->magazine->getOriginal($field);
                $new[$field] = $value;
            }
        }

        if ($new === []) {
            return;
        }

        ActivityLogger::log(
            issue: null,
            entityType: 'Magazine',
            entityId: $this->magazine->id,
            action: 'magazine.weight_defaults_changed',
            description: "Default peso della rivista «{$this->magazine->name}» modificati (valgono per i nuovi numeri)",
            old: $old,
            new: $new,
        );
    }

    public function render(): View
    {
        return view('livewire.magazines.edit', [
            'weightPreview' => $this->weightPreview(),
            'periodicities' => Periodicity::cases(),
            'redattori' => User::where('role', UserRole::Redattore)->orWhere('role', UserRole::Admin)->orderBy('name')->get(),
        ]);
    }

    /**
     * Anteprima con i default in modifica, confrontata con i default
     * salvati sulla stessa quantità di pagine di prova.
     */
    private function weightPreview(): WeightPreview
    {
        $pages = ctype_digit(trim($this->previewTotalPages)) ? (int) trim($this->previewTotalPages) : null;
        $brackets = config('peso_rivista.scaglioni');
        $limits = WeightLimits::fromConfig();

        return WeightPreview::fromForm(
            $this->weight,
            $pages,
            $brackets,
            $limits,
            saved: WeightPreview::fromForm(WeightParameters::formValuesFrom($this->magazine), $pages, $brackets, $limits)->result,
        );
    }
}
