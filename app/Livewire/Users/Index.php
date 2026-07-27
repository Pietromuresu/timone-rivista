<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function render(): View
    {
        $users = User::withCount('magazines')->orderBy('name')->get();

        return view('livewire.users.index', [
            'users' => $users,
        ]);
    }
}
