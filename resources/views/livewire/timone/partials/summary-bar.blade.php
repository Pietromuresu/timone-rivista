{{-- Fase 6 (2026-10-27): riepilogo stile dashboard, sempre visibile subito
     sotto l'header fisso. Sta nella fascia del timone (grid.blade.php,
     data-testid="timone-sticky-band"), portata con @teleport nel contenitore
     sticky unico del layout (2026-10-28).
     Numeri da App\Support\IssueSummary ($summary), nessun calcolo qui.
     "Pagine bianche" e "Da assegnare" sono bottoni: evidenziano quelle
     pagine nel timone (Grid::toggleSummaryHighlight()); di nuovo per togliere. --}}
@php
    $chip = 'inline-flex items-baseline gap-1 whitespace-nowrap';
    $toggle = fn (string $what) => $summaryHighlight === $what
        ? 'bg-sky-600 text-white border-sky-700'
        : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 hover:border-sky-500';
@endphp
<div
    class="text-xs text-gray-700 dark:text-gray-200"
    data-testid="summary-bar"
>
    {{-- 2026-10-28: «Comprimi» (bottone ⌃ nella fascia, stato `collapsed`
         della fascia in grid.blade.php) mostra solo questa riga corta. --}}
    <div x-show="collapsed" x-cloak class="truncate" data-testid="summary-compact">
        <strong>{{ $summary['internalPages'] }}</strong> pagine · {{ $summary['fullPages'] }} piene · {{ $summary['blankPages'] }} bianche ·
        libero {{ $summary['freeLabel'] }} · pubblicità <strong>{{ $summary['adPercentage'] }}%</strong> ·
        segnaposto {{ $summary['placeholderCount'] }}
    </div>

    <div x-show="! collapsed" class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
        <span class="{{ $chip }}" title="Pagine interne (le copertine sono a parte)">
            <strong class="text-sm">{{ $summary['internalPages'] }}</strong> pagine interne:
            <span>{{ $summary['fullPages'] }} piene</span> ·
            <span>{{ $summary['partialPages'] }} parziali</span> ·
        </span>

        <button
            type="button"
            wire:click="toggleSummaryHighlight('blank')"
            class="inline-flex items-baseline gap-1 rounded border px-1.5 py-0.5 {{ $toggle('blank') }}"
            title="Evidenzia le pagine bianche nel timone (di nuovo per togliere)"
            aria-pressed="{{ $summaryHighlight === 'blank' ? 'true' : 'false' }}"
            data-testid="summary-blank"
        >○ <strong>{{ $summary['blankPages'] }}</strong> bianche</button>

        <span class="{{ $chip }}" title="Somma dello spazio libero su tutte le pagine interne">
            Spazio libero: <strong>{{ $summary['freeLabel'] }}</strong>
        </span>

        <span class="{{ $chip }}" title="Spazio pubblicitario sulle pagine interne, prenotate comprese, calcolato in dodicesimi di pagina">
            Pubblicità: <strong class="text-sm">{{ $summary['adPercentage'] }}%</strong>
        </span>

        <button
            type="button"
            wire:click="toggleSummaryHighlight('placeholders')"
            class="inline-flex items-baseline gap-1 rounded border border-dashed px-1.5 py-0.5 {{ $toggle('placeholders') }}"
            title="Evidenzia le pagine con pubblicità da assegnare (segnaposto)"
            aria-pressed="{{ $summaryHighlight === 'placeholders' ? 'true' : 'false' }}"
            data-testid="summary-placeholders"
        >
            {{-- 2026-10-28: «Segnaposto», non più «Da assegnare» (scelta dell'utente):
                 «da assegnare» è ora il contatore dei contenuti senza pagina
                 nel bottone «Contenuti» della barra comandi. --}}
            Segnaposto: <strong>{{ $summary['placeholderCount'] }}</strong>
            @if ($summary['placeholderCount'] > 0)
                <span class="opacity-75">({{ collect($summary['placeholderByFormat'])->map(fn ($n, $format) => \App\Enums\AdFormat::from($format)->label().' '.$n)->implode(', ') }})</span>
            @endif
        </button>

        <span class="{{ $chip }}" title="Copertine con almeno un contenuto">
            Copertine: <strong>{{ $summary['coversAssigned'] }}</strong>/{{ $summary['coversTotal'] }} assegnate
            ({{ $summary['coversTotal'] - $summary['coversAssigned'] }} libere)
        </span>

        <span class="{{ $chip }}" title="Dal pannello Peso">
            @if ($summary['weight']['complete'])
                Peso copia: <strong>{{ \App\Support\Weight\WeightFormatter::grams($summary['weight']['copyGrams']) }} g</strong>
                @if ($summary['weight']['outOfBracket'])
                    · <span class="font-medium">fuori scaglione</span>
                @else
                    · scaglione {{ \App\Support\Weight\WeightFormatter::bracket($summary['weight']['bracket']) }}
                @endif
                @if (! $summary['weight']['outOfBracket'] && $summary['weight']['atRisk'])
                    <span class="font-medium">(vicino al limite)</span>
                @endif
            @else
                Peso: <span class="italic opacity-75">parametri incompleti</span>
            @endif
        </span>
    </div>
</div>
