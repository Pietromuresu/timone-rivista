{{--
    Riepilogo del peso di una copia (MagazineWeightResult): peso ± margine,
    intervallo min–max, badge scaglione, oppure l'elenco di cosa manca/non
    va. Usato dall'anteprima in creazione numero e dal pannello Peso.

    Badge: verde = dentro lo scaglione anche nel caso peggiore; giallo =
    a rischio (il peso massimo cade nello scaglione successivo); rosso =
    fuori scaglione (oltre l'ultimo limite di config/peso_rivista.php).
--}}
@props(['result'])

@use('App\Support\Weight\WeightFormatter', 'F')
@use('App\Support\Weight\WeightParameters')

@if (! $result->complete)
    @if ($result->missing !== [])
        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30 px-3 py-2 text-sm text-amber-800 dark:text-amber-300">
            <p class="font-medium">Parametri peso da configurare</p>
            <p class="text-xs mt-1">
                Mancano:
                {{ collect($result->missing)->map(fn ($field) => WeightParameters::LABELS[$field] ?? $field)->implode(', ') }}.
            </p>
        </div>
    @endif
    @foreach ($result->errors as $error)
        <p class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/30 px-3 py-2 text-sm text-red-700 dark:text-red-300">⛔ {{ $error }}</p>
    @endforeach
@else
    <div class="space-y-1 text-sm">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <span class="text-gray-500 dark:text-gray-400 w-28">Peso copia</span>
            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ F::grams($result->pesoCopiaG) }} g</span>
            <span class="text-gray-700 dark:text-gray-300" title="Margine d'errore nel caso peggiore, arrotondato per eccesso">± {{ $result->margineG }} g</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">({{ F::grams($result->pesoMinG, 0) }} – {{ F::grams($result->pesoMaxG, 0) }} g)</span>
        </div>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="text-gray-500 dark:text-gray-400 w-28">Scaglione</span>
            <span @class([
                'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold',
                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $result->fuoriScaglione,
                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => ! $result->fuoriScaglione && $result->aRischio,
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => ! $result->fuoriScaglione && ! $result->aRischio,
            ])>{{ F::bracket($result->scaglione) }}</span>
            @if ($result->scaglionePrecedente !== null && $result->grammiOltreScaglionePrecedente !== null)
                <span class="text-xs text-gray-600 dark:text-gray-400">⚠ a {{ F::grams($result->grammiOltreScaglionePrecedente) }} g oltre lo scaglione {{ F::bracket($result->scaglionePrecedente) }}</span>
            @endif
        </div>
        @if ($result->aRischio)
            <p class="text-xs font-medium text-amber-700 dark:text-amber-400">
                Rischio di passare allo scaglione {{ F::bracket($result->scaglionePeggiore) }}: mancano {{ F::grams($result->grammiAlLimite) }} g al limite, il margine è ± {{ $result->margineG }} g.
            </p>
        @endif
    </div>
@endif

@foreach ($result->warnings as $warning)
    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">⚠️ {{ $warning }}</p>
@endforeach
