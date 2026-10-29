<?php

namespace App\Support\Manual;

/**
 * Correzioni imparate dai voti per una chiave di domanda (2026-10-29):
 * slug → correzione in [-0,3, +0,3]. L'assistente non sa da dove arrivino:
 * in produzione dalla tabella manual_learned_boosts (StoredLearnedCorrections),
 * nel cancello del job notturno dai pesi appena calcolati, non ancora
 * pubblicati (ArrayLearnedCorrections).
 */
interface LearnedCorrections
{
    /** @return array<string, float> */
    public function forKey(string $key): array;
}
