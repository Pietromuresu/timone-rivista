{{--
    Contenitore di un campo con anteprima live (revisione 2, Fase 1, bug 1,
    2026-10-27). Gli avvisi che dipendono dal valore («non è multiplo di 4»,
    «non ci stanno», grammatura fuori scala) non devono comparire mentre si
    scrive, quando il valore è per forza a metà ("3" prima di "32"): dentro
    questo contenitore un avviso con `x-show="! editing"` resta nascosto
    finché il focus è in uno dei suoi campi e compare all'uscita (blur).
    Gli errori di validazione veri arrivano solo dal salvataggio (submit).
    Lo stato è di Alpine sul contenitore: il morph di Livewire lo conserva
    tra un render e l'altro, quindi un avviso inserito dal server durante
    la digitazione nasce già nascosto.
--}}
<div x-data="{ editing: false }" x-on:focusin="editing = true" x-on:focusout="editing = false" {{ $attributes }}>
    {{ $slot }}
</div>
