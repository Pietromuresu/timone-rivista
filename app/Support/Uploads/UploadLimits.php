<?php

namespace App\Support\Uploads;

/**
 * Limiti di dimensione effettivi di un upload (Fase 1, bug 9, 2026-10-26).
 * Un file passa da quattro "cancelli" diversi, configurati in quattro posti
 * diversi: upload_max_filesize e post_max_size di PHP, la regola `max:` di
 * livewire.temporary_file_upload.rules (upload temporaneo) e la regola
 * `max:` del componente (salvataggio). Il più piccolo vince, e se un
 * cancello di PHP è più stretto delle regole Laravel il file viene
 * scartato PRIMA che l'applicazione possa validarlo: l'utente vedeva solo
 * "caricamento non riuscito". Il valore effettivo serve alla drop zone per
 * rifiutare subito, lato browser, un file troppo grande con un messaggio
 * che dice il limite vero, e al comando `timone:diagnose-upload`.
 *
 * Il limite di nginx (client_max_body_size) non è leggibile da PHP: è nella
 * checklist di docs/09-deploy.md.
 */
final class UploadLimits
{
    /**
     * Valore di una direttiva php.ini di dimensione ("100M", "2G", "512K",
     * "0" = illimitato) in byte; null se illimitato o non interpretabile.
     */
    public static function iniBytes(string|false|null $value): ?int
    {
        if ($value === false || $value === null || trim($value) === '' || trim($value) === '0' || trim($value) === '-1') {
            return null;
        }

        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        $bytes = match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => is_numeric($value) ? $number : null,
        };

        return $bytes === null ? null : (int) $bytes;
    }

    /**
     * Valore della regola `max:` (in KB, come in Laravel) in un elenco di
     * regole stringa; null se assente.
     *
     * @param  list<string>|string  $rules
     */
    public static function ruleMaxKilobytes(array|string $rules): ?int
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;

        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'max:')) {
                return (int) substr($rule, 4);
            }
        }

        return null;
    }

    /**
     * Il più piccolo tra i limiti applicabili, in byte.
     *
     * @param  int|null  $componentMaxKilobytes  regola `max:` del componente che salva il file
     * @return array{bytes: int|null, source: string|null}
     */
    public static function effective(?int $componentMaxKilobytes = null): array
    {
        $candidates = array_filter([
            'upload_max_filesize (PHP)' => self::iniBytes(ini_get('upload_max_filesize')),
            'post_max_size (PHP)' => self::iniBytes(ini_get('post_max_size')),
            'livewire.temporary_file_upload.rules' => ($kb = self::ruleMaxKilobytes(config('livewire.temporary_file_upload.rules') ?? [])) !== null ? $kb * 1024 : null,
            'regola del modulo' => $componentMaxKilobytes !== null ? $componentMaxKilobytes * 1024 : null,
        ], fn ($bytes) => $bytes !== null);

        if ($candidates === []) {
            return ['bytes' => null, 'source' => null];
        }

        $source = array_keys($candidates, min($candidates))[0];

        return ['bytes' => $candidates[$source], 'source' => $source];
    }

    public static function humanMegabytes(int $bytes): string
    {
        $mb = $bytes / 1024 / 1024;

        return (fmod($mb, 1.0) === 0.0 ? (string) (int) $mb : number_format($mb, 1, ',', '')).' MB';
    }
}
