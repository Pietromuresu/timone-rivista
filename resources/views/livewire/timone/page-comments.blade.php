{{-- Pannello commenti (2026-10-29, App\Livewire\Timone\PageComments):
     cassetto a destra, sopra il timone. role="dialog" + aria-modal: con il
     pannello aperto Ctrl+Z del timone non scatta (isModalOpen() in
     grid.blade.php), e nel campo di testo resta l'annulla del browser. --}}
<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex justify-end bg-gray-900/40" x-on:keydown.escape.window="$wire.close()" x-on:click.self="$wire.close()" role="dialog" aria-modal="true" aria-labelledby="page-comments-title" data-testid="page-comments-panel">
            <div class="h-full w-full max-w-md bg-white dark:bg-gray-800 shadow-xl flex flex-col">
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-700 px-4 py-3">
                    <h2 id="page-comments-title" class="font-semibold text-gray-800 dark:text-gray-100">
                        @if ($mode === 'page' && $page)
                            💬 Commenti — {{ \Illuminate\Support\Str::ucfirst($page->label()) }}
                        @else
                            💬 Commenti del numero
                        @endif
                    </h2>
                    <button type="button" wire:click="close" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Chiudi (Esc)">✕</button>
                </div>

                @if ($mode === 'issue')
                    <div class="flex items-center gap-1 px-4 pt-3 text-sm">
                        <button type="button" wire:click="$set('tab', 'unread')" @class(['px-2 py-1 rounded', 'bg-indigo-600 text-white' => $tab === 'unread', 'text-gray-600 dark:text-gray-300' => $tab !== 'unread']) data-testid="comments-tab-unread">Non letti</button>
                        <button type="button" wire:click="$set('tab', 'all')" @class(['px-2 py-1 rounded', 'bg-indigo-600 text-white' => $tab === 'all', 'text-gray-600 dark:text-gray-300' => $tab !== 'all']) data-testid="comments-tab-all">Tutti</button>
                        @if ($tab === 'unread' && $issueComments->isNotEmpty())
                            <button type="button" wire:click="markAllRead" class="ml-auto text-xs text-indigo-700 dark:text-indigo-300 hover:underline">Segna tutti come letti</button>
                        @endif
                    </div>

                    <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2" data-testid="issue-comments-list">
                        @forelse ($issueComments as $comment)
                            <div wire:key="issue-comment-{{ $comment->id }}" class="rounded border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm">
                                <div class="flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($comment->page)
                                        <button type="button" wire:click="goToPage({{ $comment->page_id }})" class="font-semibold text-indigo-700 dark:text-indigo-300 hover:underline" title="Apri i commenti della pagina e mostrala nel timone">{{ \Illuminate\Support\Str::ucfirst($comment->pageName()) }} →</button>
                                    @else
                                        <span class="font-semibold text-gray-500 dark:text-gray-400" title="La pagina è stata eliminata riducendo il numero di pagine: il commento resta qui">{{ \Illuminate\Support\Str::ucfirst($comment->pageName()) }}</span>
                                    @endif
                                    <span>{{ $comment->authorName() }} · {{ $comment->created_at->translatedFormat('d M H:i') }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-line break-words text-gray-800 dark:text-gray-100">{{ $comment->body }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">{{ $tab === 'unread' ? 'Nessun commento da leggere.' : 'Nessun commento su questo numero.' }}</p>
                        @endforelse
                    </div>
                @elseif ($page)
                    <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2" data-testid="page-comments-list">
                        @forelse ($comments as $comment)
                            <div wire:key="page-comment-{{ $comment->id }}" class="rounded border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm" data-testid="page-comment">
                                <div class="flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $comment->authorName() }}</span>
                                    <span>
                                        {{ $comment->created_at->translatedFormat('d M Y H:i') }}
                                        @if ($comment->edited_at) · <span title="Modificato il {{ $comment->edited_at->translatedFormat('d M Y H:i') }}">modificato</span> @endif
                                    </span>
                                </div>

                                @if ($editingId === $comment->id)
                                    <form wire:submit="saveEdit" class="mt-1 space-y-1">
                                        <textarea wire:model="editingBody" rows="3" maxlength="{{ \App\Livewire\Timone\PageComments::MAX_LENGTH }}" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm"></textarea>
                                        @error('editingBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        <div class="flex gap-2 text-xs">
                                            <button type="submit" class="px-2 py-1 rounded bg-indigo-600 text-white">Salva</button>
                                            <button type="button" wire:click="cancelEdit" class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600">Annulla</button>
                                        </div>
                                    </form>
                                @else
                                    <p class="mt-1 whitespace-pre-line break-words text-gray-800 dark:text-gray-100">{{ $comment->body }}</p>
                                    <div class="mt-1 flex gap-3 text-xs">
                                        @can('update', $comment)
                                            <button type="button" wire:click="startEdit({{ $comment->id }})" class="text-indigo-700 dark:text-indigo-300 hover:underline">Modifica</button>
                                        @endcan
                                        @can('delete', $comment)
                                            <button type="button" wire:click="deleteComment({{ $comment->id }})" wire:confirm="Eliminare il commento?" class="text-red-600 hover:underline">Elimina</button>
                                        @endcan
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Nessun commento su questa pagina.</p>
                        @endforelse
                    </div>

                    @if ($canComment)
                        <form wire:submit="post" class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 space-y-2" data-testid="page-comment-form">
                            @if ($notice)
                                <p class="text-xs text-green-700 dark:text-green-400" data-testid="page-comment-notice">{{ $notice }}</p>
                            @endif
                            <textarea wire:model="body" rows="3" maxlength="{{ \App\Livewire\Timone\PageComments::MAX_LENGTH }}" placeholder="Scrivi un commento… (@Nome Cognome avvisa quella persona)" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm" data-testid="page-comment-body"></textarea>
                            @error('body') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <details class="text-xs text-gray-600 dark:text-gray-300">
                                <summary class="cursor-pointer select-none">Avvisa via mail…</summary>
                                <label class="mt-1 flex items-center gap-1.5 font-medium">
                                    <input type="checkbox" wire:model.live="notifyEveryone" class="rounded border-gray-300 dark:border-gray-600" data-testid="notify-everyone" />
                                    Tutti gli utenti di questa rivista
                                </label>
                                @unless ($notifyEveryone)
                                    <div class="mt-1 max-h-32 overflow-y-auto grid grid-cols-2 gap-x-2">
                                        @foreach ($candidates as $candidate)
                                            <label class="flex items-center gap-1.5 truncate" title="{{ $candidate->email }}">
                                                <input type="checkbox" wire:model="recipientIds" value="{{ $candidate->id }}" class="rounded border-gray-300 dark:border-gray-600" />
                                                {{ $candidate->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endunless
                            </details>

                            <div class="flex justify-end">
                                <button type="submit" class="px-3 py-1.5 rounded bg-indigo-600 text-white text-sm" data-testid="page-comment-submit">Invia</button>
                            </div>
                        </form>
                    @else
                        <p class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 text-xs text-gray-500 dark:text-gray-400">Con il tuo ruolo puoi leggere i commenti ma non scriverne.</p>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
