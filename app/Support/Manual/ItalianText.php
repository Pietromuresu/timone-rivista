<?php

namespace App\Support\Manual;

/**
 * Normalizzazione del testo italiano per la ricerca nel manuale
 * (2026-10-29): minuscole, accenti tolti, parole vuote tolte, desinenze
 * ridotte con poche regole (sposta, spostare, spostamento → «spost»).
 * Niente libreria di stemming: un elenco corto di desinenze basta per i
 * testi del manuale e resta prevedibile.
 *
 * La stessa normalizzazione la fa il browser (resources/js/manual-search-core.js)
 * per la query: STOPWORDS e SUFFIXES arrivano al browser nel JSON
 * dell'indice, così le regole sono scritte in un posto solo; un test
 * controlla che le due implementazioni diano gli stessi risultati
 * (tests/fixtures/manual-text.json). Dalla Fase 3 la usa anche il server
 * per la «chiave di domanda».
 */
final class ItalianText
{
    /** Accentate → lettera semplice (mappa esplicita, uguale nel JavaScript). */
    public const FOLD = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    /** Parole che non dicono nulla dell'argomento: non si cercano. */
    public const STOPWORDS = [
        'a', 'ad', 'al', 'alla', 'alle', 'allo', 'ai', 'agli', 'all', 'anche', 'c', 'che', 'chi', 'ci', 'come', 'con', 'cosa', 'cui',
        'd', 'da', 'dal', 'dalla', 'dalle', 'dallo', 'dai', 'dagli', 'dall', 'del', 'della', 'delle', 'dello', 'dei', 'degli', 'dell',
        'di', 'devo', 'dove', 'e', 'ed', 'faccio', 'fare', 'gli', 'ha', 'hanno', 'ho', 'i', 'il', 'in', 'io', 'l', 'la', 'le', 'lo',
        'ma', 'mi', 'ne', 'non', 'nei', 'nel', 'nella', 'nelle', 'nello', 'negli', 'nell', 'o', 'per', 'perche', 'piu', 'posso', 'puo',
        'quale', 'quali', 'quando', 'quanto', 'questo', 'questa', 'se', 'si', 'sono', 'su', 'sul', 'sulla', 'sulle', 'sullo', 'sui',
        'sugli', 'sull', 'ti', 'tra', 'un', 'una', 'uno', 'vorrei', 'voglio',
    ];

    /** Desinenze tolte, dalla più lunga; resta sempre una radice di almeno MIN_STEM lettere. */
    public const SUFFIXES = [
        'itaria', 'itarie', 'itario', 'itari', 'amenti', 'amento', 'imenti', 'imento', 'azioni', 'azione', 'mente', 'abile', 'ibile', 'ando', 'endo',
        'iamo', 'ita', 'ato', 'ata', 'ati', 'ate', 'ito', 'iti', 'ite', 'uto', 'uta', 'uti', 'ute',
        'are', 'ere', 'ire', 'ano', 'ono', 'i', 'e', 'a', 'o',
    ];

    public const MIN_STEM = 3;

    /**
     * Le parole significative del testo, normalizzate e ridotte.
     *
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $tokens = [];

        foreach (self::words($text) as $word) {
            if (! in_array($word, self::STOPWORDS, true)) {
                $tokens[] = self::stem($word);
            }
        }

        return $tokens;
    }

    /**
     * Parole del testo in minuscolo e senza accenti, parole vuote comprese.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $folded = strtr(mb_strtolower($text), self::FOLD);

        return array_values(array_filter(preg_split('/[^a-z0-9]+/', $folded) ?: [], fn ($word) => $word !== ''));
    }

    public static function stem(string $word): string
    {
        if (preg_match('/\d/', $word) || strlen($word) <= self::MIN_STEM) {
            return $word;
        }

        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= self::MIN_STEM) {
                return substr($word, 0, -strlen($suffix));
            }
        }

        return $word;
    }
}
