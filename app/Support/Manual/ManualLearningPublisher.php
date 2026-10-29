<?php

namespace App\Support\Manual;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Un giro del job notturno (2026-10-29), la parte con il database:
 * legge tutti i voti, ricalcola da zero (ManualLearning), passa dal
 * cancello (ManualLearningGate) e solo se il cancello è verde sostituisce,
 * in una transazione, correzioni e domande imparate. In ogni caso registra
 * il giro in manual_learning_runs e una riga nel log.
 *
 * Separato dal comando (manuale:apprendi) perché i test lo chiamano
 * direttamente, con una data a scelta per provare il decadimento.
 */
final class ManualLearningPublisher
{
    public function __construct(private readonly ManualAssistantFactory $factory) {}

    public static function fromConfig(): self
    {
        return new self(ManualAssistantFactory::fromConfig());
    }

    /**
     * @return array{pubblicato: bool, voti: int, correzioni: int, domande_imparate: int, sospette: int, fallimenti: list<array{domanda: string, attesa: string, primi: list<string>}>}
     */
    public function run(CarbonInterface $now): array
    {
        $disabled = DB::table('manual_disabled')->get(['tipo', 'chiave', 'slug'])
            ->map(fn ($row) => ['tipo' => $row->tipo, 'chiave' => $row->chiave, 'slug' => $row->slug])->all();

        $learning = new ManualLearning($this->factory->initialKeys(), $this->factory->pageHashes(), $disabled);
        $votes = DB::table('manual_feedback')->lazyById(1000)->map(fn ($row) => (array) $row);
        $result = $learning->compute($votes, $now);

        $failures = $this->factory->gate()->failures($result['domande'], $result['correzioni']);
        $suspects = count(array_filter($result['correzioni'], fn (array $c) => $c['sospetta']));

        $summary = [
            'pubblicato' => $failures === [],
            'voti' => $result['voti'],
            'correzioni' => count($result['correzioni']),
            'domande_imparate' => count($result['domande']),
            'sospette' => $suspects,
            'fallimenti' => $failures,
        ];

        DB::transaction(function () use ($result, $summary, $now) {
            if ($summary['pubblicato']) {
                DB::table('manual_learned_boosts')->delete();
                DB::table('manual_learned_questions')->delete();

                foreach (array_chunk($result['correzioni'], 200) as $chunk) {
                    DB::table('manual_learned_boosts')->insert(array_map(fn (array $c) => $c + ['created_at' => $now], $chunk));
                }

                foreach (array_chunk($result['domande'], 200) as $chunk) {
                    DB::table('manual_learned_questions')->insert(array_map(fn (array $q) => $q + ['created_at' => $now], $chunk));
                }
            }

            DB::table('manual_learning_runs')->insert([
                'pubblicato' => $summary['pubblicato'],
                'voti' => $summary['voti'],
                'correzioni' => $summary['correzioni'],
                'domande_imparate' => $summary['domande_imparate'],
                'sospette' => $summary['sospette'],
                'fallimenti' => $summary['fallimenti'] === [] ? null : json_encode($summary['fallimenti'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);
        });

        if ($summary['pubblicato']) {
            Log::info('Manuale, apprendimento: pesi pubblicati.', array_diff_key($summary, ['fallimenti' => true]));
        } else {
            Log::warning('Manuale, apprendimento: pesi NON pubblicati, il set di riferimento non passa. Restano i pesi di prima.', $summary);
        }

        return $summary;
    }
}
