<?php

namespace App\Support\Weight;

/**
 * Riga "peso a pagina" mostrata sotto un campo grammatura, es.
 * "80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g". Solo dati: i valori
 * arrivano da MagazineWeightCalculator::pesoPagina()/pesoPagine() (vedi
 * WeightPreview::fromForm), la formattazione è in Blade con WeightFormatter.
 *
 * $outOfRange: la grammatura è fuori da WeightLimits, quindi non si mostra
 * nessun peso (sarebbe plausibile ma sbagliato) ma $message.
 * $pesoPaginaG null senza $outOfRange: formato mancante (nessun $message) o
 * fuori intervallo ($message spiega cosa controllare).
 * $pagine/$totaleG null: pagine non note (es. impostazioni rivista senza
 * pagine di prova), si mostra solo il peso a pagina.
 */
final readonly class PaperLine
{
    public function __construct(
        public float $grammatura,
        public ?float $pesoPaginaG = null,
        public ?int $pagine = null,
        public ?float $totaleG = null,
        public bool $outOfRange = false,
        public ?string $message = null,
    ) {}
}
