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
     * Occupazione del formato in dodicesimi di pagina (Fase 3, 2026-10-27)
     * — sostituisce defaultPercentage(): una sola fonte per "quanto spazio
     * occupa questo formato", da cui il modulo propone pagine intere +
     * frazione (sempre modificabili) e il cruscotto conta il carico.
     *
     * Mappatura dei formati esistenti (concordata nella consegna della
     * Fase 3, vedi HANDOFF.md):
     * - le percentuali di listino che non erano frazioni esatte diventano
     *   la frazione che rappresentano: Piedino 32.6% → 1/3 (4), 2/3 di
     *   pagina 70.5% → 2/3 (8);
     * - Copertina (2ª/3ª/4ª) 52% → pagina intera (12): occupa una
     *   copertina intera, il 52% era un rapporto di listino, non spazio;
     * - Battente copertina → 12: dalla Fase 2 la bandella è UN solo slot
     *   (che vale 2 facciate nel peso), quindi una pagina del timone;
     * - Doppia pagina → 24: due pagine intere affiancate;
     * - "1/4 di pagina" è la vetrina, "Piedino" il piedino 1/3.
     */
    public function twelfths(): int
    {
        return match ($this) {
            self::PaginaIntera,
            self::BattenteCopertina,
            self::CopertinaSecondaTerzaQuarta,
            self::PrimaRomana,
            self::Controsommario,
            self::ElencoInserzionisti,
            self::Controeditoriale,
            self::Pubbliredazionale => 12,
            self::DoppiaPagina => 24,
            self::DueTerziPagina => 8,
            self::MezzaPaginaOrizzontale, self::MezzaPaginaVerticale => 6,
            self::UnTerzoPaginaOrizzontale, self::UnTerzoPaginaVerticale, self::Piedino => 4,
            self::UnQuartoPagina => 3,
        };
    }

    /**
     * Orientamento nella disposizione della card (Fase 7a, 2026-10-27),
     * ricavato dalle misure del listino (dimensionsMm(), unica fonte):
     * alto quanto la pagina ma più stretto → colonna (mezza verticale,
     * 1/3 verticale, 2/3); il piedino è una fascia in fondo; tutto il
     * resto (pagine intere, mezza orizzontale, vetrina, formati senza
     * misure) si impila in righe.
     */
    public function orientation(): ContentOrientation
    {
        if ($this === self::Piedino) {
            return ContentOrientation::Bottom;
        }

        $size = $this->dimensionsMm();

        return $size !== null && $size['height'] >= 270.0 && $size['width'] < 210.0
            ? ContentOrientation::Vertical
            : ContentOrientation::Horizontal;
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
     *   coerente con twelfths() sopra (12 per ciascuna delle due pagine).
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
