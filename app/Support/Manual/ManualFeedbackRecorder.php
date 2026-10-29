<?php

namespace App\Support\Manual;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Registra un voto sull'assistente (2026-10-29). Solo voti espliciti
 * (👍 Utile, 👎 Non è questo, scelta di un'alternativa): nessun segnale
 * implicito come clic o tempo di lettura.
 *
 * Una volta per risposta mostrata: un voto sulla risposta, e al massimo una
 * pagina scelta tra le alternative (dopo 👎, o in «Forse cercavi»).
 * L'utente è salvato come impronta non reversibile (HMAC con la chiave
 * dell'app): basta per «un voto per persona», non dice chi.
 */
final class ManualFeedbackRecorder
{
    public const OK = 'ok';

    public const EXPIRED = 'scaduta';

    public const ALREADY_VOTED = 'gia-votato';

    public const NOT_ALLOWED = 'non-ammesso';

    /**
     * @param  'su'|'giu'|null  $vote  voto sulla risposta
     * @param  string|null  $choice  pagina scelta tra le alternative
     * @return self::* esito
     */
    public function record(string $token, ?string $vote, ?string $choice, int|string $userId): string
    {
        $answer = AnswerToken::decode($token, now()->getTimestamp());

        if ($answer === null) {
            return self::EXPIRED;
        }

        $row = [
            'risposta_id' => $answer['id'],
            'chiave' => $answer['chiave'],
            'domanda' => mb_substr($answer['domanda'], 0, 500),
            'contesto' => $answer['contesto'],
            'utente' => self::userHash($userId),
            'created_at' => now(),
        ];

        if ($choice !== null) {
            $position = array_search($choice, $answer['alternative'], true);

            // Dopo una risposta, le alternative si scelgono solo dopo «Non è questo».
            $rejected = $answer['stato'] !== 'risposta' || DB::table('manual_feedback')
                ->where('risposta_id', $answer['id'])->where('su_alternativa', false)->where('voto', -1)->exists();

            if ($position === false || ! $rejected) {
                return self::NOT_ALLOWED;
            }

            $row += [
                'slug_mostrato' => $answer['pagina'],
                'slug_scelto' => $choice,
                'su_alternativa' => true,
                'posizione' => $position + 1,
                'voto' => 1,
                'hash_versione' => $answer['hash'][$choice][0],
                'hash_in_breve' => $answer['hash'][$choice][1],
            ];
        } elseif (in_array($vote, ['su', 'giu'], true) && $answer['stato'] === 'risposta' && $answer['pagina'] !== null) {
            $row += [
                'slug_mostrato' => $answer['pagina'],
                'slug_scelto' => null,
                'su_alternativa' => false,
                'posizione' => 1,
                'voto' => $vote === 'su' ? 1 : -1,
                'hash_versione' => $answer['hash'][$answer['pagina']][0],
                'hash_in_breve' => $answer['hash'][$answer['pagina']][1],
            ];
        } else {
            return self::NOT_ALLOWED;
        }

        try {
            DB::table('manual_feedback')->insert($row);
        } catch (UniqueConstraintViolationException) {
            return self::ALREADY_VOTED;
        }

        return self::OK;
    }

    public static function userHash(int|string $userId): string
    {
        return hash_hmac('sha256', 'manuale:'.$userId, (string) config('app.key'));
    }
}
