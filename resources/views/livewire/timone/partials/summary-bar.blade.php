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
        segnaposto {{ $summary['placeholderCount'] }} ·
        {{ $signatures->summaryLabel() }}@unless ($signatures->isPrintable()) ⚠️@endunless
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

        {{-- Segnature (2026-10-29): App\Support\Signatures\SignaturePlan. --}}
        @php
            $suggestion = $signatures->suggestion();
            $sizeName = \App\Support\Signatures\SignaturePlan::formatName($signatures->size, 2);
        @endphp
        <span
            class="{{ $chip }}"
            title="Segnature da {{ $signatures->size }} pagine{{ $signatures->includeCovers ? ', copertine incluse in testa' : ', copertine escluse (stampate a parte)' }} — impostazione della rivista{{ $suggestion ? '. Per un numero intero di '.$sizeName.': +'.$suggestion['add'].' pagine ('.$suggestion['addTotal'].')'.($suggestion['remove'] !== null ? ' oppure −'.$suggestion['remove'].' ('.$suggestion['removeTotal'].')' : '') : '' }}"
            data-testid="summary-signatures"
        >
            {{-- Interruttore dei confini sulla griglia: preferenza del singolo
                 utente (store timoneView, localStorage), solo CSS, nessuna
                 richiesta. Qui e non nella barra comandi, che a 1366 px
                 andava a capo. --}}
            <button
                type="button"
                x-on:click="$store.timoneView?.toggleSignatures()"
                :aria-pressed="$store.timoneView?.signatures ? 'true' : 'false'"
                :class="$store.timoneView?.signatures ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 hover:border-indigo-500'"
                class="self-center rounded border px-1 leading-4 font-semibold"
                title="Mostra o nascondi sulla griglia i confini dei {{ \App\Support\Signatures\SignaturePlan::formatName($signatures->size, 2) }}"
                data-testid="toggle-signatures"
            >{{ $signatures->size }}°</button>
            Segnature: <strong>{{ $signatures->summaryLabel() }}</strong>
            @if ($signatures->remainder() > 0)
                <span class="opacity-75">({{ $signatures->breakdownLabel() }})</span>
            @endif
            @if (! $signatures->isPrintable())
                <span class="font-semibold text-red-700 dark:text-red-400" data-testid="summary-signatures-warning">⚠️ {{ $signatures->countedPages() }} non è multiplo di 4: non stampabile così</span>
            @endif
            @if ($suggestion)
                <span class="opacity-75" data-testid="summary-signatures-suggestion">→ +{{ $suggestion['add'] }} = {{ $suggestion['addTotal'] }}{{ $suggestion['remove'] !== null ? ' · −'.$suggestion['remove'].' = '.$suggestion['removeTotal'] : '' }}</span>
            @endif
        </span>

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
