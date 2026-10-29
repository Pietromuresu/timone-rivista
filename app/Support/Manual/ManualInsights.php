<?php

namespace App\Support\Manual;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dati della pagina «Cosa chiedono gli utenti» (2026-10-29), solo lettura
 * più l'unica azione ammessa: spegnere una domanda imparata o una
 * correzione. Lo spegnimento vale subito (l'assistente lo legge a ogni
 * domanda) e resta per i giri successivi del job.
 */
final class ManualInsights
{
    public const TYPES = ['domanda', 'correzione'];

    /** @return Collection<int, object> domande senza risposta sicura, le più frequenti */
    public function unanswered(int $limit = 30): Collection
    {
        return DB::table('manual_unanswered')->orderByDesc('volte')->orderByDesc('ultima_volta')->limit($limit)->get();
    }

    /** @return Collection<int, object> */
    public function learnedQuestions(): Collection
    {
        return $this->withDisabled(DB::table('manual_learned_questions')->orderByDesc('voti')->get(), 'domanda');
    }

    /** @return Collection<int, object> correzioni attive (non sospette), le più forti prima */
    public function corrections(int $limit = 50): Collection
    {
        return $this->withDisabled(
            DB::table('manual_learned_boosts')->where('sospetta', false)->orderByRaw('abs(correzione) desc')->limit($limit)->get(),
            'correzione',
        );
    }

    /** @return Collection<int, object> «Risposte probabilmente sbagliate» */
    public function suspects(): Collection
    {
        return $this->withDisabled(DB::table('manual_learned_boosts')->where('sospetta', true)->orderBy('positivi')->get(), 'correzione');
    }

    public function lastRun(): ?object
    {
        return DB::table('manual_learning_runs')->orderByDesc('id')->first();
    }

    /** Ultimo giro bloccato dal cancello, con le domande che non passavano. */
    public function lastBlockedRun(): ?object
    {
        $run = DB::table('manual_learning_runs')->where('pubblicato', false)->orderByDesc('id')->first();

        if ($run !== null) {
            $run->fallimenti = json_decode((string) $run->fallimenti, true) ?? [];
        }

        return $run;
    }

    public function disable(string $type, string $key, string $slug): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Tipo non ammesso: {$type}");
        }

        DB::table('manual_disabled')->insertOrIgnore(['tipo' => $type, 'chiave' => $key, 'slug' => $slug, 'created_at' => now()]);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function withDisabled(Collection $rows, string $type): Collection
    {
        $disabled = DB::table('manual_disabled')->where('tipo', $type)->get()->map(fn ($d) => $d->chiave."\0".$d->slug)->flip();

        return $rows->each(function ($row) use ($disabled) {
            $row->disattivata = $disabled->has($row->chiave."\0".$row->slug);
        });
    }
}
