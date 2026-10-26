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
                 fantasma sopra tutto; l'auto-scroll di Sortable parte già
                 a 120px dal bordo (resources/js/timone-alpine.js), quindi
                 anche sotto l'header. --}}
            <div class="sticky top-0 z-40 shadow-sm">
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
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
