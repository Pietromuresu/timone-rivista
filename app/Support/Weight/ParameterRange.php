<?php

namespace App\Support\Weight;

/**
 * Intervallo ammesso (limiti inclusi) per un parametro del peso, con i
 * messaggi da mostrare sotto e sopra. Generico di proposito: grammature e
 * formato hanno lo stesso problema (un errore di unità di misura — peso di
 * una pagina invece della grammatura, cm invece di mm — produce un peso
 * plausibile a colpo d'occhio ma sbagliato di 100×) e lo stesso rimedio.
 * Le istanze concrete sono in WeightLimits.
 */
final readonly class ParameterRange
{
    public function __construct(
        public float $min,
        public float $max,
        public string $tooLowMessage,
        public string $tooHighMessage,
    ) {}

    public function contains(float $value): bool
    {
        return $value >= $this->min && $value <= $this->max;
    }

    /**
     * Messaggio adatto al valore: sotto il minimo o sopra il massimo.
     */
    public function messageFor(float $value): string
    {
        return $value < $this->min ? $this->tooLowMessage : $this->tooHighMessage;
    }
}
