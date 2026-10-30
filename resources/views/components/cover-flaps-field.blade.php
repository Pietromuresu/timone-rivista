{{--
    Battenti di copertina (2026-10-30): al posto del numero «Pagine
    copertina» nei default della rivista e nel modulo «Nuovo numero». Le
    facciate per il peso ne derivano (CoverFlaps::coverPages()) e si
    vedono sotto, non si scrivono: con due battenti «6» non direbbe quale.

    `model`: proprietà Livewire con il valore di App\Enums\CoverFlaps;
    `value`: lo stesso valore, per il conteggio sotto.
--}}
@props(['model', 'value', 'modified' => false])

@php($id = str_replace('.', '-', $model))
@php($current = \App\Enums\CoverFlaps::tryFrom((string) $value) ?? \App\Enums\CoverFlaps::None)

<div>
    <div class="flex items-center gap-2">
        <x-input-label :for="$id" value="Battenti di copertina" />
        @if ($modified)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Diverso dal default della rivista">modificato</span>
        @endif
    </div>
    <select id="{{ $id }}" wire:model.live="{{ $model }}" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
        @foreach (\App\Enums\CoverFlaps::cases() as $choice)
            <option value="{{ $choice->value }}">{{ $choice->label() }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400" data-testid="cover-pages-count">Pagine di copertina per il peso: {{ $current->coverPages() }} (4 + 2 per battente)</p>
    <x-input-error class="mt-1" :messages="$errors->get($model)" />
</div>
