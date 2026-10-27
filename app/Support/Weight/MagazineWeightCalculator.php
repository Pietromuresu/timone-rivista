<?php

namespace App\Support\Weight;

/**
 * Peso teorico di una copia della rivista, con margine d'errore, scaglione
 * di spedizione e peso della tiratura — riproduce esattamente le formule
 * del foglio Excel in uso in redazione:
 *
 *   peso_interno_g   = base_cm × altezza_cm × grammatura_interno   / 10000 × (pagine_interno   / 2)
 *   peso_copertina_g = base_cm × altezza_cm × grammatura_copertina / 10000 × (pagine_copertina / 2)
 *   peso_copia_g     = (peso_interno_g + peso_copertina_g) × (1 + maggiorazione_percentuale / 100)
 *   peso_netto_kg    = peso_copia_g × n_copie / 1000
 *   peso_lordo_kg    = peso_netto_kg + n_bancali × peso_bancale_kg
 *
 * (cm² × g/m² / 10000 = grammi di un foglio; / 2 perché un foglio fisico ha
 * due facciate, cioè due pagine.)
 *
 * Pura: nessuna query, nessun config(), nessuna dipendenza da Eloquent o
 * Livewire — stesso pattern di AdLoadCalculator/PageReorderer. Gli
 * scaglioni arrivano come parametro (chi chiama legge
 * config('peso_rivista.scaglioni')), così i test non dipendono dalla config.
 *
 * Anche i limiti di plausibilità di grammature e formato (WeightLimits)
 * arrivano come parametro: chi chiama da codice applicativo passa sempre
 * WeightLimits::fromConfig(), così un dato sporco già nel DB (grammatura
 * 0,8 inserita come "peso di una pagina", formato 21 × 27 in cm) dà un
 * risultato incompleto con errore esplicito invece di un peso plausibile ma
 * sbagliato. null = nessun controllo di intervallo (solo i test unitari del
 * calcolo puro).
 *
 * Le costanti sotto sono SOLO conversioni di unità di misura, non parametri
 * di business: tutto ciò che la redazione può voler cambiare (maggiorazione,
 * peso bancale, tolleranze, scaglioni) arriva da parametri o config.
 */
final class MagazineWeightCalculator
{
    public const MM_PER_CM = 10;

    public const CM2_PER_M2 = 10000;

    /** Facciate (pagine) per foglio fisico. */
    public const PAGES_PER_SHEET = 2;

    public const GRAMS_PER_KG = 1000;

    private const PERCENT = 100;

    /**
     * Arrotondamento tecnico prima del ceil() del margine: evita che il
     * rumore del double (es. 30.000000000004) faccia salire di un grammo un
     * margine esattamente intero. 9 decimali sono comunque milioni di volte
     * sotto la sensibilità di qualunque bilancia.
     */
    private const CEIL_GUARD_DECIMALS = 9;

