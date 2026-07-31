<?php

namespace App\Enums;

enum AdFormat: string
{
    case PaginaIntera = 'pagina_intera';
    case MezzaPaginaOrizzontale = 'mezza_pagina_orizzontale';
    case MezzaPaginaVerticale = 'mezza_pagina_verticale';
    case UnTerzoPaginaOrizzontale = 'un_terzo_pagina_orizzontale';
    case UnTerzoPaginaVerticale = 'un_terzo_pagina_verticale';
    case UnQuartoPagina = 'un_quarto_pagina';

    // Aggiunti in Fase 2 (2026-07-30) per coprire l'intero listino ADV
    // allegato dall'utente — vedi dimensionsMm() sotto per le misure e
    // HANDOFF.md per le assunzioni documentate sui casi ambigui.
    case BattenteCopertina = 'battente_copertina';
    case DoppiaPagina = 'doppia_pagina';
    case CopertinaSecondaTerzaQuarta = 'copertina_seconda_terza_quarta';
    case Piedino = 'piedino';
    case DueTerziPagina = 'due_terzi_pagina';
    case PrimaRomana = 'prima_romana';
    case Controsommario = 'controsommario';
    case ElencoInserzionisti = 'elenco_inserzionisti';
    case Controeditoriale = 'controeditoriale';
    case Pubbliredazionale = 'pubbliredazionale';

    public function label(): string
    {
        return match ($this) {
            self::PaginaIntera => 'Pagina intera',
            self::MezzaPaginaOrizzontale => 'Mezza pagina orizzontale',
            self::MezzaPaginaVerticale => 'Mezza pagina verticale',
            self::UnTerzoPaginaOrizzontale => 'Un terzo pagina orizzontale',
            self::UnTerzoPaginaVerticale => 'Un terzo pagina verticale',
            self::UnQuartoPagina => 'Un quarto pagina',
            self::BattenteCopertina => 'Battente copertina',
            self::DoppiaPagina => 'Doppia pagina',
            self::CopertinaSecondaTerzaQuarta => 'Copertina (2ª/3ª/4ª)',
            self::Piedino => 'Piedino',
            self::DueTerziPagina => '2/3 di pagina',
            self::PrimaRomana => '1ª romana',
            self::Controsommario => 'Controsommario',
            self::ElencoInserzionisti => 'Elenco inserzionisti',
            self::Controeditoriale => 'Controeditoriale',
            self::Pubbliredazionale => 'Pubbliredazionale',
        };
    }

    /**
     * Percentuale di pagina occupata di default per questo formato.
     * Battente copertina/Doppia pagina occupano 100% di CIASCUNA delle due
     * pagine affiancate a cui vanno assegnati (non 200% di una sola): lo
     * stesso Content va assegnato a entrambe le pagine tramite
     * Grid::extendToPage(), già esistente per i contenuti multipagina — non
     * serve nessun concetto nuovo nello schema per rappresentarli.
     */
    public function defaultPercentage(): float
    {
        return match ($this) {
            self::PaginaIntera,
            self::BattenteCopertina,
            self::DoppiaPagina,
            self::PrimaRomana,
            self::Controsommario,
            self::ElencoInserzionisti,
            self::Controeditoriale,
            self::Pubbliredazionale => 100.0,
            self::MezzaPaginaOrizzontale, self::MezzaPaginaVerticale => 50.0,
            self::UnTerzoPaginaOrizzontale, self::UnTerzoPaginaVerticale => 33.3,
            self::UnQuartoPagina => 25.0,
            self::CopertinaSecondaTerzaQuarta => 52.0,
            self::Piedino => 32.6,
            self::DueTerziPagina => 70.5,
        };
    }

    /**
     * Misure nominali (mm, larghezza×altezza) del formato secondo il
     * listino ADV ufficiale allegato dall'utente in Fase 2 — usate dal
     * controllo formato (§2.3, vedi App\Support\PdfFormatChecker) per
     * confrontare le dimensioni reali del PDF caricato, includendo poi
     * l'abbondanza di stampa (+3mm per lato). `null` quando il listino non
     * fornisce (o non rende univoca) una misura affidabile: il controllo
     * formato per quel caso resta "non applicabile" invece di usare una
     * stima inventata.
     *
     * Note sui casi non ovvi:
     * - Doppia pagina: il listino dà 210×270 per CIASCUNA delle due pagine
     *   affiancate (non il totale) — stessa misura di "1 pagina intera",
     *   coerente con defaultPercentage() sopra (100% per pagina).
     * - Un terzo pagina orizzontale: il listino ha una sola voce "1/3 di
     *   pagina" (58×270, chiaramente una striscia stretta e alta, quindi
     *   assegnata qui a UnTerzoPaginaVerticale) — nessuna misura
     *   corrispondente per un possibile "1/3 orizzontale": tornare `null`
     *   invece di inventare una trasposizione non richiesta dal listino.
     *
     * @return array{width: float, height: float}|null
     */
    public function dimensionsMm(): ?array
    {
        return match ($this) {
            self::PaginaIntera, self::DoppiaPagina,
            self::PrimaRomana, self::Controsommario,
            self::ElencoInserzionisti, self::Controeditoriale,
            self::Pubbliredazionale => ['width' => 210.0, 'height' => 270.0],
            self::MezzaPaginaOrizzontale => ['width' => 210.0, 'height' => 137.0],
            self::MezzaPaginaVerticale => ['width' => 103.0, 'height' => 270.0],
            self::UnQuartoPagina => ['width' => 103.0, 'height' => 137.0],
            self::UnTerzoPaginaVerticale => ['width' => 58.0, 'height' => 270.0],
            self::UnTerzoPaginaOrizzontale => null,
            self::BattenteCopertina => ['width' => 420.0, 'height' => 270.0],
            self::CopertinaSecondaTerzaQuarta => ['width' => 152.0, 'height' => 194.0],
            self::Piedino => ['width' => 210.0, 'height' => 88.0],
            self::DueTerziPagina => ['width' => 148.0, 'height' => 270.0],
        };
    }
}
