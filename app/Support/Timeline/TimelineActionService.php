<?php

namespace App\Support\Timeline;

use App\Models\Issue;
use App\Models\TimelineAction;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Annulla/ripeti del timone (2026-10-28), pattern a comandi: ogni azione
 * annullabile passa da run(), che la esegue tra due fotografie del timone
 * (TimelineSnapshot) e registra in `timone_azioni` solo le righe e i campi
 * cambiati (TimelineDiff). undo()/redo() riapplicano lo stato «prima»/«dopo»
 * con un solo applicatore generico (TimelineApplier), dopo i controlli di
 * conflitto, permessi (TimelineAuthorizer) e spazio.
 *
 * - Cronologia per utente e per numero: Ctrl+Z annulla solo le mie azioni.
 *   Al massimo MAX_STEPS passi; dopo 24 ore li toglie timone:history-prune.
 * - Una nuova azione svuota la mia pila «ripeti».
 * - Un passo non annullabile (PDF sostituito, conflitto trovato) blocca la
 *   pila lì: annullare quelli più vecchi saltandolo lascerebbe il timone
 *   in uno stato che nessuno ha mai visto.
 * - Le azioni annidate (nudgePage → movePage) contano una volta sola.
 * - Lotti (caricamento multiplo): stessa batch_key = stesso passo.
 *
 * Transazione: per le azioni sul timone run() blocca la riga del numero
 * (le scritture di un numero passano una alla volta, così le due
 * fotografie contengono solo questa azione). Per creazione/modifica di
 * contenuti con file si usa $transactional = false: i job delle miniature
 * devono trovare le righe già scritte, non dentro una transazione aperta.
 * Senza il lock un collega può scrivere fra le due fotografie: per non
 * mettere le sue modifiche nel mio passo (Ctrl+Z le disferebbe), queste
 * azioni passano $scope, che dopo l'azione dice quali contenuti ha
 * toccato, e il passo tiene solo quelli (TimelineDiff::onlyContents()).
 * Con la transazione non serve: MySQL (REPEATABLE READ) fa leggere a
 * entrambe le fotografie la stessa vista, più le mie scritture.
 */
final class TimelineActionService
{
    public const MAX_STEPS = 50;

    public const RETENTION_HOURS = 24;

    private static int $depth = 0;

    public function run(Issue $issue, ?User $user, string $type, Closure $action, ?string $batchKey = null, ?string $notUndoable = null, bool $transactional = true, ?Closure $scope = null): mixed
    {
        if (self::$depth > 0 || $user === null) {
            return $action();
        }

        self::$depth++;

        try {
            $body = function () use ($issue, $user, $type, $action, $batchKey, $notUndoable, $transactional, $scope) {
                if ($transactional) {
                    Issue::whereKey($issue->id)->lockForUpdate()->first();
                }

                $before = TimelineSnapshot::take($issue->id);
                $result = $action();
                $after = TimelineSnapshot::take($issue->id);
                $changes = TimelineDiff::between($before, $after);

                if ($scope !== null) {
                    $changes = TimelineDiff::onlyContents($changes, $scope(), $before, $after);
                }

                if ($changes !== []) {
                    $this->record($issue, $user, $type, $changes, TimelineLabeler::label($type, $changes, $before, $after), $batchKey, $notUndoable);
                }

                return $result;
            };

            return $transactional ? DB::transaction($body) : $body();
        } finally {
            self::$depth--;
        }
    }

    /**
     * @return array{ok: bool, message: string, label?: string}
     *
     * @throws AuthorizationException
     */
    public function undo(Issue $issue, User $user): array
    {
        return $this->exclusively($issue, $user, function () use ($issue, $user) {
            $step = TimelineAction::where('issue_id', $issue->id)->where('user_id', $user->id)
                ->where('status', TimelineAction::DONE)->orderByDesc('sequence')->first();

            return $step === null
                ? ['ok' => false, 'message' => 'Niente da annullare.']
                : $this->replay($issue, $user, $step, true);
        });
    }

    /**
     * @return array{ok: bool, message: string, label?: string}
     *
     * @throws AuthorizationException
     */
    public function redo(Issue $issue, User $user): array
    {
        return $this->exclusively($issue, $user, function () use ($issue, $user) {
            $step = TimelineAction::where('issue_id', $issue->id)->where('user_id', $user->id)
                ->where('status', TimelineAction::UNDONE)->orderBy('sequence')->first();

            return $step === null
                ? ['ok' => false, 'message' => 'Niente da ripetere.']
                : $this->replay($issue, $user, $step, false);
        });
    }

    /** @return array{undo: string|null, undoBlocked: string|null, redo: string|null} */
    public function state(Issue $issue, User $user): array
    {
        $base = TimelineAction::where('issue_id', $issue->id)->where('user_id', $user->id);
        $undo = (clone $base)->where('status', TimelineAction::DONE)->orderByDesc('sequence')->first();
        $redo = (clone $base)->where('status', TimelineAction::UNDONE)->orderBy('sequence')->first();

        return ['undo' => $undo?->label, 'undoBlocked' => $undo?->blocked_reason, 'redo' => $redo?->label];
    }

