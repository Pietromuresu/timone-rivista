<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            {{-- Fase 1, bug 1 (2026-10-26): header fisso in alto. `sticky`
                 e non `fixed`: resta nel flusso, quindi il contenuto non va
                 compensato con un padding da tenere allineato all'altezza
                 (che cambia col menu mobile aperto). z-40: sopra le pagine
                 del timone (badge z-10, menu export z-10), sotto modali,
                 dropdown e toast (z-50). Il drag&drop nativo disegna il
                 fantasma sopra tutto. data-sticky-top: l'auto-scroll
                 durante il trascinamento parte dal fondo della fascia
                 sticky, non dal bordo della finestra coperto dall'header
                 (resources/js/drag-autoscroll.js, revisione 2, Fase 1, bug 4). --}}
            {{-- Fase 6 (2026-10-27): l'altezza reale dell'header (cambia col
                 menu mobile) va in --app-header-height, così la barra di
                 riepilogo del timone (sticky) si ferma subito sotto. --}}
            <div
                class="sticky top-0 z-40 shadow-sm"
                data-sticky-top
                x-data
                x-init="const set = () => document.documentElement.style.setProperty('--app-header-height', $el.offsetHeight + 'px'); set(); new ResizeObserver(set).observe($el)"
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
    </body>
</html>
