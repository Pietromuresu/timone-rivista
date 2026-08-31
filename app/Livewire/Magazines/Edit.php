<?php

namespace App\Livewire\Magazines;

use App\Enums\Periodicity;
use App\Enums\UserRole;
use App\Models\Magazine;
use App\Models\User;
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
    public Magazine $magazine;

    public string $name = '';

    public string $periodicity = 'mensile';

    public string $color = '#3B82F6';

    public ?string $ad_threshold_percentage = '';

    public string $notes = '';

    public bool $battente_counted_in_total = false;

    public ?int $redattore_id = null;

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
    }

    public function save()
    {
        $this->authorize('update', $this->magazine);

        if ($this->ad_threshold_percentage === '') {
            $this->ad_threshold_percentage = null;
        }

        $validated = $this->validate([
            'name' => 'required|string|max:180',
            'periodicity' => 'required|in:'.implode(',', array_column(Periodicity::cases(), 'value')),
            'color' => 'required|string|max:7',
            'ad_threshold_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'battente_counted_in_total' => 'boolean',
            'redattore_id' => 'nullable|exists:users,id',
        ]);

        $this->magazine->update($validated);

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

    public function render(): View
    {
        return view('livewire.magazines.edit', [
            'periodicities' => Periodicity::cases(),
            'redattori' => User::where('role', UserRole::Redattore)->orWhere('role', UserRole::Admin)->orderBy('name')->get(),
        ]);
    }
}
