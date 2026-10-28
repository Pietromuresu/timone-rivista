{{-- Revisione 2, Fase 4.2–4.3: bottone «Elimina» e conferma in due passaggi
     (App\Livewire\Admin\ConfirmDeletion). Chi non può eliminare vede il
     bottone disattivato con il motivo (es. «È l'ultimo amministratore»);
     chi non è admin non lo vede affatto. --}}
<span data-testid="confirm-deletion-{{ $kind }}-{{ $targetId }}">
    @if ($allowed)
        <button type="button" wire:click="start" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-red-300 dark:border-red-800 text-sm text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">
            🗑️ Elimina
        </button>
    @elseif (auth()->user()?->isAdmin())
        <button type="button" disabled title="{{ $deniedReason }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-400 cursor-not-allowed">
            🗑️ Elimina
        </button>
    @endif

    @if ($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" x-data x-on:keydown.escape.window="$wire.cancel()" role="dialog" aria-modal="true" aria-labelledby="deletion-title-{{ $kind }}-{{ $targetId }}">
            <div class="w-full max-w-lg rounded-lg bg-white dark:bg-gray-800 shadow-xl p-5 space-y-4 text-left">
                <h3 id="deletion-title-{{ $kind }}-{{ $targetId }}" class="text-base font-semibold text-red-700 dark:text-red-400">
                    Eliminare {{ $target->label() }}?
                </h3>

                @if ($step === 1)
                    <div class="text-sm text-gray-700 dark:text-gray-200 space-y-2" data-testid="deletion-summary">
                        <p>Verrà eliminato <strong>definitivamente</strong>, senza cestino:</p>
                        <ul class="list-disc pl-5">
                            @foreach ($summary->lines as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                        @if ($summary->items !== [])
                            <p class="font-medium">{{ $summary->itemsTitle }}:</p>
                            <ul class="list-disc pl-5 max-h-40 overflow-y-auto">
                                @foreach ($summary->items as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancel" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm">Annulla</button>
                        <button type="button" wire:click="next" class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700">Continua</button>
                    </div>
                @else
                    <form wire:submit="confirm" class="space-y-3">
                        <div>
                            <x-input-label for="deletion-typed-{{ $kind }}-{{ $targetId }}">
                                Per confermare scrivi <strong class="select-all">{{ $target->confirmationText() }}</strong>
                            </x-input-label>
                            <x-text-input id="deletion-typed-{{ $kind }}-{{ $targetId }}" wire:model="typed" type="text" autocomplete="off" class="mt-1 block w-full text-sm" />
                            <x-input-error class="mt-1" :messages="$errors->get('typed')" />
                        </div>
                        <div>
                            <x-input-label for="deletion-password-{{ $kind }}-{{ $targetId }}" value="La tua password" />
                            <x-text-input id="deletion-password-{{ $kind }}-{{ $targetId }}" wire:model="password" type="password" autocomplete="current-password" class="mt-1 block w-full text-sm" />
                            <x-input-error class="mt-1" :messages="$errors->get('password')" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="cancel" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm">Annulla</button>
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700" wire:loading.attr="disabled">Elimina definitivamente</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</span>
