<?php

namespace App\Enums;

/**
 * Posizione fissa di una copertina nel numero (Fase 2, 2026-10-26).
 *
 * Le copertine NON sono pagine della sequenza interna: sono righe di
 * `pages` con `cover_slot` valorizzato e `position` NULL, fuori dalla
 * numerazione 1…N e da ogni conteggio delle pagine interne (vedi
 * Issue::pages() / Issue::covers()). Restano righe di `pages` per riusare
 * senza duplicazioni tutto ciò che una pagina sa fare: contenuti e
 * pubblicità assegnati, anteprima del PDF, stato, blocco, note.
 *
 * I–IV esistono sempre (create con il numero, App\Support\IssueCovers).
 * Battenti (2026-10-30, al posto dell'unica «bandella»): due facoltativi,
 * di 2 pagine ciascuno — il battente di I prima della I, quello di IV
 * dopo la IV (CoverFlap). Ogni pagina è una facciata, come le altre
 * copertine: 4, 6 o 8 facciate di copertina per il peso.
 *
 * Valori brevi (BI1…BIV2): la colonna `cover_slot` è varchar(10).
 */
enum CoverSlot: string
{
    case BattenteI1 = 'BI1';
    case BattenteI2 = 'BI2';
    case I = 'I';
    case II = 'II';
    case III = 'III';
    case IV = 'IV';
    case BattenteIV1 = 'BIV1';
    case BattenteIV2 = 'BIV2';

    public function label(): string
    {
        return match ($this) {
            self::BattenteI1, self::BattenteI2, self::BattenteIV1, self::BattenteIV2 => "Battente {$this->flap()->value} · {$this->flapPage()}",
            default => "{$this->value} di copertina",
        };
    }

    /** Etichetta breve per elenchi e avvisi ("II cop.", "batt. I·1"). */
    public function shortLabel(): string
    {
        return $this->flap() !== null ? "batt. {$this->flap()->value}·{$this->flapPage()}" : "{$this->value} cop.";
    }

    /** Il battente di cui fa parte questa pagina, null per I–IV. */
    public function flap(): ?CoverFlap
    {
        return match ($this) {
            self::BattenteI1, self::BattenteI2 => CoverFlap::I,
            self::BattenteIV1, self::BattenteIV2 => CoverFlap::IV,
            default => null,
        };
    }

    /** 1 o 2: pagina del battente in ordine di sfoglio; null per I–IV. */
    public function flapPage(): ?int
    {
        return match ($this) {
            self::BattenteI1, self::BattenteIV1 => 1,
            self::BattenteI2, self::BattenteIV2 => 2,
            default => null,
        };
    }

    /**
     * Ordine fisico di sfoglio: battente di I, I, II, (pagine interne),
     * III, IV, battente di IV. È l'ordine dei case, una sola fonte.
     */
    public function order(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    /** Le copertine prima delle pagine interne, in ordine di sfoglio. @return list<self> */
    public static function front(): array
    {
        return [self::BattenteI1, self::BattenteI2, self::I, self::II];
    }

    /** Le copertine dopo le pagine interne, in ordine di sfoglio. @return list<self> */
    public static function back(): array
    {
        return [self::III, self::IV, self::BattenteIV1, self::BattenteIV2];
    }

    /** @return list<self> Le quattro copertine sempre presenti. */
    public static function mandatory(): array
    {
        return [self::I, self::II, self::III, self::IV];
    }

    /**
     * Slot corrispondente a un vecchio ruolo pagina (`pages.role`, modello
     * precedente alla Fase 2) — usato solo dalla conversione dei dati. Il
     * vecchio «battente» non è qui: diventava la bandella (valore
     * CoverConversion::LEGACY_BANDELLA), che la migrazione del 2026-10-30
     * trasforma nel battente di I.
     */
    public static function fromLegacyRole(?string $role): ?self
    {
        return match ($role) {
            'copertina1' => self::I,
            'copertina2' => self::II,
            'copertina3' => self::III,
            'copertina4' => self::IV,
            default => null,
        };
    }
}