    /**
     * @param  list<int|float>  $brackets  limiti superiori degli scaglioni in grammi
     */
    public static function calculate(MagazineWeightInput $in, array $brackets, ?WeightLimits $limits = null): MagazineWeightResult
    {
        $missing = self::missing($in);

        if ($missing !== []) {
            return new MagazineWeightResult(input: $in, complete: false, missing: $missing);
        }

        $warnings = self::warnings($in);
        $errors = self::errors($in, $limits);

        if ($errors !== []) {
            return new MagazineWeightResult(input: $in, complete: false, errors: $errors, warnings: $warnings);
        }

        $tolFormato = $in->tolleranzaFormatoMm;
        $tolGrammatura = $in->tolleranzaGrammaturaPerc / self::PERCENT;
        $tolMaggiorazione = $in->tolleranzaMaggiorazionePerc;

        $nominal = self::weights(
            $in->formatoBaseMm, $in->formatoAltezzaMm,
            $in->grammaturaInterno, $in->grammaturaCopertina,
            $in->pagineInterno, $in->pagineCopertina,
            $in->maggiorazionePercentuale,
        );

        // Margine come CASO PEGGIORE: formula completa ricalcolata con tutti
        // i parametri incerti al massimo e poi tutti al minimo — non
        // un'approssimazione lineare (somma delle derivate), che
        // sottostimerebbe l'effetto combinato di formato × grammatura.
        $max = self::weights(
            $in->formatoBaseMm + $tolFormato, $in->formatoAltezzaMm + $tolFormato,
            $in->grammaturaInterno * (1 + $tolGrammatura), $in->grammaturaCopertina * (1 + $tolGrammatura),
            $in->pagineInterno, $in->pagineCopertina,
            $in->maggiorazionePercentuale + $tolMaggiorazione,
        );

        $min = self::weights(
            $in->formatoBaseMm - $tolFormato, $in->formatoAltezzaMm - $tolFormato,
            $in->grammaturaInterno * (1 - $tolGrammatura), $in->grammaturaCopertina * (1 - $tolGrammatura),
            $in->pagineInterno, $in->pagineCopertina,
            $in->maggiorazionePercentuale - $tolMaggiorazione,
        );

        $pesoCopia = $nominal['copia'];
        $erroreSopra = $max['copia'] - $pesoCopia;
        $erroreSotto = $pesoCopia - $min['copia'];
        $margine = (int) ceil(round(max($erroreSopra, $erroreSotto), self::CEIL_GUARD_DECIMALS));

        $sortedBrackets = self::sortedBrackets($brackets);
        $scaglione = self::bracketFor($pesoCopia, $sortedBrackets);
        $scaglionePeggiore = self::bracketFor($max['copia'], $sortedBrackets);
        $scaglionePrecedente = self::previousBracket($scaglione, $sortedBrackets);

        $nettoKg = $in->nCopie !== null ? $pesoCopia * $in->nCopie / self::GRAMS_PER_KG : null;
        $nettoMaxKg = $in->nCopie !== null ? $max['copia'] * $in->nCopie / self::GRAMS_PER_KG : null;
        $lordoKg = $nettoKg !== null && $in->nBancali !== null && $in->pesoBancaleKg !== null
            ? $nettoKg + $in->nBancali * $in->pesoBancaleKg
            : null;

        $scostamentoG = null;
        $scostamentoPerc = null;
        $maggiorazioneSuggerita = null;

        if ($in->pesoRealeG !== null) {
            $scostamentoG = $in->pesoRealeG - $pesoCopia;
            $scostamentoPerc = $scostamentoG / $pesoCopia * self::PERCENT;
            $maggiorazioneSuggerita = self::suggestedSurcharge($in->pesoRealeG, $nominal['interno'] + $nominal['copertina']);
        }

        return new MagazineWeightResult(
            input: $in,
            complete: true,
            warnings: $warnings,
            pesoInternoG: $nominal['interno'],
            pesoCopertinaG: $nominal['copertina'],
            pesoMaggiorazioneG: $pesoCopia - $nominal['interno'] - $nominal['copertina'],
            pesoCopiaG: $pesoCopia,
            pesoMinG: $min['copia'],
            pesoMaxG: $max['copia'],
            erroreSopraG: $erroreSopra,
            erroreSottoG: $erroreSotto,
            margineG: $margine,
            scaglione: $scaglione,
            scaglionePeggiore: $scaglionePeggiore,
            fuoriScaglione: $scaglione === null,
            aRischio: $scaglionePeggiore !== $scaglione,
            grammiAlLimite: $scaglione !== null ? $scaglione - $pesoCopia : null,
            scaglionePrecedente: $scaglionePrecedente,
            grammiOltreScaglionePrecedente: $scaglionePrecedente !== null ? $pesoCopia - $scaglionePrecedente : null,
            pesoNettoKg: $nettoKg,
            pesoLordoKg: $lordoKg,
            pesoNettoMaxKg: $nettoMaxKg,
            scostamentoRealeG: $scostamentoG,
            scostamentoRealePerc: $scostamentoPerc,
            maggiorazioneSuggerita: $maggiorazioneSuggerita,
        );
    }

