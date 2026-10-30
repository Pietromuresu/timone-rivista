<?php

namespace App\Support;

use App\Enums\AdFormat;
use App\Enums\CoverFlap;
use App\Enums\CoverSlot;
use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Models\Issue;
use App\Models\Page;
use App\Support\Comments\DeletedPageComments;
use Illuminate\Support\Facades\DB;

/**
 * Le copertine di un numero (Fase 2, 2026-10-26): creazione, battenti (2026-10-30),
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
     * create solo I e IV — bug), più i battenti richiesti. Idempotente.
     *
     * @param  list<CoverFlap>  $flaps
     */
    public static function ensure(Issue $issue, array $flaps = []): void
    {
        $existing = $issue->covers()->pluck('cover_slot')->map(fn ($slot) => $slot instanceof CoverSlot ? $slot : CoverSlot::from($slot))->all();
        $wanted = [...CoverSlot::mandatory(), ...collect($flaps)->flatMap(fn (CoverFlap $flap) => $flap->slots())->all()];

        foreach ($wanted as $slot) {
            if (! in_array($slot, $existing, true)) {
                self::create($issue, $slot);
            }
        }

        self::syncPrintedPages($issue);
    }

    /** @return list<CoverFlap> I battenti presenti, in ordine di sfoglio. */
    public static function flaps(Issue $issue): array
    {
        $slots = $issue->covers()->pluck('cover_slot')->all();

        return array_values(array_filter(CoverFlap::cases(), fn (CoverFlap $flap) => in_array($flap->slots()[0], $slots, true)));
    }

    /**
     * Aggiunge un battente: le sue 2 pagine, vuote (2 facciate in più per
     * il peso).
     *
     * @return string|null messaggio d'errore, null se aggiunto
     */
    public static function addFlap(Issue $issue, CoverFlap $flap): ?string
    {
        if (in_array($flap, self::flaps($issue), true)) {
            return "Questo numero ha già il battente {$flap->value}.";
        }

        DB::transaction(function () use ($issue, $flap) {
            foreach ($flap->slots() as $slot) {
                self::create($issue, $slot);
            }
        });
        self::syncPrintedPages($issue);

        return null;
    }

    /**
     * Perché un battente non si può togliere (null = si può): le sue pagine
     * devono essere vuote e non bloccate, per non far sparire in silenzio
     * un contenuto. Lo usa anche la fascia copertine per il tooltip del
     * bottone disattivato, così il motivo è lo stesso del rifiuto.
     */
    public static function removalBlocker(Issue $issue, CoverFlap $flap): ?string
    {
        $pages = $issue->covers()->whereIn('cover_slot', array_map(fn (CoverSlot $slot) => $slot->value, $flap->slots()))
            ->with(['contents.advertisement'])->get()->sortBy(fn (Page $page) => $page->cover_slot->order());

        foreach ($pages as $page) {
            $where = "La pagina {$page->cover_slot->flapPage()} del battente {$flap->value}";

            if ($page->isLocked()) {
                return "{$where} è bloccata: sbloccala prima.";
            }

            if (($content = $page->contents->first()) !== null) {
                return "{$where} contiene «{$content->displayLabel()}»: toglila prima.";
            }
        }

        return null;
    }

    /**
     * Toglie un battente intero, solo se removalBlocker() lo consente.
     *
     * @return string|null messaggio d'errore, null se tolto
     */
    public static function removeFlap(Issue $issue, CoverFlap $flap): ?string
    {
        $pages = $issue->covers()->whereIn('cover_slot', array_map(fn (CoverSlot $slot) => $slot->value, $flap->slots()))->get();

        if ($pages->isEmpty()) {
            return null;
        }

        if (($blocker = self::removalBlocker($issue, $flap)) !== null) {
            return $blocker;
        }

        DB::transaction(function () use ($pages) {
            // Commenti (2026-10-29): restano nel numero come «Battente I · 1, eliminata».
            DeletedPageComments::preserve($pages);
            $pages->each->delete();
        });
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

        // Una pubblicità «Battente copertina» occupa le due pagine del suo
        // battente: scambiarne una la spezzerebbe tra due copertine.
        foreach ([$a, $b] as $cover) {
            $flapAd = $cover->contents()->with('advertisement')->get()->first(fn ($content) => $content->advertisement?->format === AdFormat::BattenteCopertina);

            if ($flapAd !== null) {
                return "«{$flapAd->displayLabel()}» occupa tutto il battente: non si scambia con un'altra copertina.";
            }
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
