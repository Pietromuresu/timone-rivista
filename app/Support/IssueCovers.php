<?php

namespace App\Support;

use App\Enums\CoverSlot;
use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Models\Issue;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Le copertine di un numero (Fase 2, 2026-10-26): creazione, bandella,
 * scambio tra slot, e il parametro del peso `pagine_copertina` che ne
 * deriva. Unico punto che scrive `pages.cover_slot`, così le regole
 * ("I–IV sempre presenti", "pagine_copertina = facciate reali") non si
 * sparpagliano tra observer e componenti.
 *
 * Statica come le altre classi App\Support (CLAUDE.md, regola 3).
 */
final class IssueCovers
{
    /**
     * Crea gli slot mancanti: I–IV sempre (prima della Fase 2 venivano
     * create solo I e IV — bug), più la bandella se richiesta. Idempotente.
     */
    public static function ensure(Issue $issue, bool $withBandella = false): void
    {
        $existing = $issue->covers()->pluck('cover_slot')->map(fn ($slot) => $slot instanceof CoverSlot ? $slot : CoverSlot::from($slot))->all();
        $wanted = $withBandella ? [...CoverSlot::mandatory(), CoverSlot::Bandella] : CoverSlot::mandatory();

        foreach ($wanted as $slot) {
            if (! in_array($slot, $existing, true)) {
                self::create($issue, $slot);
            }
        }

        self::syncPrintedPages($issue);
    }

    /**
     * Aggiunge la bandella (6 facciate di copertina invece di 4).
     *
     * @return string|null messaggio d'errore, null se aggiunta
     */
    public static function addBandella(Issue $issue): ?string
    {
        if ($issue->covers()->where('cover_slot', CoverSlot::Bandella->value)->exists()) {
            return 'Questo numero ha già la bandella.';
        }

        self::create($issue, CoverSlot::Bandella);
        self::syncPrintedPages($issue);

        return null;
    }

    /**
     * Toglie la bandella, solo se vuota e non bloccata: mai far sparire in
     * silenzio un contenuto assegnato.
     *
     * @return string|null messaggio d'errore, null se tolta
     */
    public static function removeBandella(Issue $issue): ?string
    {
        $bandella = $issue->covers()->where('cover_slot', CoverSlot::Bandella->value)->first();

        if ($bandella === null) {
            return null;
        }

        if ($bandella->isLocked()) {
            return 'La bandella è bloccata: sbloccala prima di toglierla.';
        }

        if ($bandella->contents()->exists()) {
            return 'La bandella ha contenuti assegnati: rimuovili prima di toglierla.';
        }

        $bandella->delete();
        self::syncPrintedPages($issue);

        return null;
    }

    /**
     * Scambia due copertine di slot (es. la pubblicità della II passa alla
     * III): contenuti, stato, anteprima e note seguono la pagina, come per
     * lo scambio delle pagine interne. Passo intermedio a NULL per non
     * urtare unique(issue_id, cover_slot).
     *
     * @return string|null messaggio d'errore, null se scambiate
     */
    public static function swap(Page $a, Page $b): ?string
    {
        if (! $a->isCover() || ! $b->isCover() || $a->issue_id !== $b->issue_id) {
            return 'Si possono scambiare solo due copertine dello stesso numero.';
        }

        if ($a->id === $b->id) {
            return null;
        }

        foreach ([$a, $b] as $cover) {
            if ($cover->isLocked()) {
                return "La {$cover->label()} è bloccata: sbloccala prima di spostarla.";
            }
        }

        if (CoverSlot::Bandella === $a->cover_slot || CoverSlot::Bandella === $b->cover_slot) {
            return 'La bandella non si scambia con le copertine: ha un formato diverso.';
        }

        DB::transaction(function () use ($a, $b) {
            $slotA = $a->cover_slot;
            $slotB = $b->cover_slot;
            $a->update(['cover_slot' => null]);
            $b->update(['cover_slot' => $slotA]);
            $a->update(['cover_slot' => $slotB]);
        });

        return null;
    }

    /**
     * `pagine_copertina` del peso = facciate reali delle copertine. Scritto
     * sulla colonna (e non solo calcolato) perché anteprima del peso,
     * modale del pannello Peso ed export lo leggono da lì.
     */
    public static function syncPrintedPages(Issue $issue): void
    {
        $count = $issue->coverPageCount();

        if ((int) $issue->pagine_copertina !== $count) {
            $issue->forceFill(['pagine_copertina' => $count])->saveQuietly();
        }
    }

    private static function create(Issue $issue, CoverSlot $slot): Page
    {
        return $issue->allPages()->create([
            'position' => null,
            'cover_slot' => $slot,
            'content_type' => PageContentType::Bianca,
            'status' => PageStatus::DaAssegnare,
        ]);
    }
}
