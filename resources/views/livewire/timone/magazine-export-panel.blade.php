{{-- Revisione 2, Fase 5: PDF sfogliabile della rivista (App\Livewire\Timone\MagazineExportPanel).
     Il poll c'è solo mentre un job è in coda o in generazione. --}}
<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-4" data-testid="magazine-export-panel">
    @if ($running)
        <div wire:poll.3s aria-hidden="true" style="display: none;"></div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">📖 PDF sfogliabile della rivista</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Tutte le pagine nell'ordine di stampa (I e II di copertina, pagine interne, III e IV), ognuna su un A4, da sfogliare a coppie. Si genera in coda: puoi continuare a lavorare.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                <input type="checkbox" wire:model="preview" class="rounded border-gray-300 dark:border-gray-600" />
                Bassa risoluzione per anteprima (file leggero da mandare via mail)
            </label>
            <button type="button" wire:click="generate" wire:loading.attr="disabled" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700 disabled:opacity-50">
                Esporta PDF rivista
            </button>
        </div>
    </div>

    @if ($exports->isNotEmpty())
        <ul class="mt-3 space-y-1 text-sm" data-testid="magazine-export-status">
            @foreach ($variants as $variant)
                @php($export = $exports->get($variant->value))
                @continue($export === null)
                <li class="flex flex-wrap items-center gap-2">
                    <span class="text-gray-600 dark:text-gray-300">{{ $variant->label() }}:</span>
                    <span @class([
                        'px-1.5 py-0.5 rounded text-xs font-medium',
                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' => $export->status === \App\Enums\MagazineExportStatus::Queued,
                        'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200' => $export->status === \App\Enums\MagazineExportStatus::Processing,
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' => $export->status === \App\Enums\MagazineExportStatus::Ready,
                        'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' => $export->status === \App\Enums\MagazineExportStatus::Failed,
                    ])>{{ $export->status->label() }}</span>
                    @if ($export->status === \App\Enums\MagazineExportStatus::Ready)
                        <a href="{{ route('issues.export.magazine-pdf', [$issue->magazine, $issue, $variant->value]) }}" class="text-indigo-700 dark:text-indigo-300 underline">⬇ Scarica</a>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $export->page_count }} pagine · {{ \App\Support\Deletion\DeletionSummary::size((int) $export->size) }} · {{ $export->finished_at?->format('d/m/Y H:i') }}
                            @if ($export->missing_files > 0)
                                · <span class="text-amber-700 dark:text-amber-400">{{ $export->missing_files }} {{ $export->missing_files === 1 ? 'contenuto senza file' : 'contenuti senza file' }} («File mancante»)</span>
                            @endif
                        </span>
                    @elseif ($export->status === \App\Enums\MagazineExportStatus::Failed)
                        <span class="text-xs text-red-700 dark:text-red-400">{{ $export->error }} — riprova con «Esporta PDF rivista».</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
