<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        {{-- Font Figtree in locale (2026-10-29): arriva da app.css
             (@fontsource/figtree via Vite), non più da fonts.bunny.net. --}}

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            {{-- Fase 1, bug 1 (2026-10-26): header fisso in alto, `sticky` e non
                 `fixed` (resta nel flusso, niente padding da allineare). --}}
            {{-- 2026-10-28 (azioni sulla selezione nella fascia sticky): UN
                 SOLO contenitore sticky top-0 per header + fascia del timone
                 (riepilogo, comandi, azioni sulla selezione). La fascia sta in
                 Timone\Grid e arriva qui con @teleport('#timone-sticky-slot'):
                 niente più secondo sticky con un `top` calcolato. L'altezza
                 dell'intero contenitore, misurata solo qui, va in
                 --app-sticky-height: la usano scroll-padding-top
                 (resources/css/app.css) e, tramite data-sticky-top, l'auto-scroll
                 del drag (resources/js/drag-autoscroll.js). Sotto i 640 px, se
                 c'è la fascia del timone, il contenitore smette di essere
                 sticky (su un telefono coprirebbe mezzo schermo) e la misura
                 vale 0. z-40: sotto modali, dropdown, popover e toast (z-50). --}}
            <div
                class="sticky top-0 z-40 shadow-sm max-sm:has-[#timone-sticky-slot>*]:static"
                data-sticky-top
                data-testid="app-sticky"
                x-data
                x-init="const set = () => document.documentElement.style.setProperty('--app-sticky-height', (getComputedStyle($el).position === 'sticky' ? $el.offsetHeight : 0) + 'px'); set(); new ResizeObserver(set).observe($el); window.addEventListener('resize', set)"
            >
                @include('layouts.navigation')

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white dark:bg-gray-800 shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                {{-- Destinazione della fascia del timone: vuota (e nascosta) sulle
                     altre pagine. Lo sfondo copre ciò che scorre sotto. --}}
                <div class="hidden has-[#timone-sticky-slot>*]:block bg-gray-100 dark:bg-gray-900 pt-1 pb-1">
                    <div id="timone-sticky-slot" class="max-w-7xl mx-auto sm:px-6 lg:px-8"></div>
                </div>
            </div>

            <!-- Page Content -->
            <main>
                {{-- Revisione 2, Fase 4.2–4.3: esito di un'eliminazione (flash da
                     ConfirmDeletion) e avviso per chi aveva aperto un numero
                     eliminato da un admin (?eliminato=1, vedi timone-alpine.js). --}}
                @if (session('deleted') || request()->query('eliminato'))
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                        <p class="text-sm rounded-lg border px-3 py-2 {{ session('deleted') ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200' }}" data-testid="deletion-notice" role="status">
                            {{ session('deleted') ?? 'Il numero che avevi aperto è stato eliminato da un amministratore.' }}
                        </p>
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>

        {{-- «?» fisso (2026-10-29): apre «Chiedi al manuale» da ogni schermata,
             con il contesto della schermata (App\Support\Manual\ManualContexts).
             In basso a SINISTRA: a destra ci sono già gli avvisi del timone e
             «torna su» del manuale. --}}
        @auth
            @include('documentation.partials.help-button', ['context' => \App\Support\Manual\ManualContexts::forRoute(request()->route()?->getName())])
        @endauth

        {{-- 2026-10-28: Alpine arriva solo con lo script di Livewire, che
             Livewire inserisce da sé SOLO nelle pagine con un suo
             componente: su /profile e /manuale (viste da controller) il
             dropdown della navigazione non si apriva. Esplicito qui, vale per
             ogni pagina del layout; dove Livewire lo inseriva già non viene
             duplicato. --}}
        @livewireScripts
    </body>
</html>
