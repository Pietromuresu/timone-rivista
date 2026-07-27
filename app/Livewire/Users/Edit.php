<?php

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Models\Magazine;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public User $user;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    /**
     * Vuota = non cambiare la password esistente.
     */
    public string $password = '';

    /** @var array<int, int> */
    public array $magazineIds = [];

    public function mount(User $user): void
    {
        $this->authorize('update', $user);

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->magazineIds = $user->magazines()->pluck('magazines.id')->all();
    }

    public function save()
    {
        $this->authorize('update', $this->user);

        $validated = $this->validate([
            'name' => 'required|string|max:180',
            'email' => 'required|email|max:255|unique:users,email,'.$this->user->id,
            'role' => 'required|in:'.implode(',', array_column(UserRole::cases(), 'value')),
            'password' => 'nullable|string|min:8',
        ]);

        $this->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            ...($validated['password'] !== null && $validated['password'] !== '' ? ['password' => $validated['password']] : []),
        ]);

        $this->user->magazines()->sync($this->magazineIds);

        return redirect()->route('users.index');
    }

    public function render(): View
    {
        return view('livewire.users.edit', [
            'roles' => UserRole::cases(),
            'magazines' => Magazine::orderBy('name')->get(),
        ]);
    }
}
