<?php

namespace App\Support;

use App\Enums\PageFillStatus;

/**
 * Capienza di una pagina in dodicesimi (Fase 3, 2026-10-27) — sostituisce
 * PagePercentageAllocator. Una pagina contiene 12 dodicesimi; più
 * contenuti possono condividerla finché la somma non supera 12.
 *
 * Classe pura (nessuna query): riceve gli interi già letti dal chiamante,
 * stesso pattern di PageReorderer/AdLoadCalculator. L'unico punto che
 * decide "ci sta / non ci sta" e "vuota / parziale / piena", e l'unico che
 * scrive il messaggio d'errore — così ContentPlacer, ContentPageAttacher e
 * Grid dicono la stessa cosa con le stesse parole.
 */
final class PageCapacity
{
    /** @param  array<int, int|string>  $occupied  dodicesimi già occupati sulla pagina */
    public static function used(array $occupied): int
    {
        return array_sum(array_map('intval', $occupied));
    }

    /** Spazio libero (mai negativo, anche su una pagina oltre capienza). */
    public static function free(array $occupied): int
    {
        return max(0, Twelfths::PER_PAGE - self::used($occupied));
    }

    /**
     * Vero se $candidate dodicesimi stanno sulla pagina accanto agli altri
     * contenuti ($otherOccupied, escluso quello eventualmente in modifica).
     */
    public static function fits(array $otherOccupied, int $candidate): bool
    {
        return $candidate >= 1 && self::used($otherOccupied) + $candidate <= Twelfths::PER_PAGE;
    }

    public static function status(int $used): PageFillStatus
    {
        return match (true) {
            $used <= 0 => PageFillStatus::Empty,
            $used < Twelfths::PER_PAGE => PageFillStatus::Partial,
            $used === Twelfths::PER_PAGE => PageFillStatus::Full,
            default => PageFillStatus::Overfull,
        };
    }

    /** Etichetta breve per la card: "Vuota", "Libero: 1/3", "Piena". */
    public static function statusLabel(int $used): string
    {
        return match (self::status($used)) {
            PageFillStatus::Empty => 'Vuota',
            PageFillStatus::Partial => 'Libero: '.Twelfths::fractionLabel(Twelfths::PER_PAGE - $used),
            PageFillStatus::Full => 'Piena',
            PageFillStatus::Overfull => 'Oltre capienza (+'.Twelfths::fractionLabel($used - Twelfths::PER_PAGE).')',
        };
    }

    /**
     * Errore bloccante "non ci sta", con quanto spazio resta davvero
     * (richiesta esplicita: l'utente deve capire cosa può ancora mettere
     * su quella pagina senza fare conti).
     */
    public static function insufficientSpaceMessage(string $pageLabel, int $requested, int $free, ?string $contentLabel = null): string
    {
        $what = $contentLabel !== null ? "«{$contentLabel}» richiede " : 'richiesti ';
        $left = $free === 0
            ? 'la pagina è già piena'
            : 'resta libero solo '.Twelfths::pagesLabel($free);

        return "Spazio insufficiente sulla {$pageLabel}: {$what}".Twelfths::pagesLabel($requested).', ma '.$left.'.';
    }
}
