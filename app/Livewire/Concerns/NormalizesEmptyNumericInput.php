<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;
use ReflectionProperty;

/**
 * Fase 1, bug 2 (2026-10-26): un input numerico svuotato invia '' — su una
 * proprietà `int`/`?int` Livewire 3 non riesce ad assegnarla, intercetta il
 * TypeError e fa `unset()` della proprietà (HandleComponents::
 * setComponentPropertyAwareOfTypes()). Una proprietà "unset" non è null: è
 * NON inizializzata. Nella stessa richiesta ogni lettura esplode
 * (PropertyNotFoundException); nella richiesta successiva, non essendo
 * nello snapshot, ricompare col valore di DEFAULT della classe — quindi un
 * campo "Pagine" svuotato tornava silenziosamente a 32 e la validazione
 * `required` non scattava mai (scoperto scrivendo il test di regressione,
 * vedi tests/Feature/Livewire/EmptyNumericInputTest.php).
 *
 * Questo trait riporta a null ogni proprietà nullable rimasta non
 * inizializzata dopo un aggiornamento. Usa l'hook di trait
 * `updated{NomeTrait}`, che Livewire chiama PRIMA degli hook specifici
 * `updated{Proprietà}` del componente (SupportLifecycleHooks): quando un
 * `updatedTotalPages()` gira, la proprietà è già null e leggibile.
 *
 * Attenzione al default di classe: in idratazione Livewire non riassegna
 * un null dello snapshot a una proprietà tipizzata (lascia il default).
 * Una proprietà che deve poter tornare null va quindi dichiarata `= null`
 * e il suo valore iniziale impostato in mount().
 *
 * Per un input numerico LIVE (wire:model.live) una proprietà intera non va
 * bene comunque: il server rimanda il valore convertito ("12" → 12),
 * Livewire lo prende per una modifica del server e riscrive il campo
 * mentre l'utente digita (revisione 2, Fase 1, bug 1, 2026-10-27). Lì si
 * usa una proprietà `string` + parsing — vedi Issues\Create::$total_pages.
 *
 * Non copre valori non numerici ("abc"): Livewire in quel caso rilancia il
 * TypeError. Un `<input type="number">` non li invia mai (il browser manda
 * '' per un contenuto non valido) e le select inviano solo id — per un
 * campo di testo libero il progetto usa già proprietà `string` + parsing
 * (es. Grid::$newTotalPages).
 */
trait NormalizesEmptyNumericInput
{
    public function updatedNormalizesEmptyNumericInput(string $fullPath): void
    {
        $property = Str::before($fullPath, '.');

        if (! property_exists($this, $property)) {
            return;
        }

        $reflection = new ReflectionProperty($this, $property);

        if (! $reflection->isInitialized($this) && $reflection->getType()?->allowsNull()) {
            $this->{$property} = null;
        }
    }
}
