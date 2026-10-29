<?php

namespace App\Support\Manual;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Apprendimento dai voti dell'assistente (2026-10-29), logica pura: voti
 * in ingresso, correzioni e domande imparate in uscita, niente database
 * (lo legge e lo scrive il comando manuale:apprendi). Si ricalcola SEMPRE
 * da zero da tutti i voti: nessuno stato che si accumula e può derivare.
 *
 * Regole, in ordine:
 * 1. un voto per persona per (chiave, pagina): l'ultimo;
 * 2. peso del voto = posizione × decadimento × versione:
 *    - posizione: 👍/👎 sulla risposta 1; scelta in «Forse cercavi» al
 *      primo posto 1, al secondo o terzo 1,5; pagina scelta dopo «Non è
 *      questo» 2 (è il segnale più forte: la persona ha detto quale era);
 *    - decadimento: dimezza ogni 90 giorni;
 *    - versione: se la pagina è cambiata dal voto vale ½; se è cambiato
 *      proprio l'«In breve» vale 0, perché il voto giudicava un testo che
 *      non c'è più (la risposta mostrata È l'In breve);
 * 3. si impara solo da almeno MIN_VOTES voti di almeno MIN_USERS persone;
 * 4. stima prudente con l'intervallo di Wilson al 95% sui voti pesati: il
 *    limite inferiore se la maggioranza è positiva, il superiore se è
 *    negativa; correzione = SLOPE × distanza della stima da 0,5, quindi
 *    nulla finché il risultato non è chiaro, al massimo ±0,3;
 * 5. domanda imparata: una chiave non simile a nessuna domanda iniziale
 *    con una pagina sopra soglia e correzione positiva;
 * 6. risposta probabilmente sbagliata: almeno SUSPECT_USERS persone e
 *    limite superiore di Wilson sotto SUSPECT_UPPER.
 * Pagine che non esistono più e voci spente da un admin si saltano.
 */
final class ManualLearning
{
    public const MIN_VOTES = 5;

    public const MIN_USERS = 3;

    public const HALF_LIFE_DAYS = 90;

    /** 0,6 × (distanza massima 0,5) = 0,3: la correzione massima. */
    public const SLOPE = 0.6;

    public const MAX_CORRECTION = ManualAssistant::MAX_CORRECTION;

    public const SUSPECT_USERS = 5;

    public const SUSPECT_UPPER = 0.3;

    /** Sotto questa somiglianza con ogni domanda iniziale una chiave è «nuova». */
    public const NEW_QUESTION_SIMILARITY = 0.5;

    public const WEIGHT_ANSWER = 1.0;

    public const WEIGHT_LIST_LOWER = 1.5;

    public const WEIGHT_AFTER_REJECTION = 2.0;

    private const Z = 1.96;

    /**
     * @param  list<string>  $initialKeys  chiavi delle domande di domande.yml
     * @param  array<string, array{0: string, 1: string}>  $pages  slug → [hash della pagina, hash dell'In breve] di oggi
     * @param  list<array{tipo: string, chiave: string, slug: string}>  $disabled
     */
    public function __construct(
        private readonly array $initialKeys,
        private readonly array $pages,
        private readonly array $disabled = [],
    ) {}

    /**
     * @param  iterable<array<string, mixed>>  $votes  righe di manual_feedback
     * @return array{
     *     correzioni: list<array{chiave: string, slug: string, correzione: float, voti: int, utenti: int, positivi: float, sospetta: bool}>,
     *     domande: list<array{chiave: string, domanda: string, slug: string, voti: int, utenti: int}>,
     *     voti: int
     * }
     */
    public function compute(iterable $votes, CarbonInterface $now): array
    {
        $groups = [];
        $count = 0;

        foreach ($this->lastVotePerUser($votes) as $vote) {
            $slug = $vote['slug'];

            if (! isset($this->pages[$slug])) {
                continue;
            }

            $weight = $this->weight($vote, $now);

            if ($weight <= 0) {
                continue;
            }

            $count++;
            $group = &$groups[$vote['chiave']][$slug];
            $group['n'] = ($group['n'] ?? 0) + $weight;
            $group['positive'] = ($group['positive'] ?? 0) + ($vote['voto'] > 0 ? $weight : 0);
            $group['votes'] = ($group['votes'] ?? 0) + 1;
            $group['users'][$vote['utente']] = true;

            if ($vote['voto'] > 0) {
                $group['texts'][$vote['domanda']] = ($group['texts'][$vote['domanda']] ?? 0) + 1;
            }
            unset($group);
        }

        $corrections = [];
        $questions = [];

        foreach ($groups as $key => $slugs) {
            $key = (string) $key;

            foreach ($slugs as $slug => $group) {
                $slug = (string) $slug;
                $users = count($group['users']);

                if ($group['votes'] < self::MIN_VOTES || $users < self::MIN_USERS || $this->isDisabled('correzione', $key, $slug)) {
                    continue;
                }

                $p = $group['positive'] / $group['n'];
                [, $upper] = self::wilson($p, $group['n']);
                $correction = self::correction($p, $group['n']);
                $suspect = $users >= self::SUSPECT_USERS && $upper < self::SUSPECT_UPPER;

                if ($correction == 0.0 && ! $suspect) {
                    continue;
                }

                $corrections[] = [
                    'chiave' => $key,
                    'slug' => $slug,
                    'correzione' => $correction,
                    'voti' => $group['votes'],
                    'utenti' => $users,
                    'positivi' => round($p, 4),
                    'sospetta' => $suspect,
                ];

                if ($correction > 0 && $this->isNewQuestion($key) && ! $this->isDisabled('domanda', $key, $slug)) {
                    $best = $questions[$key] ?? null;

                    if ($best === null || $correction > $best['correzione']) {
                        arsort($group['texts']);
                        $questions[$key] = [
                            'chiave' => $key,
                            'domanda' => (string) array_key_first($group['texts']),
                            'slug' => $slug,
                            'voti' => $group['votes'],
                            'utenti' => $users,
                            'correzione' => $correction,
                        ];
                    }
                }
            }
        }

        $questions = array_map(fn (array $q) => array_diff_key($q, ['correzione' => true]), array_values($questions));

        return ['correzioni' => $corrections, 'domande' => $questions, 'voti' => $count];
    }

