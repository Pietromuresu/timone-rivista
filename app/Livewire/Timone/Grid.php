<?php

namespace App\Livewire\Timone;

use App\Models\Issue;
use App\Support\PageSpreadBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Grid extends Component
{
    public Issue $issue;

    /**
     * griglia (flat plan a card), doppia (pagine affiancate come aperte
     * nella rivista stampata) o lista (una riga per pagina).
     */
    public string $viewMode = 'griglia';

    protected const VIEW_MODES = ['griglia', 'doppia', 'lista'];

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, self::VIEW_MODES, true)) {
            $this->viewMode = $mode;
        }
    }

    public function render(): View
    {
        $pages = $this->issue->pages()
            ->with([
                'contents.article',
                'contents.advertisement',
                'files' => fn ($query) => $query->latest()->limit(1),
            ])
            ->get();

        return view('livewire.timone.grid', [
            'pages' => $pages,
            'spreads' => $this->viewMode === 'doppia' ? PageSpreadBuilder::build($pages) : null,
        ]);
    }
}
