<?php

namespace App\Support\Manual;

use Illuminate\Support\Facades\DB;

/**
 * Correzioni pubblicate dall'ultimo giro riuscito del job (tabella
 * manual_learned_boosts), meno quelle spente a mano da un admin: lo
 * spegnimento vale subito, senza aspettare il giro successivo.
 */
final class StoredLearnedCorrections implements LearnedCorrections
{
    public function forKey(string $key): array
    {
        $disabled = DB::table('manual_disabled')->where('tipo', 'correzione')->where('chiave', $key)->pluck('slug')->all();

        return DB::table('manual_learned_boosts')
            ->where('chiave', $key)
            ->whereNotIn('slug', $disabled)
            ->pluck('correzione', 'slug')
            ->map(fn ($value) => (float) $value)
            ->all();
    }
}
