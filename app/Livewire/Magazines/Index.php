<?php

namespace App\Livewire\Magazines;

use App\Models\Magazine;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    /**
     * Riviste a cui l'utente collegato ha accesso (tutte, se Admin),
     * con il numero attualmente in lavorazione precaricato.
     */
    public function render(): View
    {
        $user = auth()->user();

        $magazines = $user->isAdmin()
            ? Magazine::query()->orderBy('name')->get()
            : $user->magazines()->orderBy('name')->get();

        return view('livewire.magazines.index', [
            'magazines' => $magazines,
        ]);
    }
}
