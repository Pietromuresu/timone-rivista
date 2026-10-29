<?php

namespace App\Support\Manual;

/**
 * Motore di ricerca del manuale lato server (2026-10-29), per l'assistente
 * «Chiedi al manuale» e per il cancello del job notturno di apprendimento.
 *
 * Replica le regole della ricerca nel browser (resources/js/manual-search-core.js
 * con MiniSearch): stessi documenti, stessa normalizzazione (ItalianText),
 * BM25 di MiniSearch (k 1,2, b 0,7, d 0,5), prefisso da 3 lettere (peso
 * 0,375 decrescente con la distanza), errori di battitura da 6 lettere
 * (peso 0,45), pesi per campo, sinonimi a peso ridotto, copertura².
 * Perché una copia in PHP: il cancello del job deve rifare le domande di
 * riferimento con i pesi nuovi, e nel container di produzione c'è PHP ma
 * non node. Un test confronta i due motori sulle domande di riferimento.
 */
final class SearchEngine
{
    public const BOOST = ['titolo' => 4, 'sezione' => 3, 'parole_chiave' => 3, 'sinonimi' => 2, 'testo' => 1, 'domanda' => 12];

    public const DOCUMENT_BOOST = ['novita' => 0.4, 'note-amministratore' => 0.6, 'glossario' => 0.8];

    public const SYNONYM_WEIGHT = 0.5;

    public const SYNONYM_WEIGHT_UNKNOWN_WORD = 0.8;

    private const K = 1.2;

    private const B = 0.7;

    private const D = 0.5;

    private const PREFIX_WEIGHT = 0.375;

    private const FUZZY_WEIGHT = 0.45;

    /** @var array<string, array<string, array<int, int>>> termine → campo → documento → frequenza */
    private array $postings = [];

    /** @var array<int, array<string, int>> documento → campo → numero di termini */
    private array $lengths = [];

    /** @var array<string, float> */
    private array $averageLength = [];

    /** @var list<array<string, mixed>> */
    private array $documents;

    /** @var array<string, list<string>> radice → parole del suo gruppo di sinonimi */
    private array $synonyms = [];

    /** @var list<string> termini dell'indice in ordine (prefissi con ricerca binaria) */
    private array $sortedTerms = [];

    /** @var array<int, list<string>> lunghezza → termini (errori di battitura) */
    private array $termsByLength = [];

    /** @var array<string, array<string, float>> espansioni già calcolate: il job rifà centinaia di domande */
    private array $expanded = [];

    /**
     * @param  list<array<string, mixed>>  $documents  come ManualSearchIndex::build()['documenti'], più eventuali documenti con il campo 'domanda'
     * @param  list<list<string>>  $synonymGroups
     */
    public function __construct(array $documents, array $synonymGroups)
    {
        $this->documents = array_values($documents);

        // Come MiniSearch: la lunghezza di un campo è il numero di parole
        // DISTINTE prima della normalizzazione; la media si aggiorna documento
        // per documento e un campo null non entra nel conto.
        $average = array_fill_keys(array_keys(self::BOOST), 0.0);

        foreach ($this->documents as $i => $doc) {
            foreach (array_keys(self::BOOST) as $field) {
                if (! isset($doc[$field])) {
                    continue;
                }

                $text = (string) $doc[$field];
                $length = count(array_unique(ItalianText::words($text)));
                $this->lengths[$i][$field] = $length;
                $average[$field] = ($average[$field] * $i + $length) / ($i + 1);

                foreach (self::terms($text) as $term) {
                    $this->postings[$term][$field][$i] = ($this->postings[$term][$field][$i] ?? 0) + 1;
                }
            }
        }

        $this->averageLength = $average;
        $this->sortedTerms = array_map('strval', array_keys($this->postings));
        sort($this->sortedTerms, SORT_STRING);

        foreach ($this->sortedTerms as $term) {
            $this->termsByLength[strlen($term)][] = $term;
        }

        foreach ($synonymGroups as $group) {
            foreach ($group as $word) {
                $stem = ItalianText::stem(ItalianText::words($word)[0]);
                $this->synonyms[$stem] = array_values(array_unique(array_merge($this->synonyms[$stem] ?? [], $group)));
            }
        }
    }

