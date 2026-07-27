<?php

namespace App\Livewire\Timone;

use App\Models\Issue;
use App\Models\Page;
use App\Models\PageFile;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Componente Livewire a sé (stessa scelta di App\Livewire\Timone\ContentCreate,
 * vedi HANDOFF.md "Decisioni architetturali da ricordare"): visualizzare lo
 * storico dei PDF caricati su una pagina non ha nulla a che fare con il
 * rendering realtime del timone in sé — è un pannello di sola lettura,
 * aperto occasionalmente, non ha senso farlo crescere dentro Grid.php.
 *
 * Il trigger (bottone sulla card/riga pagina, dentro il template di Grid)
 * comunica con questo componente tramite l'evento browser globale
 * `show-file-history`, non tramite $wire diretto — i due componenti sono
 * fratelli, non genitore/figlio.
 */
class PageFileHistory extends Component
{
    public Issue $issue;

    public ?int $pageId = null;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    #[On('show-file-history')]
    public function show(int $pageId): void
    {
        $page = Page::find($pageId);

        if ($page === null || $page->issue_id !== $this->issue->id) {
            return;
        }

        $this->pageId = $pageId;
    }

    public function render(): View
    {
        $page = $this->pageId !== null ? Page::find($this->pageId) : null;

        $files = $page !== null
            ? PageFile::where('page_id', $page->id)->with('uploader')->latest()->get()
            : collect();

        return view('livewire.timone.page-file-history', [
            'page' => $page,
            'files' => $files,
        ]);
    }
}
