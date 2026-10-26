<?php

namespace App\Support\Weight;

/**
 * Risultato del calcolo del peso — immutabile. Tutti i pesi sono valori
 * NON arrotondati (l'arrotondamento è solo in WeightFormatter), tranne
 * $margineG, che per definizione è già arrotondato per eccesso al grammo.
 *
 * Quando $complete è false (parametri mancanti o errori bloccanti) i campi
 * numerici sono null: chi lo mostra deve leggere $missing/$errors invece
 * di trattare un null come zero.
 *
 * Scaglioni: null significa "Fuori scaglione" (peso oltre l'ultimo limite
 * di config/peso_rivista.php).
 */
final readonly class MagazineWeightResult
{
    /**
     * @param  list<string>  $missing  nomi di colonna (WeightParameters::FIELDS) o "pagine_interno"
     * @param  list<string>  $errors  errori bloccanti, testo per l'utente
     * @param  list<string>  $warnings  avvisi non bloccanti, testo per l'utente
     */
    public function __construct(
        public MagazineWeightInput $input,
        public bool $complete,
        public array $missing = [],
        public array $errors = [],
        public array $warnings = [],
        public ?float $pesoInternoG = null,
        public ?float $pesoCopertinaG = null,
        public ?float $pesoMaggiorazioneG = null,
        public ?float $pesoCopiaG = null,
        public ?float $pesoMinG = null,
        public ?float $pesoMaxG = null,
        public ?float $erroreSopraG = null,
        public ?float $erroreSottoG = null,
        public ?int $margineG = null,
        public ?int $scaglione = null,
        public ?int $scaglionePeggiore = null,
        public bool $fuoriScaglione = false,
        public bool $aRischio = false,
        public ?float $grammiAlLimite = null,
        public ?int $scaglionePrecedente = null,
        public ?float $grammiOltreScaglionePrecedente = null,
        public ?float $pesoNettoKg = null,
        public ?float $pesoLordoKg = null,
        public ?float $pesoNettoMaxKg = null,
        public ?float $scostamentoRealeG = null,
        public ?float $scostamentoRealePerc = null,
        public ?float $maggiorazioneSuggerita = null,
    ) {}
}
