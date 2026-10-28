<?php

namespace App\Support\Deletion;

use App\Models\User;

/**
 * Qualcosa che un admin può eliminare con la conferma in due passaggi
 * (revisione 2, Fase 4.2–4.3, 2026-10-28): rivista, numero, utente. Il
 * componente App\Livewire\Admin\ConfirmDeletion parla solo con questa
 * interfaccia, così un nuovo tipo eliminabile è una nuova implementazione
 * (Open/Closed) e non un altro ramo nel componente.
 */
interface DeletionTarget
{
    /** Il model, per la policy `delete`. */
    public function model(): object;

    /** «il numero «Ottobre 2026»» — usato nei titoli e nei messaggi. */
    public function label(): string;

    /** Testo da digitare per confermare (come su GitHub). */
    public function confirmationText(): string;

    /** Riepilogo di cosa verrà eliminato (passaggio 1). */
    public function summary(): DeletionSummary;

    /** Elimina (in transazione) e registra chi l'ha fatto. */
    public function delete(User $by): void;

    /** Dove portare l'admin dopo l'eliminazione. */
    public function redirectUrl(): string;
}
