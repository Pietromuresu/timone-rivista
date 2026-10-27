<?php

namespace App\Support\Weight;

/**
 * Input del calcolo del peso teorico di una copia — immutabile, nessuna
 * dipendenza da Eloquent: MagazineWeightCalculator lavora solo su questo.
 *
 * Scelta numerica (vale per tutto App\Support\Weight): float nativi, mai
 * bcmath. I decimal arrivano da Eloquent come stringhe ("80.00"): vengono
 * convertiti qui, una volta sola, in fromArray(). La precisione del double
 * (~15 cifre significative) è di molti ordini di grandezza superiore a
 * quella richiesta (confronto con l'Excel a 0.0001) e nessun passaggio
 * intermedio viene arrotondato — l'arrotondamento avviene solo in
 * WeightFormatter, in visualizzazione.
 *
 * Tutti i campi sono nullable: un parametro mancante non è un errore ma un
 * risultato "incompleto" (vedi MagazineWeightResult::$missing).
 */
final readonly class MagazineWeightInput
{
    public function __construct(
        public ?float $formatoBaseMm,
        public ?float $formatoAltezzaMm,
        public ?float $grammaturaInterno,
        public ?float $grammaturaCopertina,
        public ?int $pagineInterno,
        public ?int $pagineCopertina,
        public ?float $maggiorazionePercentuale,
        public ?float $pesoBancaleKg,
        public ?float $tolleranzaGrammaturaPerc,
        public ?float $tolleranzaFormatoMm,
        public ?float $tolleranzaMaggiorazionePerc,
        public ?int $nCopie = null,
        public ?int $nBancali = null,
        public ?float $pesoRealeG = null,
    ) {}

    /**
     * Costruisce l'input dai nomi di colonna di WeightParameters::FIELDS
     * (stringhe, interi o null, così come arrivano da Eloquent o da un
     * form Livewire). Stringa vuota = null.
     *
     * @param  array<string, mixed>  $params
     */
    public static function fromArray(array $params, ?int $pagineInterno): self
    {
        $float = static fn (string $key): ?float => self::toFloat($params[$key] ?? null);
        $int = static fn (string $key): ?int => self::toInt($params[$key] ?? null);

        return new self(
            formatoBaseMm: $float('formato_base_mm'),
            formatoAltezzaMm: $float('formato_altezza_mm'),
            grammaturaInterno: $float('grammatura_interno'),
            grammaturaCopertina: $float('grammatura_copertina'),
            pagineInterno: $pagineInterno,
            pagineCopertina: $int('pagine_copertina'),
            maggiorazionePercentuale: $float('maggiorazione_percentuale'),
            pesoBancaleKg: $float('peso_bancale_kg'),
            tolleranzaGrammaturaPerc: $float('tolleranza_grammatura_perc'),
            tolleranzaFormatoMm: $float('tolleranza_formato_mm'),
            tolleranzaMaggiorazionePerc: $float('tolleranza_maggiorazione_perc'),
            nCopie: $int('n_copie'),
            nBancali: $int('n_bancali'),
            pesoRealeG: $float('peso_reale_g'),
        );
    }

    /**
     * Copia con un numero diverso di pagine interne (simulazione +/- pagine).
     */
    public function withPagineInterno(?int $pagineInterno): self
    {
        return new self(
            $this->formatoBaseMm, $this->formatoAltezzaMm, $this->grammaturaInterno, $this->grammaturaCopertina,
            $pagineInterno, $this->pagineCopertina, $this->maggiorazionePercentuale, $this->pesoBancaleKg,
            $this->tolleranzaGrammaturaPerc, $this->tolleranzaFormatoMm, $this->tolleranzaMaggiorazionePerc,
            $this->nCopie, $this->nBancali, $this->pesoRealeG,
        );
    }

    /**
     * Copia con una maggiorazione diversa (verifica della calibrazione).
     */
    public function withMaggiorazione(?float $maggiorazionePercentuale): self
    {
        return new self(
            $this->formatoBaseMm, $this->formatoAltezzaMm, $this->grammaturaInterno, $this->grammaturaCopertina,
            $this->pagineInterno, $this->pagineCopertina, $maggiorazionePercentuale, $this->pesoBancaleKg,
            $this->tolleranzaGrammaturaPerc, $this->tolleranzaFormatoMm, $this->tolleranzaMaggiorazionePerc,
            $this->nCopie, $this->nBancali, $this->pesoRealeG,
        );
    }

    private static function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
