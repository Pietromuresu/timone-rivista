<?php

namespace App\Support\Weight;

/**
 * Anteprima live del peso a partire dai valori (non ancora salvati) di un
 * form: peso copia ± margine e scaglione, peso a pagina di interno e
 * copertina, differenza rispetto al valore salvato.
 *
 * Unico punto che trasforma un form in anteprima, condiviso da creazione
 * numero (Issues\Create), modale Peso del numero (Timone\WeightPanel) e
 * impostazioni rivista (Magazines\Edit): i componenti Livewire restano un
 * livello sottile (CLAUDE.md, regola 2) e nessuno di loro ricalcola pesi —
 * tutti i numeri vengono da MagazineWeightCalculator.
 *
 * Pura come il calculator: scaglioni e intervallo grammature arrivano come
 * parametri (chi chiama legge la config).
 */
final readonly class WeightPreview
{
    /**
     * @param  array{grammatura_interno: ?PaperLine, grammatura_copertina: ?PaperLine}  $paper
     */
    public function __construct(
        public MagazineWeightResult $result,
        public array $paper,
        public ?MagazineWeightResult $saved = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values  valori di form (WeightParameters::FIELDS e opz. ISSUE_ONLY_FIELDS)
     * @param  int|null  $totalPages  pagine INTERNE del numero (Fase 2: copertine escluse); null = non note (impostazioni rivista senza pagine di prova)
     * @param  list<int|float>  $brackets
     * @param  MagazineWeightResult|null  $saved  risultato con i valori salvati, per la differenza
     */
    public static function fromForm(array $values, ?int $totalPages, array $brackets, WeightLimits $limits, ?MagazineWeightResult $saved = null): self
    {
        $values = WeightParameters::normalizeInput($values);

        // Fase 2 (2026-10-26): $totalPages sono le pagine INTERNE (le
        // copertine non fanno più parte del totale), stessa regola di
        // Issue::weightInput() — nessuna sottrazione.
        $cover = $values['pagine_copertina'] ?? '';
        $pagineCopertina = is_numeric($cover) ? (int) $cover : null;
        $pagineInterno = $totalPages;

        $input = MagazineWeightInput::fromArray($values, $pagineInterno);

        return new self(
            result: MagazineWeightCalculator::calculate($input, $brackets, $limits),
            paper: [
                'grammatura_interno' => self::paperLine($input, $input->grammaturaInterno, $pagineInterno, $limits),
                'grammatura_copertina' => self::paperLine($input, $input->grammaturaCopertina, $pagineCopertina, $limits),
            ],
            saved: $saved,
        );
    }

    /**
     * Differenza in grammi rispetto al peso salvato; null se uno dei due
     * calcoli è incompleto o non c'è un valore salvato con cui confrontare.
     */
    public function deltaG(): ?float
    {
        if ($this->saved === null || ! $this->saved->complete || ! $this->result->complete) {
            return null;
        }

        return $this->result->pesoCopiaG - $this->saved->pesoCopiaG;
    }

    /**
     * true se l'anteprima cade in uno scaglione diverso da quello salvato
     * (compreso entrare/uscire da "Fuori scaglione").
     */
    public function bracketChanged(): bool
    {
        return $this->deltaG() !== null && $this->saved->scaglione !== $this->result->scaglione;
    }

    private static function paperLine(MagazineWeightInput $in, ?float $grammatura, ?int $pagine, WeightLimits $limits): ?PaperLine
    {
        if ($grammatura === null) {
            return null;
        }

        if (! $limits->grammatura->contains($grammatura)) {
            return new PaperLine(grammatura: $grammatura, outOfRange: true, message: $limits->grammatura->messageFor($grammatura));
        }

        if ($in->formatoBaseMm === null || $in->formatoAltezzaMm === null) {
            return new PaperLine(grammatura: $grammatura);
        }

        // Formato fuori scala (tipicamente in cm): un peso a pagina 100 volte
        // più piccolo sarebbe solo fuorviante, si rimanda all'errore del formato.
        foreach ([$in->formatoBaseMm, $in->formatoAltezzaMm] as $mm) {
            if (! $limits->formato->contains($mm)) {
                return new PaperLine(grammatura: $grammatura, message: 'Peso a pagina non calcolabile: controlla il formato. '.$limits->formato->messageFor($mm));
            }
        }

        $pagine = $pagine !== null && $pagine >= 0 ? $pagine : null;

        return new PaperLine(
            grammatura: $grammatura,
            pesoPaginaG: MagazineWeightCalculator::pesoPagina($in->formatoBaseMm, $in->formatoAltezzaMm, $grammatura),
            pagine: $pagine,
            totaleG: $pagine !== null ? MagazineWeightCalculator::pesoPagine($in->formatoBaseMm, $in->formatoAltezzaMm, $grammatura, $pagine) : null,
        );
    }
}
