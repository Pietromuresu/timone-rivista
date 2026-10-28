{{-- Fase 4 (2026-10-27): modale di modifica di un contenuto
     (App\Livewire\Timone\ContentEdit). Stato aperto/chiuso sul server
     ($showModal), non nel componente <x-modal> di Breeze: si apre da un
     evento con l'id del contenuto e deve caricarne i campi prima di
     mostrarsi. z-50 come le altre modali, sopra l'header fisso (z-40). --}}
<div>
    @if ($showModal && $content)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4" x-on:keydown.escape.window="$wire.close()" data-testid="content-edit-modal">
            <div class="w-full max-w-2xl mt-8 rounded-lg bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700" x-on:click.outside="$wire.close()">
                <form wire:submit="save" class="p-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100">Modifica contenuto</h3>
                        <button type="button" wire:click="close" class="text-gray-400 hover:text-gray-600" title="Chiudi">✕</button>
                    </div>

                    @if ($content->pages->isNotEmpty())
                        <p class="text-xs text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 rounded px-2 py-1">
                            Il contenuto è su {{ $content->pages->count() === 1 ? '1 pagina' : $content->pages->count().' pagine' }}: le modifiche valgono ovunque compaia. Cambiando l'occupazione viene ridistribuito a partire dalla prima pagina.
                        </p>
                    @endif

                    @if ($content->isPlaceholder())
                        <p class="text-xs text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-900/30 border border-dashed border-amber-400 rounded px-2 py-1" data-testid="placeholder-note">
                            Pubblicità <strong>da assegnare</strong> (segnaposto): indicando il cliente o caricando il file diventa una pubblicità vera (il titolo da solo non basta).
                        </p>
                    @endif

                    <div class="flex gap-4 text-sm">
                        {{-- Revisione 2, Fase 4.1: il Commerciale non trasforma una pubblicità in articolo. --}}
                        <label class="inline-flex items-center gap-1 {{ $editsTimone ? '' : 'opacity-50 cursor-not-allowed' }}" @unless ($editsTimone) title="Solo la redazione può creare articoli" @endunless><input type="radio" wire:model.live="type" value="articolo" @disabled(! $editsTimone) /> Articolo</label>
                        <label class="inline-flex items-center gap-1"><input type="radio" wire:model.live="type" value="pubblicita" /> Pubblicità</label>
                    </div>

                    <div>
                        <x-input-label for="edit_title" :value="$isAd ? 'Titolo/descrizione inserzione (opzionale)' : 'Titolo articolo'" />
                        <x-text-input id="edit_title" wire:model="title" type="text" class="mt-1 block w-full text-sm" />
                        <x-input-error class="mt-1" :messages="$errors->get('title')" />
                    </div>

                    <div>
                        <x-input-label for="edit_section" value="Rubrica (opzionale)" />
                        <select id="edit_section" wire:model="section_id" class="mt-1 block w-full sm:w-1/2 text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            <option value="">— Nessuna —</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}">{{ $section->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($isAd)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="edit_client" value="Cliente (opzionale)" />
                                <x-text-input id="edit_client" wire:model="client" type="text" class="mt-1 block w-full text-sm" />
                                <x-input-error class="mt-1" :messages="$errors->get('client')" />
                            </div>
                            <div>
                                <x-input-label for="edit_agency" value="Agenzia (opzionale)" />
                                <x-text-input id="edit_agency" wire:model="agency" type="text" class="mt-1 block w-full text-sm" />
                            </div>
                            <div>
                                <x-input-label for="edit_format" value="Formato" />
                                <select id="edit_format" wire:model.live="format" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    <option value="">— Scegli il formato —</option>
                                    @foreach ($adFormats as $adFormat)
                                        <option value="{{ $adFormat->value }}">{{ $adFormat->label() }} ({{ \App\Support\Twelfths::pagesLabel($adFormat->twelfths()) }})</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-1" :messages="$errors->get('format')" />
                            </div>
                            <div>
                                <x-input-label for="edit_confirmation" value="Stato commerciale" />
                                <select id="edit_confirmation" wire:model="confirmation_status" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    @foreach ($confirmationStatuses as $confStatus)
                                        <option value="{{ $confStatus->value }}">{{ $confStatus->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="edit_preferred" value="Pagina preferita (opzionale)" />
                                <x-text-input id="edit_preferred" wire:model="preferred_position" type="number" min="1" class="mt-1 block w-full text-sm" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="edit_notes" value="Note commerciali (opzionale)" />
                                <textarea id="edit_notes" wire:model="commercial_notes" rows="2" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm"></textarea>
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="edit_author" value="Autore (opzionale)" />
                                <x-text-input id="edit_author" wire:model="author" type="text" class="mt-1 block w-full text-sm" />
                            </div>
                            <div>
                                <x-input-label for="edit_status" value="Stato redazionale" />
                                <select id="edit_status" wire:model="editorial_status" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    @foreach ($editorialStatuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="edit_length" value="Lunghezza prevista (opzionale)" />
                                <x-text-input id="edit_length" wire:model="expected_length" type="number" min="1" class="mt-1 block w-full text-sm" />
                            </div>
                        </div>
                    @endif

                    @include('livewire.timone.partials.occupancy-fields', [
                        'hint' => $isAd ? 'Cambiando formato viene riproposto lo spazio del formato; puoi correggerlo.' : null,
                    ])

                    @include('livewire.timone.partials.placement-field', ['placementId' => 'edit_placement'])

                    {{-- Revisione 2, Fase 1, bug 5 (2026-10-27): barra di avanzamento e
                         controllo del limite prima dell'invio, stessa logica del modulo
                         di creazione (Alpine.data('fileUpload')). --}}
                    <div
                        x-data="fileUpload({ maxBytes: @js($uploadLimit['bytes']), maxLabel: @js($uploadLimit['bytes'] ? \App\Support\Uploads\UploadLimits::humanMegabytes($uploadLimit['bytes']) : null) })"
                        x-on:change.capture="checkPicked($event)"
                        x-on:livewire-upload-start="uploading = true; progress = 0; clientError = null"
                        x-on:livewire-upload-progress="progress = $event.detail.progress"
                        x-on:livewire-upload-finish="uploading = false"
                        x-on:livewire-upload-error="uploading = false"
                    >
                        <x-input-label for="edit_file" value="Materiale di stampa (PDF o immagine)" />
                        @php $currentFiles = $content->files->sortBy('pdf_page_number'); @endphp
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" data-testid="current-material">
                            @if ($currentFiles->isEmpty())
                                Nessun file allegato.
                            @else
                                Attuale: {{ $currentFiles->first()->original_name }}
                                @if ($currentFiles->count() > 1) ({{ $currentFiles->count() }} pagine) @endif
                            @endif
                        </p>
                        <input id="edit_file" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" wire:model="file" class="mt-1 block w-full text-xs text-gray-700 dark:text-gray-300" />
                        <p class="mt-1 text-xs text-gray-400">Scegliendo un file sostituisci quello attuale. Un PDF di più pagine deve attraversare lo stesso numero di pagine dell'occupazione.{{ $uploadLimit['bytes'] ? ' Massimo '.\App\Support\Uploads\UploadLimits::humanMegabytes($uploadLimit['bytes']).'.' : '' }}</p>
                        <div x-show="uploading" x-cloak class="mt-2" data-testid="upload-progress">
                            <div class="h-2 w-full rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-2 bg-indigo-600 transition-all" :style="`width: ${progress}%`"></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500" x-text="`Caricamento… ${progress}%`"></p>
                        </div>
                        <p x-show="clientError" x-cloak x-text="clientError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                        {{-- Revisione 2, Fase 2.4: come mostrare il file nel suo riquadro
                             sulla pagina. Automatico = dalle proporzioni della prima
                             pagina del file (tolleranza 5%). --}}
                        @if ($currentFiles->isNotEmpty())
                            <div class="mt-3">
                                <x-input-label for="edit_file_fit" value="Anteprima nel riquadro" class="text-xs" />
                                <select id="edit_file_fit" wire:model="file_fit" class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-gray-700" data-testid="file-fit">
                                    <option value="">Automatico{{ $detectedFit ? ' (rilevato: '.$detectedFit->shortLabel().')' : '' }}</option>
                                    @foreach ($fileFits as $fit)
                                        <option value="{{ $fit->value }}">{{ $fit->label() }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-400">A pagina intera: il PDF è impaginato a pagina piena con l'annuncio in una zona, si vede solo la parte del riquadro. Nel formato: il file è già della misura dell'annuncio e si vede intero.</p>
                            </div>
                        @endif
                        <x-input-error class="mt-1" :messages="$errors->get('file')" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" wire:click="close" class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-sm">Annulla</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,file" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm disabled:opacity-50">Salva modifiche</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
