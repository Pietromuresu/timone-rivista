<div id="content-create-panel" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3" wire:key="content-create-form">
    <div class="flex items-center justify-between">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Nuovo contenuto</h4>
        <button
            type="button"
            wire:click="toggleForm"
            @class([
                'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                'bg-indigo-600 text-white border-indigo-600' => $showForm,
                'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $showForm,
            ])
        >
            {{ $showForm ? '✕ Annulla' : '+ Nuovo contenuto' }}
        </button>
    </div>

    {{-- Punto 4 del prompt (2026-09-02): questi due errori restano FUORI
         da "@if ($showForm)" apposta — possono scattare (blockIfLocked()/
         spazio insufficiente sulla pagina di partenza) proprio nello
         stesso metodo che poi chiude il form ($showForm = false a fine
         createContentAndFinish()); dentro il blocco condizionale
         sarebbero scomparsi insieme al form nella stessa risposta,
         invisibili all'utente esattamente quando servono di più. Stesso
         principio già seguito in grid.blade.php per i suoi banner
         d'errore, mai nascosti dietro un pannello che si può richiudere
         nella stessa azione che li genera. --}}
    @error('locked')
        <p class="mt-2 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg px-3 py-1.5">🔒 {{ $message }}</p>
    @enderror

    @error('anchor')
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-1.5">⚠️ {{ $message }}</p>
    @enderror

    @if ($showForm)
        {{-- Promemoria di dove finirà il contenuto, quando il form si apre
             dal bottone "carica materiale" di una card/riga specifica
             (App\Livewire\Timone\ContentCreate::openForPage()) — sostituisce
             l'immediatezza del vecchio upload diretto su pagina, che non
             richiedeva questo tipo di conferma perché il PDF andava
             ovviamente sulla pagina cliccata. --}}
        @if ($anchorPageId)
            <p class="mt-2 text-xs text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 rounded-lg px-3 py-1.5">
                📌 Il materiale caricato qui verrà assegnato subito alla pagina da cui hai aperto questo modulo (o, se il PDF occupa più pagine, a quelle successive — te lo chiederemo prima di scrivere nulla).
            </p>
        @endif

        <form wire:submit="save" class="mt-3 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="type" value="Tipo" />
                    <select id="type" wire:model.live="type" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="articolo">Articolo</option>
                        <option value="pubblicita">Pubblicità</option>
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('type')" />
                </div>

                <div>
                    <x-input-label for="section_id" value="Rubrica (opzionale)" />
                    <div class="flex items-center gap-1 mt-1">
                        <select id="section_id" wire:model="section_id" class="block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            <option value="">— Nessuna —</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}">{{ $section->name }}</option>
                            @endforeach
                        </select>
                        @if ($section_id)
                            <button type="button" wire:click="startEditSection({{ $section_id }})" title="Rinomina rubrica selezionata" class="shrink-0 px-2 py-1.5 text-sm rounded border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">✏️</button>
                        @endif
                        <button type="button" wire:click="startCreateSection" title="Nuova rubrica" class="shrink-0 px-2 py-1.5 text-sm rounded border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">➕</button>
                    </div>
                    <x-input-error class="mt-1" :messages="$errors->get('section_id')" />
                </div>
            </div>

            @if ($showSectionForm)
                <div class="flex items-end gap-2 p-2 rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-900/20">
                    <div class="flex-1">
                        <x-input-label for="sectionName" :value="$editingSectionId ? 'Rinomina rubrica' : 'Nome nuova rubrica'" />
                        <x-text-input id="sectionName" wire:model="sectionName" wire:keydown.enter.prevent="saveSection" type="text" class="mt-1 block w-full text-sm" />
                        <x-input-error class="mt-1" :messages="$errors->get('sectionName')" />
                    </div>
                    <button type="button" wire:click="saveSection" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm">{{ $editingSectionId ? 'Salva' : 'Crea' }}</button>
                    <button type="button" wire:click="cancelSectionForm" class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-sm">Annulla</button>
                </div>
            @endif

            <div>
                <x-input-label for="title" :value="$type === 'articolo' ? 'Titolo articolo' : 'Titolo/descrizione inserzione'" />
                <x-text-input id="title" wire:model="title" type="text" class="mt-1 block w-full text-sm" required />
                <x-input-error class="mt-1" :messages="$errors->get('title')" />
            </div>

            @if ($type === 'articolo')
                {{-- Bug 5 (2026-10-12): occupazione obbligatoria, da una select
                     con le sole frazioni/pagine ammesse (App\Support\PageOccupancy)
                     e nessun valore preselezionato. Se è stato scelto un PDF,
                     il suo numero di pagine viene proposto (updatedFile()) e
                     dichiarato qui sotto come proposta, non applicato in silenzio. --}}
                <div>
                    <x-input-label for="page_occupancy" value="Occupazione di pagina (obbligatoria)" />
                    <select id="page_occupancy" wire:model.live="page_occupancy" class="mt-1 block w-full sm:w-1/2 text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="">— Scegli quanto spazio occupa —</option>
                        @foreach ($occupancyOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($pdfPageCount)
                        <p class="mt-1 text-xs text-indigo-700 dark:text-indigo-300">
                            📄 Il PDF allegato ha {{ $pdfPageCount }} {{ $pdfPageCount === 1 ? 'pagina' : 'pagine' }}:
                            @if ($pdfPageCount > 1)
                                proposta {{ $pdfPageCount }} pagine — controlla e conferma prima di creare.
                            @else
                                proposta 1 pagina — puoi scegliere anche una frazione (es. 1/2 pagina).
                            @endif
                        </p>
                    @endif
                    <x-input-error class="mt-1" :messages="$errors->get('page_occupancy')" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="author" value="Autore (opzionale)" />
                        <x-text-input id="author" wire:model="author" type="text" class="mt-1 block w-full text-sm" />
                    </div>
                    <div>
                        <x-input-label for="editorial_status" value="Stato redazionale" />
                        <select id="editorial_status" wire:model="editorial_status" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach ($editorialStatuses as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="expected_length" value="Lunghezza prevista (opzionale)" />
                        <x-text-input id="expected_length" wire:model="expected_length" type="number" min="1" class="mt-1 block w-full text-sm" />
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="client" value="Cliente" />
                        <x-text-input id="client" wire:model="client" type="text" class="mt-1 block w-full text-sm" required />
                        <x-input-error class="mt-1" :messages="$errors->get('client')" />
                    </div>
                    <div>
                        <x-input-label for="agency" value="Agenzia (opzionale)" />
                        <x-text-input id="agency" wire:model="agency" type="text" class="mt-1 block w-full text-sm" />
                    </div>
                    <div>
                        {{-- Bug 5: per la pubblicità il formato È l'occupazione
                             (App\Support\PageOccupancy::forAdFormat()) — obbligatorio
                             e senza default, come l'occupazione di un articolo. --}}
                        <x-input-label for="format" value="Formato / occupazione (obbligatorio)" />
                        <select id="format" wire:model="format" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            <option value="">— Scegli il formato —</option>
                            @foreach ($adFormats as $adFormat)
                                <option value="{{ $adFormat->value }}">{{ $adFormat->label() }} ({{ $adFormat->defaultPercentage() }}%)</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('format')" />
                    </div>
                    <div>
                        <x-input-label for="occupied_percentage_override" value="Percentuale manuale (opzionale)" />
                        <x-text-input id="occupied_percentage_override" wire:model="occupied_percentage_override" type="number" min="0.1" max="100" step="0.1" placeholder="usa il default del formato" class="mt-1 block w-full text-sm" />
                        <x-input-error class="mt-1" :messages="$errors->get('occupied_percentage_override')" />
                    </div>
                    <div>
                        <x-input-label for="preferred_position" value="Pagina preferita (opzionale)" />
                        <x-text-input id="preferred_position" wire:model="preferred_position" type="number" min="1" placeholder="nessuna preferenza" class="mt-1 block w-full text-sm" />
                        <p class="mt-1 text-xs text-gray-400">Solo indicativo: non assegna il contenuto da solo, ricordalo a chi compone il timone.</p>
                    </div>
                    <div>
                        <x-input-label for="confirmation_status" value="Stato commerciale" />
                        <select id="confirmation_status" wire:model="confirmation_status" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach ($confirmationStatuses as $confStatus)
                                <option value="{{ $confStatus->value }}">{{ $confStatus->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="commercial_notes" value="Note commerciali (opzionale)" />
                        <textarea id="commercial_notes" wire:model="commercial_notes" rows="2" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm"></textarea>
                    </div>
                </div>
            @endif

            {{-- Bug 2 (2026-10-12): drop zone per il file, solo Alpine +
                 l'upload nativo di Livewire, nessuna libreria. Il file
                 trascinato va nella STESSA proprietà `file` dell'input
                 classico ($wire.upload('file', ...)), quindi segue lo stesso
                 identico flusso: validazione FILE_RULES + lettura pagine PDF
                 in updatedFile(), stesso servizio ContentCreator al
                 salvataggio, stessi job miniature. Un click sulla zona apre il
                 selettore classico (l'<input> resta, nascosto). Un solo file
                 per contenuto, come l'input: più file trascinati insieme
                 vengono rifiutati con un messaggio invece di prenderne uno a
                 caso. La validazione tipo/dimensione resta quella server
                 (stesse regole dell'input), il messaggio compare sotto. --}}
            <div
                x-data="{
                    dragging: false,
                    uploading: false,
                    progress: 0,
                    clientError: null,
                    send(files) {
                        this.dragging = false;
                        this.clientError = null;
                        if (! files || files.length === 0) return;
                        if (files.length > 1) {
                            this.clientError = 'Trascina un solo file alla volta: ogni contenuto ha un solo PDF o immagine.';
                            return;
                        }
                        this.uploading = true;
                        this.progress = 0;
                        $wire.upload('file', files[0],
                            () => { this.uploading = false; },
                            () => { this.uploading = false; this.clientError = 'Caricamento non riuscito: controlla che sia un PDF o un\'immagine (JPG, PNG, WebP) di massimo 20 MB.'; },
                            (event) => { this.progress = event.detail.progress; },
                        );
                    },
                }"
                x-on:livewire-upload-start="uploading = true; progress = 0; clientError = null"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-error="uploading = false; clientError = 'Caricamento non riuscito: controlla che sia un PDF o un\'immagine (JPG, PNG, WebP) di massimo 20 MB.'"
            >
                <x-input-label for="file" value="Allega PDF/immagine (opzionale)" />
                <div
                    data-testid="file-drop-zone"
                    role="button"
                    tabindex="0"
                    x-on:click="$refs.fileInput.click()"
                    x-on:keydown.enter.prevent="$refs.fileInput.click()"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="send($event.dataTransfer.files)"
                    :class="dragging
                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30'
                        : 'border-gray-300 dark:border-gray-600 hover:border-indigo-400'"
                    class="mt-1 flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-6 text-center text-sm text-gray-600 dark:text-gray-300 cursor-pointer transition-colors"
                >
                    <span class="text-2xl" aria-hidden="true">📄</span>
                    <span x-show="! dragging"><strong>Trascina qui il PDF o l'immagine</strong> oppure clicca per sceglierlo</span>
                    <span x-show="dragging" x-cloak><strong>Rilascia per caricare</strong></span>
                    <span class="text-xs text-gray-400">PDF, JPG, PNG, WebP — max 20 MB</span>
                    @if ($file)
                        <span class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">✓ {{ $file->getClientOriginalName() }}</span>
                    @endif
                </div>
                <input type="file" id="file" x-ref="fileInput" wire:model="file" accept=".pdf,image/*" class="sr-only" />

                <div x-show="uploading" x-cloak class="mt-2">
                    <div class="h-2 w-full rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div class="h-2 bg-indigo-600 transition-all" :style="`width: ${progress}%`"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500" x-text="`Caricamento… ${progress}%`"></p>
                </div>
                <div wire:loading wire:target="file" class="mt-1 text-xs text-gray-400">Lettura del file in corso…</div>
                <p x-show="clientError" x-cloak x-text="clientError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                <x-input-error class="mt-1" :messages="$errors->get('file')" />
            </div>

            <x-primary-button>Crea contenuto</x-primary-button>
        </form>
    @endif

    {{-- Riepilogo di conflitto per un caricamento PDF multipagina (§2.1),
         spostato qui dal Punto 4 del prompt (2026-09-02): prima viveva in
         grid.blade.php/Grid.php per il caricamento diretto su pagina,
         rimosso — un contenuto è ora l'unico oggetto a cui si può
         allegare un PDF. Testo/scelte INVARIATI rispetto a prima (il
         prompt chiedeva esplicitamente di non toccare questa UI), solo il
         componente che la ospita è cambiato. Nessuna scrittura è ancora
         avvenuta quando questa modale è aperta. --}}
    <x-modal name="multipage-upload-conflict" maxWidth="lg">
        @if ($multipageUploadConflict)
            <div class="p-6 space-y-3">
                <p class="font-medium text-lg text-gray-900 dark:text-gray-100">
                    📄 Il PDF caricato sulla pagina {{ $multipageUploadConflict['initiatingPosition'] }} occupa {{ $multipageUploadConflict['totalPdfPages'] }} pagine.
                </p>

                @if ($multipageUploadConflict['availablePages'] < $multipageUploadConflict['totalPdfPages'])
                    <p class="text-sm text-amber-700 dark:text-amber-400">
                        Il numero finisce prima: solo {{ $multipageUploadConflict['availablePages'] }} pagine del timone sono disponibili da questo punto in poi — le pagine successive del PDF non verranno posizionate.
                    </p>
                @endif

                @if (empty($multipageUploadConflict['conflictingPositions']))
                    <p class="text-sm text-gray-600 dark:text-gray-400">Nessuna delle pagine coinvolte ha già un PDF caricato: puoi procedere senza sovrascrivere nulla.</p>
                    <button type="button" wire:click="confirmMultipageUpload(false)" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                        Occupa le {{ $multipageUploadConflict['totalPdfPages'] }} pagine
                    </button>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ count($multipageUploadConflict['conflictingPositions']) === 1 ? 'La pagina' : 'Le pagine' }}
                        {{ implode(', ', $multipageUploadConflict['conflictingPositions']) }}
                        {{ count($multipageUploadConflict['conflictingPositions']) === 1 ? 'risulta' : 'risultano' }}
                        già occupata/e da un altro PDF.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            wire:click="confirmMultipageUpload(false)"
                            class="px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-300 text-sm hover:bg-amber-100 dark:hover:bg-amber-900/50"
                        >
                            Salta le pagine in conflitto
                        </button>
                        <button
                            type="button"
                            x-on:click="confirm('Sovrascrivere il PDF già presente su {{ implode(', ', $multipageUploadConflict['conflictingPositions']) }}? L\'operazione non è reversibile.') && $wire.confirmMultipageUpload(true)"
                            class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700"
                        >
                            Sovrascrivi le pagine in conflitto
                        </button>
                        <button
                            type="button"
                            wire:click="confirmMultipageUploadWithShift"
                            title="Le pagine in conflitto vengono spostate subito dopo, nulla viene perso — il numero di pagine totali si estende automaticamente se necessario"
                            class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700"
                        >
                            ↪️ Sposta le pagine esistenti dopo
                        </button>
                    </div>
                @endif

                <button type="button" wire:click="cancelMultipageUpload" class="text-xs text-gray-500 dark:text-gray-400 underline">
                    Annulla il caricamento
                </button>
            </div>
        @endif
    </x-modal>
</div>
