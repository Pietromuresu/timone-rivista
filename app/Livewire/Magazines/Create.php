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

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('create', Magazine::class);
    }

    public function save()
    {
        $this->authorize('create', Magazine::class);

        $validated = $this->validate([
            'name' => 'required|string|max:180',
            'periodicity' => 'required|in:'.implode(',', array_column(Periodicity::cases(), 'value')),
            'color' => 'required|string|max:7',
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
