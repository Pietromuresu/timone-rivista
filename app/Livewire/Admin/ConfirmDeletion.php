<?php

namespace App\Livewire\Admin;

use App\Support\Deletion\DeletionConfirmation;
use App\Support\Deletion\DeletionTarget;
use App\Support\Deletion\DeletionTargets;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Bottone «Elimina» + conferma in due passaggi (revisione 2, Fase 4.2–4.3,
 * 2026-10-28), lo stesso per rivista, numero e utente:
 * 1) riepilogo di cosa verrà eliminato (DeletionTarget::summary());
 * 2) password dell'admin + testo da digitare (DeletionConfirmation).
 * La policy `delete` si controlla all'apertura E alla conferma (lato
 * server: il bottone nascosto non basta). Componente sottile: cosa si
 * elimina e come lo decidono le classi App\Support\Deletion.
 */
class ConfirmDeletion extends Component
{
    #[Locked]
    public string $kind;

    #[Locked]
    public int $targetId;

    public bool $open = false;

    public int $step = 1;

    public string $password = '';

    public string $typed = '';

    public function mount(string $kind, int $targetId): void
    {
        abort_unless(in_array($kind, DeletionTargets::KINDS, true), 404);

        $this->kind = $kind;
        $this->targetId = $targetId;
    }

    public function start(): void
    {
        $this->authorize('delete', $this->target()->model());

        $this->reset('password', 'typed');
        $this->resetErrorBag();
        $this->step = 1;
        $this->open = true;
    }

    public function next(): void
    {
        $this->authorize('delete', $this->target()->model());

        $this->step = 2;
    }

    public function cancel(): void
    {
        $this->reset('open', 'step', 'password', 'typed');
        $this->resetErrorBag();
    }

    public function confirm(): void
    {
        $target = $this->target();
        $this->authorize('delete', $target->model());

        DeletionConfirmation::verify(auth()->user(), $this->password, $this->typed, $target);

        $label = $target->label();
        $redirect = $target->redirectUrl();
        $target->delete(auth()->user());

        session()->flash('deleted', 'Eliminato: '.$label.'.');
        // Niente render dopo: il target non esiste più.
        $this->skipRender();
        $this->redirect($redirect);
    }

    private function target(): DeletionTarget
    {
        return DeletionTargets::resolve($this->kind, $this->targetId);
    }

    public function render(): View
    {
        $target = $this->target();
        $decision = Gate::inspect('delete', $target->model());

        return view('livewire.admin.confirm-deletion', [
            'target' => $target,
            'allowed' => $decision->allowed(),
            'deniedReason' => $decision->message(),
            // Riepilogo solo a modale aperta: sono query di conteggio.
            'summary' => $this->open ? $target->summary() : null,
        ]);
    }
}
