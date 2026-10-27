<?php

namespace App\Enums;

/**
 * Come un contenuto si dispone nella card della pagina (Fase 7a,
 * 2026-10-27, App\Support\PageLayoutPlanner):
 * - Vertical: colonna a tutta altezza, larga in proporzione (mezza
 *   verticale, 1/3 verticale, 2/3 — alti quanto la pagina nel listino);
 * - Horizontal: riga a tutta la larghezza disponibile, alta in proporzione
 *   (articoli, pagine intere, mezza orizzontale, vetrina…);
 * - Bottom: riga ancorata in fondo alla pagina (piedino).
 */
enum ContentOrientation: string
{
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';
    case Bottom = 'bottom';
}
