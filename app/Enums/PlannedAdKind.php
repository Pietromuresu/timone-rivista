<?php

namespace App\Enums;

use App\Support\AdPlaceholder;

/**
 * Tipi di pubblicità "previste" che si indicano creando un numero (Fase 5,
 * 2026-10-27): ciascuno diventa una pubblicità segnaposto con il formato
 * di listino corrispondente. L'ordine dei case è quello di posizionamento
 * (AdPlacementService): pagine intere prima, poi le frazioni dalla più
 * grande alla più piccola. Un nuovo tipo = un nuovo case (Open/Closed).
 */
enum PlannedAdKind: string
{
    case Full = 'full';
    case TwoThirds = 'two_thirds';
    case Half = 'half';
    case Piedino = 'piedino';
    case Vetrina = 'vetrina';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Pagina intera',
            self::TwoThirds => '2/3 di pagina',
            self::Half => '1/2 pagina',
            self::Piedino => 'Piedino (1/3)',
            self::Vetrina => 'Vetrina (1/4)',
        };
    }

    /**
     * Formato di listino del segnaposto. La mezza pagina nasce
     * orizzontale: l'orientamento vero si sceglie quando arriva
     * l'inserzionista (si modifica come ogni contenuto).
     */
    public function format(): AdFormat
    {
        return match ($this) {
            self::Full => AdFormat::PaginaIntera,
            self::TwoThirds => AdFormat::DueTerziPagina,
            self::Half => AdFormat::MezzaPaginaOrizzontale,
            self::Piedino => AdFormat::Piedino,
            self::Vetrina => AdFormat::UnQuartoPagina,
        };
    }

    public function twelfths(): int
    {
        return $this->format()->twelfths();
    }

    /**
     * Titolo automatico del segnaposto: «Pubblicità 1/2 — da assegnare».
     * Il testo nasce in AdPlaceholder::title(), lo stesso usato dal modulo
     * quando il titolo di una pubblicità resta vuoto (revisione 2, Fase 3.1).
     */
    public function placeholderTitle(): string
    {
        return AdPlaceholder::title($this->twelfths());
    }
}