    /**
     * Primo scaglione il cui limite è >= al peso (limite inclusivo: 500,0 g
     * → 500). null = oltre l'ultimo limite ("Fuori scaglione").
     *
     * @param  list<int|float>  $brackets
     */
    public static function bracketFor(float $grams, array $brackets): ?int
    {
        foreach (self::sortedBrackets($brackets) as $limit) {
            if ($grams <= $limit) {
                return (int) $limit;
            }
        }

        return null;
    }

    /**
     * Peso in grammi di UNA pagina (una facciata) di carta del formato e
     * della grammatura dati: mezzo foglio, perché un foglio ha due facciate.
     * Es. 210×270 mm a 80 g/m² → 2,268 g; a 170 g/m² → 4,8195 g.
     *
     * È il mattone dell'unica formula del peso (weights() la usa per
     * interno e copertina): chi deve mostrare il "peso a pagina" chiama
     * questo metodo invece di ricalcolarlo.
     */
    public static function pesoPagina(float $baseMm, float $altezzaMm, float $grammatura): float
    {
        $areaCm2 = ($baseMm / self::MM_PER_CM) * ($altezzaMm / self::MM_PER_CM);

        return $areaCm2 * $grammatura / self::CM2_PER_M2 / self::PAGES_PER_SHEET;
    }

    /**
     * Peso di $pagine pagine della stessa carta (senza maggiorazione).
     */
    public static function pesoPagine(float $baseMm, float $altezzaMm, float $grammatura, int $pagine): float
    {
        return self::pesoPagina($baseMm, $altezzaMm, $grammatura) * $pagine;
    }

    /**
     * Maggiorazione (in punti percentuali) che, applicata a interno +
     * copertina, avrebbe dato esattamente il peso reale misurato.
     */
    public static function suggestedSurcharge(float $pesoRealeG, float $pesoCartaG): float
    {
        return ($pesoRealeG / $pesoCartaG - 1) * self::PERCENT;
    }

    /**
     * Simulazione "+/- N pagine interne": stesso calcolo su una copia
     * dell'input, nessuna scrittura.
     *
     * @param  list<int|float>  $brackets
     */
    public static function simulate(MagazineWeightInput $in, int $deltaPages, array $brackets, ?WeightLimits $limits = null): MagazineWeightResult
    {
        return self::calculate($in->withPagineInterno(($in->pagineInterno ?? 0) + $deltaPages), $brackets, $limits);
    }

    /**
     * Quante pagine interne si possono ancora aggiungere (a passi di un
     * foglio, PAGES_PER_SHEET) restando nello scaglione nominale attuale:
     * 'nominale' guardando il peso nominale, 'sicure' guardando il peso
     * massimo (margine compreso). null se il calcolo è incompleto o il
     * numero è già fuori scaglione.
     *
     * @param  list<int|float>  $brackets
     * @return array{nominale: int, sicure: int}|null
     */
    public static function addablePages(MagazineWeightInput $in, array $brackets, ?WeightLimits $limits = null): ?array
    {
        $current = self::calculate($in, $brackets, $limits);

        if (! $current->complete || $current->scaglione === null) {
            return null;
        }

        $limit = (float) $current->scaglione;

        return [
            'nominale' => self::addableSheets($in, $brackets, $limit, $current->pesoCopiaG, fn (MagazineWeightResult $r) => $r->pesoCopiaG) * self::PAGES_PER_SHEET,
            'sicure' => self::addableSheets($in, $brackets, $limit, $current->pesoMaxG, fn (MagazineWeightResult $r) => $r->pesoMaxG) * self::PAGES_PER_SHEET,
        ];
    }

