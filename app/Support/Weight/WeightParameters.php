<?php

namespace App\Support\Weight;

/**
 * Elenco unico dei parametri del peso condivisi tra rivista (default) e
 * numero (copia), con etichette e regole di validazione — usato da
 * Issues\Create, Magazines\Edit e Timone\WeightPanel, così aggiungere un
 * parametro richiede di toccare un solo punto invece di tre form.
 */
final class WeightParameters
{
    /**
     * Colonne presenti con lo stesso nome sia su `magazines` sia su `issues`.
     */
    public const FIELDS = [
        'formato_base_mm',
        'formato_altezza_mm',
        'grammatura_interno',
        'grammatura_copertina',
        'pagine_copertina',
        'maggiorazione_percentuale',
        'peso_bancale_kg',
        'tolleranza_grammatura_perc',
        'tolleranza_formato_mm',
        'tolleranza_maggiorazione_perc',
    ];

    /**
     * Sotto-insieme mostrato nel blocco "Avanzate" (chiuso di default).
     */
    public const ADVANCED_FIELDS = [
        'maggiorazione_percentuale',
        'peso_bancale_kg',
        'tolleranza_grammatura_perc',
        'tolleranza_formato_mm',
        'tolleranza_maggiorazione_perc',
    ];

    /**
     * Colonne solo del numero (tiratura e calibrazione), mai sulla rivista.
     */
    public const ISSUE_ONLY_FIELDS = [
        'n_copie',
        'n_bancali',
        'peso_reale_g',
    ];

    /**
     * Sezione "Carta" in cima ai form: i campi che determinano il peso a
     * pagina (vedi MagazineWeightCalculator::pesoPagina) e le pagine di
     * copertina.
     */
    public const PAPER_FIELDS = [
        'grammatura_interno',
        'grammatura_copertina',
        'pagine_copertina',
    ];

    /** Massimo rappresentabile da decimal(5,2) di maggiorazione_percentuale. */
    public const MAX_MAGGIORAZIONE = 999.99;

    public const LABELS = [
        'formato_base_mm' => 'Base (mm)',
        'formato_altezza_mm' => 'Altezza (mm)',
        'grammatura_interno' => 'Grammatura interno (g/m²)',
        'grammatura_copertina' => 'Grammatura copertina (g/m²)',
        'pagine_copertina' => 'Pagine copertina',
        'maggiorazione_percentuale' => 'Maggiorazione (%)',
        'peso_bancale_kg' => 'Peso bancale (kg)',
        'tolleranza_grammatura_perc' => 'Tolleranza grammatura (± %)',
        'tolleranza_formato_mm' => 'Tolleranza formato (± mm)',
        'tolleranza_maggiorazione_perc' => 'Tolleranza maggiorazione (± punti %)',
        'n_copie' => 'N. copie (tiratura)',
        'n_bancali' => 'N. bancali',
        'peso_reale_g' => 'Peso reale misurato (g)',
        'pagine_interno' => 'Pagine interne',
    ];

    /**
     * Regole di validazione Laravel, coerenti con i tipi di colonna delle
     * migration 2026_10_26_*. Tutti nullable: un campo vuoto produce un
     * calcolo "incompleto", non un errore di salvataggio.
     *
     * Formato, grammature e pagine copertina hanno limiti di BUSINESS letti
     * da config/peso_rivista.php (non dalla colonna): sotto il minimo quasi
     * sempre c'è un errore di unità — formato in cm, peso di una pagina al
     * posto della grammatura (vedi WeightLimits e messages()).
     *
     * @return array<string, string>
     */
    public static function rules(bool $includeIssueOnly = false): array
    {
        $limits = WeightLimits::fromConfig();
        $formatRule = "nullable|integer|min:{$limits->formato->min}|max:{$limits->formato->max}";
        $grammageRule = "nullable|numeric|min:{$limits->grammatura->min}|max:{$limits->grammatura->max}";
        $coverMin = (int) config('peso_rivista.pagine_copertina.min');
        $coverMax = (int) config('peso_rivista.pagine_copertina.max');

        $rules = [
            'formato_base_mm' => $formatRule,
            'formato_altezza_mm' => $formatRule,
            'grammatura_interno' => $grammageRule,
            'grammatura_copertina' => $grammageRule,
            'pagine_copertina' => "nullable|integer|min:{$coverMin}|max:{$coverMax}|multiple_of:".MagazineWeightCalculator::PAGES_PER_SHEET,
            'maggiorazione_percentuale' => 'nullable|numeric|min:0|max:'.self::MAX_MAGGIORAZIONE,
            'peso_bancale_kg' => 'nullable|numeric|min:0|max:9999.99',
            'tolleranza_grammatura_perc' => 'nullable|numeric|min:0|max:99.99',
            'tolleranza_formato_mm' => 'nullable|numeric|min:0|max:99.99',
            'tolleranza_maggiorazione_perc' => 'nullable|numeric|min:0|max:99.99',
        ];

        if ($includeIssueOnly) {
            $rules += [
                'n_copie' => 'nullable|integer|min:0|max:4294967295',
                'n_bancali' => 'nullable|integer|min:0|max:65535',
                'peso_reale_g' => 'nullable|numeric|gt:0|max:999999.99',
            ];
        }

        return $rules;
    }

