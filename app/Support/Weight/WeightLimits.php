<?php

namespace App\Support\Weight;

/**
 * Limiti di plausibilità dei parametri del peso, letti da
 * config/peso_rivista.php (chiavi `grammatura` e `formato`). Un solo
 * oggetto condiviso tra validazione dei form (WeightParameters::rules) e
 * calcolo (MagazineWeightCalculator::calculate), così i due controlli non
 * possono divergere.
 *
 * Esiste per gli errori di unità di misura, i più comuni e i più subdoli:
 * - grammatura scritta come peso di una pagina (0,8 invece di 80 g/m²);
 * - formato scritto in centimetri (21 × 27 invece di 210 × 270 mm).
 * Entrambi danno un peso copia di pochi grammi invece di ~500.
 */
final readonly class WeightLimits
{
    public const GRAMMATURA_TOO_LOW = 'Inserisci la grammatura della carta in g/m² (es. 80), non il peso di una pagina. Il peso a pagina viene calcolato automaticamente.';

    public const GRAMMATURA_TOO_HIGH = 'Valore troppo alto per una carta da rivista, controlla l\'unità di misura.';

    public const FORMATO_TOO_LOW = 'Inserisci il formato in millimetri (es. 210 × 270), non in centimetri.';

    public const FORMATO_TOO_HIGH = 'Valore troppo alto per il formato di una rivista, controlla l\'unità di misura (millimetri).';

    public function __construct(
        public ParameterRange $grammatura,
        public ParameterRange $formato,
    ) {}

    public static function fromConfig(): self
    {
        return self::fromValues(
            (float) config('peso_rivista.grammatura.min'),
            (float) config('peso_rivista.grammatura.max'),
            (float) config('peso_rivista.formato.min'),
            (float) config('peso_rivista.formato.max'),
        );
    }

    /**
     * Costruzione senza config (test unitari del calcolo puro).
     */
    public static function fromValues(float $grammaturaMin, float $grammaturaMax, float $formatoMin, float $formatoMax): self
    {
        return new self(
            new ParameterRange($grammaturaMin, $grammaturaMax, self::GRAMMATURA_TOO_LOW, self::GRAMMATURA_TOO_HIGH),
            new ParameterRange($formatoMin, $formatoMax, self::FORMATO_TOO_LOW, self::FORMATO_TOO_HIGH),
        );
    }
}
