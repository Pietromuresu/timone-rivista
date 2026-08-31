<?php

namespace App\Enums;

/**
 * Ruolo strutturale della pagina nel numero (copertina/battente/interna),
 * asse deliberatamente separato da PageContentType (editoriale/pubblicità/
 * mista/bianca — COSA c'è sulla pagina): una copertina può benissimo essere
 * "mista" o "pubblicita" nel content_type, restando "copertina1" nel role.
 * Vedi PROMPT_DI_PARTENZA.md §4.3 ("copertina 1ª/2ª/3ª/4ª") e §6.8.
 */
enum PageRole: string
{
    case Copertina1 = 'copertina1';
    case Copertina2 = 'copertina2';
    case Copertina3 = 'copertina3';
    case Copertina4 = 'copertina4';
    case Battente = 'battente';
    case Interna = 'interna';

    public function label(): string
    {
        return match ($this) {
            self::Copertina1 => 'Copertina (1ª)',
            self::Copertina2 => 'Seconda di copertina',
            self::Copertina3 => 'Terza di copertina',
            self::Copertina4 => 'Quarta di copertina',
            self::Battente => 'Battente',
            self::Interna => 'Interna',
        };
    }

    public function isCoverSection(): bool
    {
        return $this !== self::Interna;
    }

    /**
     * @return array<self> Ruoli assegnabili manualmente dal pannello
     *  Copertina/Battente — esclude Copertina1/Copertina4, che sono
     *  ricalcolati automaticamente da PageRoleAssigner in base alla
     *  posizione fisica (prima/ultima pagina) e non vanno mai scelti a
     *  mano, altrimenti andrebbero fuori sincrono al primo resize.
     */
    public static function manuallyAssignable(): array
    {
        return [self::Copertina2, self::Copertina3, self::Battente, self::Interna];
    }
}
