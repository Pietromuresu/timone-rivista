<?php

namespace App\Livewire\Timone;

use App\Enums\PageRole;
use App\Livewire\Concerns\NormalizesEmptyNumericInput;
use App\Models\Issue;
use App\Support\ActivityLogger;
use App\Support\PageNumberer;
use Livewire\Component;

/**
 * Vista dedicata a copertina/battente/quarta di copertina (Fase 3.1),
 * separata dalla griglia principale — segue la policy stabilita in
 * HANDOFF.md ("Decisioni architetturali da ricordare", SOLID su Grid.php):
 * una nuova funzionalità che non è "far reagire il timone in tempo reale a
 * un cambiamento" va in un componente a sé, non dentro Grid.php.
 *
 * Non riusa page-card.blade.php: quel partial è cablato per chiamare
 * $wire.movePage()/assignContent()/selectForSwap() sul componente Grid,
 * riusarlo qui li chiamerebbe su QUESTO componente (che non li ha) e
 * romperebbe silenziosamente drag&drop/assegnazione contenuti sulle card di
 * copertina mostrate qui. Le pagine di copertina restano comunque
 * pienamente interattive nella griglia principale (sono pagine come le
 * altre lì) — questo pannello è un riepilogo dedicato in sola lettura più
 * la sola azione che gli è propria, assegnare/rimuovere il ruolo
 * Copertina2/Copertina3/Battente.
 */
class CoverSection extends Component
{
    use NormalizesEmptyNumericInput;

    public Issue $issue;

    public bool $expanded = false;

    public ?int $assignPageId = null;

    public string $assignRoleValue = 'copertina2';

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    /**
     * Ordine di visualizzazione fisico (non alfabetico): copertina esterna,
     * battente ripiegato subito dopo (è fisicamente agganciato alla prima
     * di copertina), poi l'interno delle due copertine, chiudendo con la
     * quarta — rispecchia come si sfoglierebbe la rivista dall'esterno.
     */
    private const DISPLAY_ORDER = [
        PageRole::Copertina1,
        PageRole::Battente,
        PageRole::Copertina2,
        PageRole::Copertina3,
        PageRole::Copertina4,
    ];

    public function assignRole(int $pageId, string $role): void
    {
        $this->authorize('update', $this->issue);

        $roleEnum = PageRole::tryFrom($role);

        if ($roleEnum === null || ! in_array($roleEnum, PageRole::manuallyAssignable(), true)) {
            return;
        }

        $page = $this->issue->pages()->find($pageId);

        if ($page === null || $page->isLocked()) {
            return;
        }

        // Copertina1/Copertina4 sono gestite da PageRoleAssigner in base
        // alla posizione fisica: assegnarle qui a mano andrebbe fuori
        // sincrono al primo resize, per questo manuallyAssignable() le
        // esclude già a monte — questo controllo è un secondo strato di
        // difesa nel caso arrivi comunque un valore non atteso dal client.
        $oldRole = $page->role;
        $page->update(['role' => $roleEnum->value]);

        ActivityLogger::log(
            issue: $this->issue,
            entityType: 'Page',
            entityId: $page->id,
            action: 'page.role_changed',
            description: "Ruolo pagina {$page->position} cambiato da {$oldRole->label()} a {$roleEnum->label()}",
            old: ['role' => $oldRole->value],
            new: ['role' => $roleEnum->value],
        );
    }

    /**
     * Orchestrazione del piccolo form nella vista (wire:model su
     * $assignPageId/$assignRoleValue) — delega ad assignRole(), stessa
     * validazione, nessuna logica duplicata.
     */
    public function submitAssignment(): void
    {
        if ($this->assignPageId === null) {
            return;
        }

        $this->assignRole($this->assignPageId, $this->assignRoleValue);
        $this->assignPageId = null;
    }

    public function render()
    {
        $pages = $this->issue->pages()
            ->where('role', '!=', PageRole::Interna->value)
            ->with([
                'contents.article',
                'contents.advertisement',
                'files' => fn ($query) => $query->latest()->limit(1),
            ])
            ->get()
            ->sortBy(fn ($page) => array_search($page->role, self::DISPLAY_ORDER, true));

        $hasBattente = $pages->contains(fn ($page) => $page->role === PageRole::Battente);

        $internalPages = $this->issue->pages()
            ->where('role', PageRole::Interna->value)
            ->orderBy('position')
            ->get(['id', 'position']);

        // Etichetta mostrata nel menu "assegna a una pagina interna"
        // (sotto): deve combaciare con quella già vista dall'utente nella
        // griglia principale, non la posizione fisica grezza — serve
        // l'intera issue (non solo $internalPages) perché il conteggio
        // riparte da 1 dopo la copertina, vedi App\Support\PageNumberer.
        $pageNumbers = PageNumberer::assign(
            $this->issue->pages()->orderBy('position')->get(['id', 'position', 'role']),
            $this->issue->magazine->battente_counted_in_total
        );

        return view('livewire.timone.cover-section', [
            'coverPages' => $pages,
            'hasBattente' => $hasBattente,
            'internalPages' => $internalPages,
            'pageNumbers' => $pageNumbers,
            'assignableRoles' => array_filter(PageRole::manuallyAssignable(), fn ($role) => $role !== PageRole::Interna),
            'displayedTotalPages' => $this->issue->displayedTotalPages(),
        ]);
    }
}
