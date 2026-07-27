<?php

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Models\Magazine;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'redattore';

    /** @var array<int, int> */
    public array $magazineIds = [];

    public function mount(): void
    {
        $this->authorize('create', User::class);
    }

    public function save()
    {
        $this->authorize('create', User::class);

        $validated = $this->validate([
            'name' => 'required|string|max:180',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:'.implode(',', array_column(UserRole::cases(), 'value')),
        ]);

        $user = User::create($validated);
        $user->magazines()->sync($this->magazineIds);

        return redirect()->route('users.index');
    }

    public function render(): View
    {
        return view('livewire.users.create', [
            'roles' => UserRole::cases(),
            'magazines' => Magazine::orderBy('name')->get(),
        ]);
    }
}
