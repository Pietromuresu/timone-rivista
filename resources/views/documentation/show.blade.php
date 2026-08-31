<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            📖 Documentazione
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                {{-- Stile in resources/css/app.css (.manuale-content, @layer components) —
                     non in uno <style> qui: @apply non viene mai processato dentro un
                     .blade.php, solo nei file che passano dalla pipeline Tailwind/Vite. --}}
                <div class="manuale-content">
                    {!! $contentHtml !!}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