    /**
     * Messaggi di validazione specifici, con le chiavi prefissate dal
     * percorso della proprietà Livewire (es. "weight" → "weight.grammatura_interno.min").
     *
     * @return array<string, string>
     */
    public static function messages(string $prefix): array
    {
        $coverMin = (int) config('peso_rivista.pagine_copertina.min');
        $coverMax = (int) config('peso_rivista.pagine_copertina.max');
        $coverMessage = "Le pagine di copertina devono essere un numero pari tra {$coverMin} e {$coverMax}.";

        $messages = [];

        foreach (['grammatura_interno', 'grammatura_copertina'] as $field) {
            $messages["{$prefix}.{$field}.min"] = WeightLimits::GRAMMATURA_TOO_LOW;
            $messages["{$prefix}.{$field}.max"] = WeightLimits::GRAMMATURA_TOO_HIGH;
            $messages["{$prefix}.{$field}.numeric"] = 'Inserisci un numero (es. 80 o 80,5).';
        }

        foreach (['formato_base_mm', 'formato_altezza_mm'] as $field) {
            $messages["{$prefix}.{$field}.min"] = WeightLimits::FORMATO_TOO_LOW;
            $messages["{$prefix}.{$field}.max"] = WeightLimits::FORMATO_TOO_HIGH;
            $messages["{$prefix}.{$field}.integer"] = 'Inserisci il formato in millimetri interi (es. 210).';
        }

        foreach (['min', 'max', 'multiple_of', 'integer'] as $rule) {
            $messages["{$prefix}.pagine_copertina.{$rule}"] = $coverMessage;
        }

        return $messages;
    }

    /**
     * Regole già prefissate dal percorso della proprietà Livewire, pronte
     * per $this->validate($rules, WeightParameters::messages($prefix)).
     *
     * @return array<string, string>
     */
    public static function prefixedRules(string $prefix, bool $includeIssueOnly = false): array
    {
        $prefixed = [];

        foreach (self::rules($includeIssueOnly) as $field => $rule) {
            $prefixed["{$prefix}.{$field}"] = $rule;
        }

        return $prefixed;
    }

    /**
     * Accetta la virgola come separatore decimale ("80,5" → "80.5") su
     * tutti i valori di form, prima di validazione, anteprima e
     * salvataggio. Un solo punto, così "80,5" e "80.5" danno sempre lo
     * stesso valore salvato. Solo la virgola viene sostituita: un valore con
     * separatore delle migliaia ("1.234,5") resta non numerico e la
     * validazione lo rifiuta invece di indovinare.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function normalizeInput(array $values): array
    {
        return array_map(self::normalizeDecimal(...), $values);
    }

    public static function normalizeDecimal(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '.', trim($value)) : $value;
    }

    /**
     * Campi di FIELDS il cui valore di form differisce dal default della
     * rivista (badge "modificato" e pulsante "Ripristina default rivista").
     *
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    public static function modifiedFields(array $values, object $magazine): array
    {
        return array_values(array_filter(
            self::FIELDS,
            fn (string $field) => ! self::equals($values[$field] ?? '', $magazine->{$field}),
        ));
    }

    /**
     * Valori di default della rivista nel formato usato dai form Livewire
     * (stringhe, '' per "vuoto"), da copiare su un nuovo numero.
     *
     * @param  object  $source  Magazine o Issue (qualunque oggetto con le colonne di FIELDS)
     * @param  list<string>  $fields
     * @return array<string, string>
     */
    public static function formValuesFrom(object $source, array $fields = self::FIELDS): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = self::normalizeForForm($source->{$field} ?? null);
        }

        return $values;
    }

    /**
     * Da valori di form (stringhe) a valori da salvare: '' → null.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function toDatabase(array $values): array
    {
        return array_map(fn ($value) => $value === '' ? null : $value, $values);
    }

    /**
     * Rappresentazione canonica per confrontare un valore di form con il
     * default della rivista ("80" e "80.00" sono lo stesso valore).
     */
    public static function normalizeForForm(mixed $value): string
    {
        $value = self::normalizeDecimal($value);

        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return (string) (0 + $value);
        }

        return (string) $value;
    }

    public static function equals(mixed $a, mixed $b): bool
    {
        return self::normalizeForForm($a) === self::normalizeForForm($b);
    }
}