    /**
     * Fogli aggiungibili prima di superare $limit. Il peso è lineare nel
     * numero di fogli: si stima con una divisione e poi si corregge di ±1
     * ricalcolando la formula vera, per non dipendere dall'arrotondamento
     * del double proprio sul bordo dello scaglione.
     *
     * @param  list<int|float>  $brackets
     * @param  callable(MagazineWeightResult): float  $weightOf
     */
    private static function addableSheets(MagazineWeightInput $in, array $brackets, float $limit, float $currentWeight, callable $weightOf): int
    {
        if ($currentWeight > $limit) {
            return 0;
        }

        $weightWith = fn (int $sheets): float => $weightOf(self::simulate($in, $sheets * self::PAGES_PER_SHEET, $brackets));
        $perSheet = $weightWith(1) - $currentWeight;

        if ($perSheet <= 0) {
            return 0;
        }

        $sheets = (int) floor(($limit - $currentWeight) / $perSheet);

        while ($sheets > 0 && $weightWith($sheets) > $limit) {
            $sheets--;
        }

        while ($weightWith($sheets + 1) <= $limit) {
            $sheets++;
        }

        return $sheets;
    }

    /**
     * @return array{interno: float, copertina: float, copia: float}
     */
    private static function weights(
        float $baseMm,
        float $altezzaMm,
        float $grammaturaInterno,
        float $grammaturaCopertina,
        int $pagineInterno,
        int $pagineCopertina,
        float $maggiorazionePercentuale,
    ): array {
        $interno = self::pesoPagine($baseMm, $altezzaMm, $grammaturaInterno, $pagineInterno);
        $copertina = self::pesoPagine($baseMm, $altezzaMm, $grammaturaCopertina, $pagineCopertina);

        return [
            'interno' => $interno,
            'copertina' => $copertina,
            'copia' => ($interno + $copertina) * (1 + $maggiorazionePercentuale / self::PERCENT),
        ];
    }

    /**
     * Parametri indispensabili per il peso della copia. Copie, bancali e
     * peso bancale NON lo sono: senza, manca solo il peso della tiratura.
     *
     * @return list<string>
     */
    private static function missing(MagazineWeightInput $in): array
    {
        $required = [
            'formato_base_mm' => $in->formatoBaseMm,
            'formato_altezza_mm' => $in->formatoAltezzaMm,
            'grammatura_interno' => $in->grammaturaInterno,
            'grammatura_copertina' => $in->grammaturaCopertina,
            'pagine_interno' => $in->pagineInterno,
            'pagine_copertina' => $in->pagineCopertina,
            'maggiorazione_percentuale' => $in->maggiorazionePercentuale,
            'tolleranza_grammatura_perc' => $in->tolleranzaGrammaturaPerc,
            'tolleranza_formato_mm' => $in->tolleranzaFormatoMm,
            'tolleranza_maggiorazione_perc' => $in->tolleranzaMaggiorazionePerc,
        ];

        return array_keys(array_filter($required, fn ($value) => $value === null));
    }

