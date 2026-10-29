<?php

namespace App\Support\Manual;

/**
 * Cancello del job notturno (2026-10-29): prima di pubblicare i pesi
 * nuovi rifà tutte le domande di riferimento (domande.yml e
 * domande-di-controllo.yml) con quei pesi. Se anche una sola non trova più
 * la sua pagina tra le prime tre, i pesi nuovi non si pubblicano: restano
 * quelli di prima e i fallimenti finiscono nel log e nella pagina
 * «Cosa chiedono gli utenti». Così voti sbagliati, in buona fede o no, non
 * possono rompere le risposte che sappiamo giuste.
 */
final class ManualLearningGate
{
    public const TOP = 3;

    /**
     * @param  array{documenti: list<array<string, mixed>>, sinonimi: list<list<string>>}  $index
     * @param  list<array{domanda: string, pagina: string}>  $initialQuestions  domande.yml
     * @param  list<array{domanda: string, pagina: string}>  $controlQuestions  domande-di-controllo.yml
     */
    public function __construct(
        private readonly array $index,
        private readonly array $initialQuestions,
        private readonly array $controlQuestions,
    ) {}

    /**
     * @param  list<array{domanda: string, slug: string}>  $learnedQuestions
     * @param  list<array{chiave: string, slug: string, correzione: float}>  $corrections
     * @return list<array{domanda: string, attesa: string, primi: list<string>}> vuota = si può pubblicare
     */
    public function failures(array $learnedQuestions, array $corrections): array
    {
        $questions = $this->initialQuestions;

        foreach ($learnedQuestions as $learned) {
            $questions[] = ['domanda' => $learned['domanda'], 'pagina' => $learned['slug']];
        }

        $byKey = [];

        foreach ($corrections as $correction) {
            $byKey[$correction['chiave']][$correction['slug']] = (float) $correction['correzione'];
        }

        $assistant = ManualAssistant::build($this->index, $questions);
        $weights = new ArrayLearnedCorrections($byKey);
        $failures = [];

        foreach ([...$this->initialQuestions, ...$this->controlQuestions] as $reference) {
            $top = array_column(array_slice($assistant->rank($reference['domanda'], [], $weights), 0, self::TOP), 'slug');

            if (! in_array($reference['pagina'], $top, true)) {
                $failures[] = ['domanda' => $reference['domanda'], 'attesa' => $reference['pagina'], 'primi' => $top];
            }
        }

        return $failures;
    }
}
