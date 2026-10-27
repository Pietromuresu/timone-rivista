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
 * La bandella (il "battente": lembo ripiegato agganciato alla I) è
 * facoltativa e vale due facciate nel calcolo del peso — è il caso "6
 * pagine di copertina".
 */
enum CoverSlot: string
{
    case I = 'I';
    case II = 'II';
    case III = 'III';
    case IV = 'IV';
    case Bandella = 'bandella';

    public function label(): string
    {
        return match ($this) {
            self::Bandella => 'Bandella',
            default => "{$this->value} di copertina",
        };
    }

    /** Etichetta breve per elenchi e avvisi ("II cop."). */
    public function shortLabel(): string
    {
        return $this === self::Bandella ? 'bandella' : "{$this->value} cop.";
    }

    /**
     * Facciate stampate, per il peso (`issues.pagine_copertina`): ogni
     * copertina è una facciata, la bandella due (fronte e retro del lembo).
     */
    public function printedPages(): int
    {
        return $this === self::Bandella ? 2 : 1;
    }

    /** Ordine fisico di sfoglio, usato per ordinare le copertine. */
    public function order(): int
    {
        return match ($this) {
            self::I => 1,
            self::Bandella => 2,
            self::II => 3,
            self::III => 4,
            self::IV => 5,
        };
    }

    /** @return list<self> Le quattro copertine sempre presenti. */
    public static function mandatory(): array
    {
        return [self::I, self::II, self::III, self::IV];
    }

    /**
     * Slot corrispondente a un vecchio ruolo pagina (`pages.role`, modello
     * precedente alla Fase 2) — usato solo dalla conversione dei dati.
     */
    public static function fromLegacyRole(?string $role): ?self
    {
        return match ($role) {
            'copertina1' => self::I,
            'copertina2' => self::II,
            'copertina3' => self::III,
            'copertina4' => self::IV,
            'battente' => self::Bandella,
            default => null,
        };
    }
}
