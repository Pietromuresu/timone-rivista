{{-- Revisione 2, Fase 3.2 (2026-10-28): caricamento massivo. Tutto lo stato
     (pannello aperto, tipo del lotto, coda dei file) è di Alpine
     (Alpine.data('bulkUpload') in resources/js/timone-alpine.js): il
     componente Livewire non ridisegna nulla di suo, così le risposte di
     upload/import non toccano la lista mentre la coda avanza. --}}
<div
    class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3"
    data-testid="bulk-upload"
    x-data="bulkUpload({ maxBytes: @js($uploadLimit['bytes']), maxLabel: @js($uploadLimit['bytes'] ? \App\Support\Uploads\UploadLimits::humanMegabytes($uploadLimit['bytes']) : null), type: @js($onlyAds ? 'pubblicita' : 'articolo') })"
>
    <div class="flex items-center justify-between gap-2">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Caricamento multiplo</h4>
        <button
            type="button"
            x-on:click="open = ! open"
            :class="open ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'"
            class="px-3 py-1.5 rounded-lg border text-sm transition-colors"
            x-text="open ? '✕ Chiudi' : '⇪ Carica più file'"
        ></button>
    </div>

    <div x-show="open" x-cloak class="mt-3 space-y-3">
        <fieldset class="flex flex-wrap items-center gap-4 text-sm" :disabled="busy">
            <legend class="sr-only">Tipo di contenuto per tutto il lotto</legend>
            <span class="text-gray-600 dark:text-gray-300">Crea come:</span>
            @unless ($onlyAds)
                <label class="inline-flex items-center gap-1"><input type="radio" x-model="type" value="articolo" /> Articoli</label>
            @endunless
            <label class="inline-flex items-center gap-1"><input type="radio" x-model="type" value="pubblicita" /> Pubblicità</label>
        </fieldset>

        <div
            data-testid="bulk-drop-zone"
            role="button"
            tabindex="0"
            x-on:click="$refs.bulkInput.click()"
            x-on:keydown.enter.prevent="$refs.bulkInput.click()"
            x-on:dragover.prevent="dragging = true"
            x-on:dragleave.prevent="dragging = false"
            x-on:drop.prevent="add($event.dataTransfer.files)"
            :class="dragging ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30' : 'border-gray-300 dark:border-gray-600 hover:border-indigo-400'"
            class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-6 text-center text-sm text-gray-600 dark:text-gray-300 cursor-pointer transition-colors"
        >
            <span class="text-2xl" aria-hidden="true">🗂️</span>
            <span x-show="! dragging"><strong>Trascina qui più file</strong> oppure clicca per sceglierli</span>
            <span x-show="dragging" x-cloak><strong>Rilascia per caricare</strong></span>
            <span class="text-xs text-gray-400">PDF, JPG, PNG, WebP{{ $uploadLimit['bytes'] ? ' — max '.\App\Support\Uploads\UploadLimits::humanMegabytes($uploadLimit['bytes']).' per file' : '' }}. Un contenuto per file, tra i «Contenuti da assegnare»: titolo dal nome del file, occupazione = pagine del PDF (1 per un'immagine).</span>
        </div>
        <input type="file" x-ref="bulkInput" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only" x-on:change="pick($event)" />

        <template x-if="items.length > 0">
            <div class="space-y-2">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm" data-testid="bulk-file-list">
                    <template x-for="item in items" :key="item.id">
                        <li class="py-1.5 flex flex-col gap-0.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate" x-text="item.name"></span>
                                <span class="shrink-0 text-xs" :class="statusClass(item)" x-text="statusLabel(item)"></span>
                            </div>
                            <div x-show="item.status === 'uploading'" class="h-1.5 w-full rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-1.5 bg-indigo-600 transition-all" :style="`width: ${item.progress}%`"></div>
                            </div>
                            <p x-show="item.message" x-text="item.message" class="text-xs" :class="item.status === 'error' ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400'"></p>
                        </li>
                    </template>
                </ul>

                <div x-show="! busy" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 dark:bg-gray-900/40 px-3 py-2 text-sm" data-testid="bulk-summary">
                    <span x-text="summary"></span>
                    <span class="flex gap-2">
                        <button type="button" x-show="failedCount > 0" x-on:click="retryFailed()" class="px-2 py-1 rounded border border-gray-200 dark:border-gray-700 hover:bg-white dark:hover:bg-gray-700">↻ Riprova i falliti</button>
                        <button type="button" x-on:click="clear()" class="px-2 py-1 rounded border border-gray-200 dark:border-gray-700 hover:bg-white dark:hover:bg-gray-700">Svuota elenco</button>
                    </span>
                </div>
            </div>
        </template>
    </div>
</div>