    /**
     * Risultati per documento, in ordine di punteggio (come search() nel browser,
     * senza il limite per pagina).
     *
     * @return list<array{doc: array<string, mixed>, score: float, coverage: float}>
     */
    public function search(string $query): array
    {
        $words = array_values(array_filter(ItalianText::words($query), fn ($w) => ! in_array($w, ItalianText::STOPWORDS, true)));

        if ($words === []) {
            return [];
        }

        $scores = [];
        $matched = [];
        $sources = [];
        $groups = [];

        foreach ($words as $word) {
            $stem = ItalianText::stem($word);
            $alternatives = array_values(array_unique(array_merge([$word], $this->synonyms[$stem] ?? [])));
            // Come nel browser: la stringa delle alternative passa dalla stessa
            // elaborazione; il primo termine è la parola scritta.
            $terms = self::terms(implode(' ', $alternatives));
            $groups[] = array_values(array_unique(array_map(fn ($w) => ItalianText::stem($w), ItalianText::words(implode(' ', $alternatives)))));
            $unknown = $terms !== [] && ! $this->known($terms[0]);

            foreach ($terms as $i => $term) {
                $termBoost = $i === 0 ? 1.0 : ($unknown ? self::SYNONYM_WEIGHT_UNKNOWN_WORD : self::SYNONYM_WEIGHT / sqrt(count($terms) - 1));

                foreach ($this->expand($term) as $derived => $weight) {
                    foreach ($this->postings[$derived] ?? [] as $field => $docs) {
                        $matchingDocs = count($docs);

                        foreach ($docs as $doc => $frequency) {
                            $docBoost = self::DOCUMENT_BOOST[explode('#', (string) $this->documents[$doc]['id'])[0]] ?? 1.0;
                            $raw = $this->bm25($frequency, $matchingDocs, $this->lengths[$doc][$field], $this->averageLength[$field]);
                            $scores[$doc] = ($scores[$doc] ?? 0) + $weight * $termBoost * self::BOOST[$field] * $docBoost * $raw;
                            $matched[$doc][$derived] = true;
                            $sources[$doc][$term] = true;
                        }
                    }
                }
            }
        }

        $results = [];

        foreach ($scores as $doc => $score) {
            $found = 0;

            foreach ($groups as $stems) {
                foreach (array_keys($matched[$doc]) as $m) {
                    foreach ($stems as $s) {
                        if (str_starts_with($m, $s) || str_starts_with($s, $m)) {
                            $found++;

                            continue 3;
                        }
                    }
                }
            }

            $coverage = $found / count($groups);
            // MiniSearch moltiplica per quanti termini distinti della query
            // (sinonimi compresi) il documento ha trovato.
            $quality = count($sources[$doc]);
            $results[] = ['doc' => $this->documents[$doc], 'score' => $score * $quality * $coverage ** 2, 'coverage' => $coverage];
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    /**
     * Termini dell'indice raggiunti da un termine della query, con il loro
     * peso: esatto 1, prefisso (da 3 lettere) e errore di battitura (da 6
     * lettere) come MiniSearch.
     *
     * @return array<string, float>
     */
    private function expand(string $term): array
    {
        if (isset($this->expanded[$term])) {
            return $this->expanded[$term];
        }

        $out = isset($this->postings[$term]) ? [$term => 1.0] : [];
        $length = strlen($term);

        if ($length >= 3) {
            foreach ($this->withPrefix($term) as $candidate) {
                if ($candidate !== $term) {
                    $distance = strlen($candidate) - $length;
                    $out[$candidate] = self::PREFIX_WEIGHT * strlen($candidate) / (strlen($candidate) + 0.3 * $distance);
                }
            }
        }

        if ($length >= 6) {
            $maxDistance = min(6, (int) round($length * 0.2));

            for ($l = $length - $maxDistance; $l <= $length + $maxDistance; $l++) {
                foreach ($this->termsByLength[$l] ?? [] as $candidate) {
                    if (isset($out[$candidate])) {
                        continue;
                    }

                    $distance = levenshtein($term, $candidate);

                    if ($distance > 0 && $distance <= $maxDistance) {
                        $out[$candidate] = self::FUZZY_WEIGHT * strlen($candidate) / (strlen($candidate) + $distance);
                    }
                }
            }
        }

        return $this->expanded[$term] = $out;
    }

    /**
     * Termini dell'indice che iniziano con $prefix (lui compreso), con una
     * ricerca binaria sull'elenco ordinato invece di scorrerli tutti.
     *
     * @return list<string>
     */
    private function withPrefix(string $prefix): array
    {
        $low = 0;
        $high = count($this->sortedTerms);

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            strcmp($this->sortedTerms[$mid], $prefix) < 0 ? $low = $mid + 1 : $high = $mid;
        }

        $out = [];

        for ($i = $low; $i < count($this->sortedTerms) && str_starts_with($this->sortedTerms[$i], $prefix); $i++) {
            $out[] = $this->sortedTerms[$i];
        }

        return $out;
    }

    /**
     * La parola compare nel manuale, anche solo come inizio di parola? Come
     * nel browser, la radice passa di nuovo dalla normalizzazione (MiniSearch
     * la tratta come una nuova ricerca).
     */
    private function known(string $stem): bool
    {
        foreach (self::terms($stem) as $term) {
            if ($this->withPrefix($term) !== []) {
                return true;
            }
        }

        return false;
    }

    private function bm25(int $frequency, int $matching, int $length, float $average): float
    {
        $idf = log(1 + (count($this->documents) - $matching + 0.5) / ($matching + 0.5));

        return $idf * (self::D + $frequency * (self::K + 1) / ($frequency + self::K * (1 - self::B + self::B * $length / max($average, 1e-9))));
    }

    /**
     * Come tokenize + processTerm nel browser: parole, senza parole vuote, ridotte.
     *
     * @return list<string>
     */
    private static function terms(string $text): array
    {
        return ItalianText::tokens($text);
    }
}
