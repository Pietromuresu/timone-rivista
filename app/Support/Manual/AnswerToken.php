<?php

namespace App\Support\Manual;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Ricevuta di una risposta dell'assistente (2026-10-29), cifrata con la
 * chiave dell'app e restituita dal browser quando si vota. Contiene ciò che
 * è stato davvero mostrato (domanda, chiave, pagina, alternative, versione
 * delle pagine), così il voto non può riguardare una pagina mai proposta
 * e non serve salvare nulla finché nessuno vota.
 */
final class AnswerToken
{
    /** Dopo un giorno la ricevuta scade: si vota su una risposta appena vista. */
    public const TTL_SECONDS = 86400;

    /**
     * @param  array{id: string, chiave: string, domanda: string, contesto: ?string, stato: string, pagina: ?string, alternative: list<string>, hash: array<string, array{0: string, 1: string}>, ora: int}  $data
     */
    public static function encode(array $data): string
    {
        return Crypt::encryptString(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed>|null null se manomessa o scaduta */
    public static function decode(string $token, int $now): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data) || ! isset($data['ora']) || $now - (int) $data['ora'] > self::TTL_SECONDS) {
            return null;
        }

        return $data;
    }
}