    /** Ultimi passi dell'utente sul numero, per il menu Cronologia. */
    public function history(Issue $issue, User $user, int $limit = self::MAX_STEPS)
    {
        return TimelineAction::where('issue_id', $issue->id)->where('user_id', $user->id)
            ->orderByDesc('sequence')->limit($limit)->get(['id', 'label', 'status', 'blocked_reason', 'created_at', 'undone_at']);
    }

    private function replay(Issue $issue, User $user, TimelineAction $step, bool $undo): array
    {
        if ($undo && $step->blocked_reason !== null) {
            return ['ok' => false, 'message' => 'Non annullabile: '.$step->blocked_reason];
        }

        if (! TimelineAuthorizer::allows($user, $issue, $step->changes)) {
            throw new AuthorizationException('Non hai i permessi per '.($undo ? 'annullare' : 'ripetere').' questa azione.');
        }

        $verb = $undo ? 'Annullato' : 'Ripetuto';

        try {
            DB::transaction(function () use ($issue, $user, $step, $undo) {
                Issue::whereKey($issue->id)->lockForUpdate()->first();

                if (($conflict = TimelineApplier::conflict($issue, $step->changes, $undo, $undo ? $step->created_at : ($step->undone_at ?? $step->created_at), $user->id)) !== null) {
                    throw new TimelineConflict($conflict);
                }

                $overfull = TimelineApplier::overfullBefore($step->changes);
                TimelineApplier::apply($step->changes, $undo);

                if (($problem = TimelineApplier::capacityProblem($issue, $overfull)) !== null) {
                    throw new TimelineConflict('Non posso '.($undo ? 'annullare' : 'ripetere').': '.$problem.'.');
                }

                $step->update(['status' => $undo ? TimelineAction::UNDONE : TimelineAction::DONE, 'undone_at' => $undo ? now() : null]);

                // Gli altri client devono riallineare l'ordinamento (lock ottimistico di movePage).
                if (collect($step->changes)->contains(fn ($c) => $c['t'] === 'pages')) {
                    DB::table('issues')->where('id', $issue->id)->increment('reorder_version');
                }
            });
        } catch (TimelineConflict $conflict) {
            // Il passo resta in cronologia, marcato: non si riprova all'infinito.
            if ($undo) {
                $step->update(['blocked_reason' => $conflict->getMessage()]);
            }

            return ['ok' => false, 'message' => $conflict->getMessage()];
        }

        $issue->markPagesUpdated();

        return ['ok' => true, 'message' => "{$verb}: {$step->label}", 'label' => $step->label];
    }

    private function record(Issue $issue, User $user, string $type, array $changes, string $label, ?string $batchKey, ?string $notUndoable): void
    {
        $last = TimelineAction::where('issue_id', $issue->id)->where('user_id', $user->id)->orderByDesc('sequence')->first();

        if ($batchKey !== null && $last !== null && $last->batch_key === $batchKey && $last->status === TimelineAction::DONE) {
            $merged = TimelineDiff::merge($last->changes, $changes);
            $count = collect($merged)->where('t', 'contents')->count();
            $last->update(['changes' => $merged, 'label' => $count > 1 ? "Crea {$count} contenuti (caricamento multiplo)" : $label]);

            return;
        }

        // Una nuova azione dopo un annulla svuota il «ripeti».
        TimelineAction::where('issue_id', $issue->id)->where('user_id', $user->id)->where('status', TimelineAction::UNDONE)->delete();

        TimelineAction::create([
            'issue_id' => $issue->id,
            'user_id' => $user->id,
            'sequence' => (int) ($last?->sequence ?? 0) + 1,
            'type' => $type,
            'label' => $label,
            'changes' => $changes,
            'status' => TimelineAction::DONE,
            'blocked_reason' => $notUndoable,
            'batch_key' => $batchKey,
        ]);

        $keep = TimelineAction::where('issue_id', $issue->id)->where('user_id', $user->id)->orderByDesc('sequence')->limit(self::MAX_STEPS)->pluck('id');
        TimelineAction::where('issue_id', $issue->id)->where('user_id', $user->id)->whereNotIn('id', $keep)->delete();
    }

    /**
     * Una richiesta alla volta per utente e numero: un Ctrl+Z mentre il
     * precedente è in corso viene ignorato (raffiche di tasti).
     */
    private function exclusively(Issue $issue, User $user, Closure $work): array
    {
        $lock = Cache::lock("timeline:{$issue->id}:{$user->id}", 30);

        if (! $lock->get()) {
            return ['ok' => false, 'message' => 'Un annulla/ripeti è già in corso.', 'busy' => true];
        }

        try {
            return $work();
        } finally {
            $lock->release();
        }
    }
}
