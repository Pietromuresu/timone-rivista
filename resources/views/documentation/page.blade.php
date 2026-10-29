<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            <a href="{{ route('documentation.show') }}" class="hover:underline">📖 Manuale utente</a>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            @include('documentation.partials.search')

            <div class="manual-layout">
                @include('documentation.partials.nav', ['categories' => $categories, 'current' => $page->slug, 'toc' => $rendered['toc']])

                <div class="manual-main">
                    {{-- Stile in resources/css/app.css (.manuale-content e .manual-*,
                         @layer components) — mai @apply in un .blade.php.
                         {!! !!} è sicuro: ManualRenderer fa l'escape dell'HTML
                         grezzo del markdown e scrive solo l'HTML dei blocchi. --}}
                    <article class="manual-card manuale-content" data-testid="manual-page" data-slug="{{ $page->slug }}">
                        <p class="manual-breadcrumb">{{ $page->categoria }}</p>
                        <h1>{{ $page->titolo }}</h1>
                        {!! $rendered['html'] !!}
                    </article>

                    <nav aria-label="Pagine vicine" class="manual-pager">
                        @if ($previous)
                            <a href="{{ route('documentation.page', $previous->slug) }}" rel="prev">← {{ $previous->titolo }}</a>
                        @else
                            <span></span>
                        @endif
                        @if ($next)
                            <a href="{{ route('documentation.page', $next->slug) }}" rel="next">{{ $next->titolo }} →</a>
                        @endif
                    </nav>
                </div>
            </div>
        </div>
    </div>

    @include('documentation.partials.back-to-top')
</x-app-layout>
