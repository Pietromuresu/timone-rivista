<?php

namespace App\Livewire\Magazines;

use App\Enums\Periodicity;
use App\Models\Magazine;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $periodicity = 'mensile';

    public string $color = '#3B82F6';

    public ?string $ad_threshold_percentage = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('create', Magazine::class);
    }

    public function save()
    {
        $this->authorize('create', Magazine::class);

        // Il campo arriva sempre come stringa dall'input HTML (anche
        // quando vuoto): normalizzato a null prima di validare, altrimenti
        // "numeric" fallirebbe su una stringa vuota nonostante "nullable"
        // (che in Laravel salta le altre regole solo per un valore null
        // vero e proprio, non per una stringa vuota).
        if ($this->ad_threshold_percentage === '') {
            $this->ad_threshold_percentage = null;
        }

        $validated = $this->validate([
            'name' => 'required|string|max:180',
            'periodicity' => 'required|in:'.implode(',', array_column(Periodicity::cases(), 'value')),
            'color' => 'required|string|max:7',
            'ad_threshold_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        $magazine = Magazine::create($validated);

        return redirect()->route('magazines.show', $magazine);
    }

    public function render(): View
    {
        return view('livewire.magazines.create', [
            'periodicities' => Periodicity::cases(),
        ]);
    }
}
