<?php

namespace App\Support\Manual;

/**
 * «Chiedi al manuale» (2026-10-29): sceglie la pagina che risponde a una
 * domanda. Nessun modello linguistico: è la ricerca del manuale
 * (SearchEngine, le stesse regole del browser) con in più le domande di
 * domande.yml e quelle imparate dai voti come documenti cercabili.
 *
 * La risposta è SEMPRE il blocco «In breve» della pagina scelta: qui si
 * decide solo quale pagina, mai il testo.
 *
 * Punteggio di una pagina = miglior punteggio tra i suoi documenti (pagina,
 * sezioni, domande) × bonus di contesto × (1 + correzione imparata), con la
 * correzione limitata a ±0,3: i voti spostano l'ordine tra pagine vicine,
 * non possono far vincere una pagina che non c'entra.
 */
final class ManualAssistant
{
    /**
     * Domanda già nota (stessa chiave di una domanda di domande.yml o di una
     * imparata): la sua pagina vale tanto quanto la migliore trovata dalla
     * ricerca, moltiplicata per questo. Una domanda nota ha già una
     * risposta decisa; la ricerca da sola sbaglierebbe 10 domande su 262
     * (gruppi di sinonimi grandi come «pubblicità» spingono le pagine che li
     * contengono quasi tutti).
     */
    public const KNOWN_QUESTION_BONUS = 1.5;

    /** Quanto conta il punto dell'app da cui si apre l'assistente: fisso, non imparato. */
    public const CONTEXT_BONUS = 1.15;

    public const MAX_CORRECTION = 0.3;

    /**
     * Soglie, misurate il 2026-10-29 con i pesi di SearchEngine::BOOST (se
     * cambiano i pesi vanno rimisurate, vedi HANDOFF): sopra ANSWER_SCORE
     * con almeno metà delle parole trovate si risponde; sopra MAYBE_SCORE
     * si propongono tre pagine («Forse cercavi»); sotto, si suggerisce di
     * riformulare. Misure: domande vere del set di controllo da 15 a 1300
     * punti; domande fuori tema («ricetta della carbonara», «chi ha vinto
     * la partita») da 0 a 22. «cosa vuol dire il bordo giallo» (15) resta
     * così in «Forse cercavi», con la pagina giusta al primo posto.
     */
    public const ANSWER_SCORE = 60.0;

    public const ANSWER_COVERAGE = 0.5;

    public const MAYBE_SCORE = 12.0;

    /** Quante pagine si propongono in «Forse cercavi» e dopo «Non è questo». */
    public const ALTERNATIVES = 3;

    /** I sei bottoni di domanda rapida: ogni domanda è anche in domande.yml (un test lo verifica). */
    public const QUICK_QUESTIONS = [
        'come annullo l\'ultima cosa che ho fatto',
        'come metto un articolo su una pagina',
        'spostare una pagina',
        'come carico il pdf di una pagina',
        'cambiare lo stato di una pagina',
        'scrivere un commento su una pagina',
    ];

    /**
     * @param  array<string, string>  $known  chiave di domanda → pagina
     */
    public function __construct(
        private readonly SearchEngine $engine,
        private readonly QuestionKey $keys,
        private readonly array $known = [],
    ) {}

    /**
     * @param  array{documenti: list<array<string, mixed>>, sinonimi: list<list<string>>}  $index  ManualSearchIndex::build()
     * @param  list<array{domanda: string, pagina: string}>  $questions  domande iniziali e imparate
     */
    public static function build(array $index, array $questions): self
    {
        $documents = $index['documenti'];
        $keys = new QuestionKey($index['sinonimi']);
        $known = [];

        foreach (array_values($questions) as $i => $question) {
            $documents[] = ['id' => 'domanda:'.$i, 'slug' => $question['pagina'], 'domanda' => $question['domanda']];
            // In caso di chiavi uguali vince la prima (le domande iniziali
            // vengono prima delle imparate; un test vieta i doppioni in domande.yml).
            $known[$keys->of($question['domanda'])] ??= $question['pagina'];
        }

        return new self(new SearchEngine($documents, $index['sinonimi']), $keys, $known);
    }

    public function key(string $question): string
    {
        return $this->keys->of($question);
    }

    /**
     * Pagine in ordine di punteggio.
     *
     * @param  list<string>  $contextSlugs  pagine legate al punto dell'app da cui si chiede
     * @return list<array{slug: string, score: float, coverage: float}>
     */
    public function rank(string $question, array $contextSlugs = [], ?LearnedCorrections $corrections = null): array
    {
        $bySlug = [];

        foreach ($this->engine->search($question) as $result) {
            $slug = (string) $result['doc']['slug'];

            if (! isset($bySlug[$slug]) || $result['score'] > $bySlug[$slug]['score']) {
                $bySlug[$slug] = ['slug' => $slug, 'score' => $result['score'], 'coverage' => $result['coverage']];
            }
        }

        $key = $this->key($question);
        $knownSlug = $this->known[$key] ?? null;

        if ($knownSlug !== null) {
            $best = max([0.0, ...array_column($bySlug, 'score')]);
            $bySlug[$knownSlug] = [
                'slug' => $knownSlug,
                // Nessun risultato (domanda di sole parole vuote): vale giusto la soglia di risposta.
                'score' => max($best, self::ANSWER_SCORE) * self::KNOWN_QUESTION_BONUS,
                'coverage' => 1.0,
            ];
        }

        $learned = $corrections?->forKey($key) ?? [];

        foreach ($bySlug as $slug => $row) {
            $correction = max(-self::MAX_CORRECTION, min(self::MAX_CORRECTION, (float) ($learned[$slug] ?? 0)));
            $bonus = in_array($slug, $contextSlugs, true) ? self::CONTEXT_BONUS : 1.0;
            $bySlug[$slug]['score'] = $row['score'] * $bonus * (1 + $correction);
        }

        $ranked = array_values($bySlug);
        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['slug'], $b['slug']));

        return $ranked;
    }

    /**
     * Esito per l'interfaccia: «risposta» (una pagina), «forse» (tre
     * pagine da scegliere) o «niente».
     *
     * @param  list<string>  $contextSlugs
     * @return array{stato: string, chiave: string, pagina: ?string, alternative: list<string>}
     */
    public function answer(string $question, array $contextSlugs = [], ?LearnedCorrections $corrections = null): array
    {
        $ranked = $this->rank($question, $contextSlugs, $corrections);
        $top = $ranked[0] ?? null;
        $slugs = array_column($ranked, 'slug');
        $key = $this->key($question);

        if ($top !== null && $top['score'] >= self::ANSWER_SCORE && $top['coverage'] >= self::ANSWER_COVERAGE) {
            return ['stato' => 'risposta', 'chiave' => $key, 'pagina' => $top['slug'], 'alternative' => array_slice($slugs, 1, self::ALTERNATIVES)];
        }

        if ($top !== null && $top['score'] >= self::MAYBE_SCORE) {
            return ['stato' => 'forse', 'chiave' => $key, 'pagina' => null, 'alternative' => array_slice($slugs, 0, self::ALTERNATIVES)];
        }

        return ['stato' => 'niente', 'chiave' => $key, 'pagina' => null, 'alternative' => []];
    }
}
