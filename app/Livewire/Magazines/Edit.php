<?php

namespace App\Livewire\Magazines;

use App\Enums\CoverFlaps;
use App\Enums\Periodicity;
use App\Enums\UserRole;
use App\Livewire\Concerns\NormalizesEmptyNumericInput;
use App\Models\Magazine;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Signatures\SignaturePlan;
use App\Support\Weight\WeightLimits;
use App\Support\Weight\WeightParameters;
use App\Support\Weight\WeightPreview;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Prima UI di modifica di una Magazine già esistente (Fase 5.2) — fino a
 * questa sessione esisteva solo Create, quindi campi come
 * la soglia di allarme pubblicitario (tolta nella Fase 6 del 2026-10-27; battente_counted_in_total non è più modificabile dalla Fase 2 del 2026-10-26: la bandella è una copertina a parte e non entra mai nel totale)
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

    public string $notes = '';

    public ?int $redattore_id = null;

    /** Segnature (2026-10-29): pagine per segnatura (SignaturePlan::SIZES). */
    public int $signature_pages = SignaturePlan::DEFAULT_SIZE;

    /** Segnature: facciate di copertina contate in testa alla prima segnatura. */
    public bool $signature_includes_covers = false;

    /**
     * Battenti dei nuovi numeri (2026-10-30, App\Enums\CoverFlaps): al posto
     * del numero «Pagine copertina», che ne deriva (syncCoverPages()).
     */
    public string $default_cover_flaps = 'nessuno';

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
        $this->notes = $magazine->notes ?? '';
        // (bool) esplicito: una Magazine passata qui appena creata via
        // factory senza specificare il campo ha 'null' in memoria PHP
        // finché non viene riletta dal DB (il default 'false' è solo a
        // livello di colonna, Eloquent non lo rilegge dopo un insert) —
        // stessa gotcha già documentata in HANDOFF.md per reorder_version/
        // role. In produzione arriva sempre da route model binding (query
        // reale), questo è solo un secondo strato difensivo a costo zero.
        $this->redattore_id = $magazine->redattore_id;
        $this->signature_pages = (int) ($magazine->signature_pages ?? SignaturePlan::DEFAULT_SIZE);
        $this->signature_includes_covers = (bool) $magazine->signature_includes_covers;
        $this->weight = WeightParameters::formValuesFrom($magazine);
        $this->default_cover_flaps = ($magazine->default_cover_flaps ?? CoverFlaps::None)->value;
        $this->syncCoverPages();
        $this->previewTotalPages = (string) ($magazine->issues()->orderByDesc('issue_date')->value('total_pages') ?? '');
    }

    public function save()
    {
        $this->authorize('update', $this->magazine);

        // "80,5" → "80.5" prima di validare, così virgola e punto salvano lo stesso valore.
        $this->weight = WeightParameters::normalizeInput($this->weight);
        $this->syncCoverPages();

        $validated = $this->validate(WeightParameters::prefixedRules('weight') + [
            'default_cover_flaps' => 'required|in:'.implode(',', array_column(CoverFlaps::cases(), 'value')),
            'name' => 'required|string|max:180',
            'periodicity' => 'required|in:'.implode(',', array_column(Periodicity::cases(), 'value')),
            'color' => 'required|string|max:7',
            'notes' => 'nullable|string|max:2000',
            'redattore_id' => 'nullable|exists:users,id',
            'signature_pages' => 'required|integer|in:'.implode(',', SignaturePlan::SIZES),
            'signature_includes_covers' => 'boolean',
        ], WeightParameters::messages('weight'));

        $weightValues = WeightParameters::toDatabase($validated['weight']);
        unset($validated['weight']);

        $this->logWeightDefaultsChange($weightValues);

        $signaturesChanged = (int) $this->magazine->signature_pages !== (int) $validated['signature_pages']
            || (bool) $this->magazine->signature_includes_covers !== (bool) $validated['signature_includes_covers'];

        $this->magazine->update($validated + $weightValues);

        // Segnature (2026-10-29): i timoni aperti di questa rivista ricalcolano
        // conteggio e confini al prossimo poll (stesso segnale delle pagine).
        if ($signaturesChanged) {
            $this->magazine->issues()->update(['pages_updated_at' => now()]);
        }

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

    public function updatedDefaultCoverFlaps(): void
    {
        $this->syncCoverPages();
    }

    /** Le facciate di copertina del peso seguono i battenti scelti (anteprima e salvataggio). */
    private function syncCoverPages(): void
    {
        $this->weight['pagine_copertina'] = (string) (CoverFlaps::tryFrom($this->default_cover_flaps) ?? CoverFlaps::None)->coverPages();
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
