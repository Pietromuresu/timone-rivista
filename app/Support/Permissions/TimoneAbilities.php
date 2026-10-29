<?php

namespace App\Support\Permissions;

use App\Enums\ContentType;
use App\Enums\UserRole;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;
use App\Models\User;

/**
 * Cosa può fare un utente sul timone di UN numero (revisione 2, Fase 4.1,
 * 2026-10-28). Unico punto delle regole "chi sposta/modifica cosa": lo usano
 * le policy (IssuePolicy, ContentPolicy: controllo lato server) e le viste
 * del timone (maniglie, frecce, tooltip), così bottone nascosto e 403 non
 * possono divergere.
 *
 * Perché un oggetto e non solo le policy: la vista chiede "posso spostare
 * questo contenuto/questa pagina?" per decine di elementi a ogni render; le
 * policy rifarebbero ogni volta la query sull'accesso alla rivista. Qui
 * accesso e ruolo si leggono una volta (for()) e le domande successive
 * sono pure.
 *
 * Regole (scelte dall'utente):
 * - Admin e Redattore (UserRole::editsTimone()) fanno tutto;
 * - il Commerciale (UserRole::handlesAds()) crea, modifica e sposta SOLO
 *   pubblicità (segnaposto compresi) e sposta solo pagine senza articoli;
 * - Sola lettura: niente.
 */
final class TimoneAbilities
{
    public const EDITORIAL_ONLY = 'Solo la redazione può spostare questo contenuto';

    public const EDITORIAL_PAGES_ONLY = 'Solo la redazione può spostare pagine che contengono articoli';

    private function __construct(
        private readonly bool $hasAccess,
        private readonly ?UserRole $role,
    ) {}

    public static function for(?User $user, Issue $issue): self
    {
        return new self(
            $user !== null && $user->canAccessMagazine($issue->magazine),
            $user?->role,
        );
    }

    /**
     * Commenti (2026-10-29): admin, redattore e commerciale su qualunque
     * pagina; la sola lettura no. PageCommentPolicy e la vista usano questo.
     */
    public function canComment(): bool
    {
        return $this->hasAccess && ($this->role?->handlesAds() ?? false);
    }

    /** Tutto il timone: pagine, stati, gruppi, articoli, impostazioni del numero. */
    public function editsTimone(): bool
    {
        return $this->hasAccess && (bool) $this->role?->editsTimone();
    }

    /** Almeno le pubblicità (il Commerciale lavora solo su queste). */
    public function handlesAds(): bool
    {
        return $this->hasAccess && (bool) $this->role?->handlesAds();
    }

    public function canChangeContentOfType(ContentType|string $type): bool
    {
        $type = $type instanceof ContentType ? $type : ContentType::tryFrom($type);

        return $this->editsTimone() || ($type === ContentType::Pubblicita && $this->handlesAds());
    }

    /** Crea, modifica, sposta, ruota, toglie dalla pagina questo contenuto. */
    public function canChangeContent(Content $content): bool
    {
        return $this->canChangeContentOfType($content->type);
    }

    /**
     * Pagine che si spostano insieme (una pagina, un gruppo collegato, una
     * selezione, due pagine scambiate): per chi lavora solo sulle
     * pubblicità, nessuna deve contenere articoli.
     *
     * @param  iterable<Page>  $pages
     */
    public function canMovePages(iterable $pages): bool
    {
        if ($this->editsTimone()) {
            return true;
        }

        if (! $this->handlesAds()) {
            return false;
        }

        foreach ($pages as $page) {
            if (self::hasEditorialContent($page)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Per la vista: id delle pagine che questo utente NON può trascinare
     * (niente maniglia, cursore not-allowed). Una pagina di un gruppo
     * collegato si sposta con tutto il gruppo, quindi basta un articolo in
     * una pagina del gruppo per bloccarle tutte — come farebbe il server.
     *
     * @param  iterable<Page>  $pages  con `contents` caricati
     * @return array<int, true> id => true (lookup veloce nel Blade)
     */
    public function immovablePageIds(iterable $pages): array
    {
        if ($this->editsTimone()) {
            return [];
        }

        $pages = collect($pages);

        if (! $this->handlesAds()) {
            return $pages->mapWithKeys(fn (Page $page) => [$page->id => true])->all();
        }

        $editorial = $pages->filter(fn (Page $page) => self::hasEditorialContent($page));
        $blockedGroups = $editorial->pluck('page_group_id')->filter()->unique()->all();

        return $pages
            ->filter(fn (Page $page) => $editorial->contains('id', $page->id) || in_array($page->page_group_id, $blockedGroups, true))
            ->mapWithKeys(fn (Page $page) => [$page->id => true])
            ->all();
    }

    /**
     * Usa i contenuti già caricati quando ci sono (render del timone),
     * altrimenti una query (azioni lato server).
     */
    private static function hasEditorialContent(Page $page): bool
    {
        return $page->relationLoaded('contents')
            ? $page->contents->contains(fn (Content $content) => $content->type === ContentType::Articolo)
            : $page->contents()->where('type', ContentType::Articolo->value)->exists();
    }
}