    /**
     * Errori bloccanti: con questi valori la formula darebbe un peso privo
     * di senso fisico (mezzo foglio, dimensioni negative nel caso minimo).
     *
     * @return list<string>
     */
    private static function errors(MagazineWeightInput $in, ?WeightLimits $limits): array
    {
        $errors = [];

        if ($limits !== null) {
            $errors = [
                ...self::rangeErrors(['Formato base' => $in->formatoBaseMm, 'Formato altezza' => $in->formatoAltezzaMm], $limits->formato, 'mm'),
                ...self::rangeErrors(['Grammatura interno' => $in->grammaturaInterno, 'Grammatura copertina' => $in->grammaturaCopertina], $limits->grammatura, 'g/m²'),
            ];
        } else {
            if ($in->formatoBaseMm <= 0 || $in->formatoAltezzaMm <= 0) {
                $errors[] = 'Il formato deve avere base e altezza maggiori di zero.';
            }

            if ($in->grammaturaInterno <= 0 || $in->grammaturaCopertina <= 0) {
                $errors[] = 'Le grammature devono essere maggiori di zero.';
            }
        }

        if ($in->pagineInterno < 0) {
            $errors[] = 'Il numero ha meno pagine totali delle pagine di copertina impostate: le pagine interne risulterebbero negative.';
        } elseif ($in->pagineInterno % self::PAGES_PER_SHEET !== 0) {
            $errors[] = "Le pagine interne ({$in->pagineInterno}) devono essere un numero pari: un foglio ha due facciate.";
        }

        if ($in->pagineCopertina % self::PAGES_PER_SHEET !== 0) {
            $errors[] = "Le pagine di copertina ({$in->pagineCopertina}) devono essere un numero pari: un foglio ha due facciate.";
        }

        if ($in->tolleranzaGrammaturaPerc < 0 || $in->tolleranzaFormatoMm < 0 || $in->tolleranzaMaggiorazionePerc < 0) {
            $errors[] = 'Le tolleranze non possono essere negative.';
        }

        if ($in->maggiorazionePercentuale - $in->tolleranzaMaggiorazionePerc < 0) {
            $errors[] = 'La tolleranza sulla maggiorazione non può superare la maggiorazione stessa.';
        }

        if ($in->tolleranzaFormatoMm >= min($in->formatoBaseMm, $in->formatoAltezzaMm)) {
            $errors[] = 'La tolleranza sul formato è più grande del formato stesso.';
        }

        if ($in->tolleranzaGrammaturaPerc >= self::PERCENT) {
            $errors[] = 'La tolleranza sulla grammatura deve essere inferiore al 100%.';
        }

        return $errors;
    }

    /**
     * Un errore per ogni valore fuori intervallo, es. "Grammatura interno
     * 0,8 g/m² fuori dall'intervallo ammesso (30–400 g/m²). Inserisci…".
     *
     * @param  array<string, float>  $values  etichetta => valore
     * @return list<string>
     */
    private static function rangeErrors(array $values, ParameterRange $range, string $unit): array
    {
        $errors = [];

        foreach ($values as $label => $value) {
            if (! $range->contains($value)) {
                $errors[] = sprintf(
                    '%s %s %s fuori dall\'intervallo ammesso (%s–%s %s). %s',
                    $label,
                    WeightFormatter::parameter($value),
                    $unit,
                    WeightFormatter::parameter($range->min),
                    WeightFormatter::parameter($range->max),
                    $unit,
                    $range->messageFor($value),
                );
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private static function warnings(MagazineWeightInput $in): array
    {
        $warnings = [];
        $signature = self::PAGES_PER_SHEET * 2;

        if ($in->pagineInterno !== null && $in->pagineInterno > 0 && $in->pagineInterno % $signature !== 0) {
            $warnings[] = "Le pagine interne ({$in->pagineInterno}) non sono un multiplo di {$signature}: in stampa si lavora a segnature, verifica con la tipografia.";
        }

        // Fase 2 (2026-10-26): tolto l'avviso "pagine con ruolo copertina
        // nel timone ≠ parametro Pagine copertina": il parametro ora deriva
        // dalle copertine esistenti (Issue::coverPageCount()), non possono
        // più divergere.

        if ($in->nBancali !== null && $in->pesoBancaleKg === null) {
            $warnings[] = 'Peso del bancale non impostato: il peso lordo della tiratura non è calcolabile.';
        }

        return $warnings;
    }

    /**
     * @param  list<int|float>  $brackets
     * @return list<float>
     */
    private static function sortedBrackets(array $brackets): array
    {
        $sorted = array_map('floatval', array_values($brackets));
        sort($sorted);

        return $sorted;
    }

    /**
     * @param  list<float>  $sortedBrackets
     */
    private static function previousBracket(?int $bracket, array $sortedBrackets): ?int
    {
        $previous = null;

        foreach ($sortedBrackets as $limit) {
            if ($bracket !== null && $limit >= $bracket) {
                break;
            }
            $previous = (int) $limit;
        }

        return $previous;
    }
}