    /**
     * Intervallo di Wilson al 95% per una proporzione $p su $n prove (anche
     * non intere: i voti pesati).
     *
     * @return array{0: float, 1: float}
     */
    public static function wilson(float $p, float $n): array
    {
        if ($n <= 0) {
            return [0.0, 1.0];
        }

        $z2 = self::Z ** 2;
        $center = $p + $z2 / (2 * $n);
        $margin = self::Z * sqrt($p * (1 - $p) / $n + $z2 / (4 * $n * $n));
        $denominator = 1 + $z2 / $n;

        return [max(0.0, ($center - $margin) / $denominator), min(1.0, ($center + $margin) / $denominator)];
    }

    /** Correzione per una proporzione di voti positivi $p su $n voti pesati. */
    public static function correction(float $p, float $n): float
    {
        [$lower, $upper] = self::wilson($p, $n);
        $deviation = $p >= 0.5 ? max(0.0, $lower - 0.5) : -max(0.0, 0.5 - $upper);

        return round(max(-self::MAX_CORRECTION, min(self::MAX_CORRECTION, self::SLOPE * $deviation)), 4);
    }

    /** Peso del decadimento dopo $days giorni. */
    public static function decay(float $days): float
    {
        return 0.5 ** (max(0.0, $days) / self::HALF_LIFE_DAYS);
    }

    /**
     * Peso di un voto: posizione × decadimento × versione della pagina.
     *
     * @param  array<string, mixed>  $vote  normalizzato da lastVotePerUser()
     */
    public function weight(array $vote, CarbonInterface $now): float
    {
        [$pageHash, $inBreveHash] = $this->pages[$vote['slug']];

        $version = match (true) {
            $vote['hash_in_breve'] !== $inBreveHash => 0.0,
            $vote['hash_versione'] !== $pageHash => 0.5,
            default => 1.0,
        };

        $position = match (true) {
            $vote['su_alternativa'] && $vote['slug_mostrato'] !== null => self::WEIGHT_AFTER_REJECTION,
            $vote['posizione'] > 1 => self::WEIGHT_LIST_LOWER,
            default => self::WEIGHT_ANSWER,
        };

        $days = $vote['created_at']->diffInSeconds($now) / 86400;

        return $position * self::decay($days) * $version;
    }

    /**
     * Regola 1: per ogni persona, chiave e pagina votata conta solo l'ultimo voto.
     *
     * @param  iterable<array<string, mixed>>  $votes
     * @return list<array<string, mixed>>
     */
    private function lastVotePerUser(iterable $votes): array
    {
        $last = [];

        foreach ($votes as $vote) {
            $vote = (array) $vote;
            $vote['slug'] = $vote['slug_scelto'] ?? $vote['slug_mostrato'];
            $vote['su_alternativa'] = (bool) $vote['su_alternativa'];
            $vote['voto'] = (int) $vote['voto'];
            $vote['posizione'] = (int) $vote['posizione'];
            $vote['created_at'] = $vote['created_at'] instanceof CarbonInterface ? $vote['created_at'] : Carbon::parse($vote['created_at']);

            if ($vote['slug'] === null) {
                continue;
            }

            $id = $vote['utente']."\0".$vote['chiave']."\0".$vote['slug'];
            $previous = $last[$id] ?? null;

            if ($previous === null
                || $vote['created_at']->greaterThan($previous['created_at'])
                || ($vote['created_at']->equalTo($previous['created_at']) && ($vote['id'] ?? 0) > ($previous['id'] ?? 0))) {
                $last[$id] = $vote;
            }
        }

        return array_values($last);
    }

    private function isNewQuestion(string $key): bool
    {
        foreach ($this->initialKeys as $initial) {
            if (QuestionKey::similarity($key, $initial) >= self::NEW_QUESTION_SIMILARITY) {
                return false;
            }
        }

        return true;
    }

    private function isDisabled(string $type, string $key, string $slug): bool
    {
        foreach ($this->disabled as $item) {
            if ($item['tipo'] === $type && $item['chiave'] === $key && $item['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }
}
