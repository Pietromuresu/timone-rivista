<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;

/**
 * Impostazioni del backup che l'app può conoscere: destinatari degli
 * avvisi e soglie. Le scrive il servizio «backup-agent» in settings.json
 * leggendole da backup.env; l'app non legge backup.env direttamente perché
 * lì ci sono anche credenziali e password, che non devono entrare nei
 * container dell'applicazione. Delle destinazioni remote arrivano solo
 * «configurata sì/no» e il tipo (rest-server, s3): mai indirizzi, utenti o
 * percorsi, che la pagina admin non deve mostrare (2026-10-30).
 */
final class BackupSettings
{
    /**
     * @param  list<string>  $alertRecipients
     * @param  list<string>  $invalidRecipients
     */
    public function __construct(
        public readonly array $alertRecipients,
        public readonly array $invalidRecipients,
        public readonly int $maxAgeHours,
        public readonly int $localMinFreeGb,
        public readonly ?int $remoteQuotaGb,
        public readonly int $remoteWarnPercent,
        public readonly bool $remoteConfigured,
        public readonly ?string $remoteType,
        public readonly bool $remote2Configured,
        public readonly ?string $remote2Type,
        public readonly ?int $remote2QuotaGb,
        public readonly ?CarbonImmutable $writtenAt,
        public readonly ?string $configError,
    ) {}

    /**
     * @param  array<string, mixed>  $data  contenuto di settings.json
     */
    public static function fromArray(array $data): self
    {
        [$valid, $invalid] = self::parseRecipients((string) ($data['alert_emails'] ?? ''));

        return new self(
            alertRecipients: $valid,
            invalidRecipients: $invalid,
            maxAgeHours: (int) ($data['max_age_hours'] ?? 26),
            localMinFreeGb: (int) ($data['local_min_free_gb'] ?? 10),
            remoteQuotaGb: isset($data['remote_quota_gb']) ? (int) $data['remote_quota_gb'] : null,
            remoteWarnPercent: (int) ($data['remote_warn_percent'] ?? 80),
            remoteConfigured: (bool) ($data['remote_configured'] ?? false),
            remoteType: isset($data['remote_type']) ? (string) $data['remote_type'] : null,
            remote2Configured: (bool) ($data['remote_2_configured'] ?? false),
            remote2Type: isset($data['remote_2_type']) ? (string) $data['remote_2_type'] : null,
            remote2QuotaGb: isset($data['remote_2_quota_gb']) ? (int) $data['remote_2_quota_gb'] : null,
            writtenAt: isset($data['written_at']) ? CarbonImmutable::parse((string) $data['written_at']) : null,
            configError: isset($data['config_error']) ? (string) $data['config_error'] : null,
        );
    }

    /**
     * BACKUP_ALERT_EMAIL accetta più indirizzi separati da virgola (o punto
     * e virgola, errore di battitura frequente). Gli indirizzi non validi
     * vengono restituiti a parte, non scartati in silenzio: chi configura
     * deve sapere che uno dei destinatari non riceverà nulla.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function parseRecipients(string $raw): array
    {
        $valid = [];
        $invalid = [];

        foreach (preg_split('/[,;]/', $raw) ?: [] as $address) {
            $address = trim($address);
            if ($address === '') {
                continue;
            }
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                $invalid[] = $address;
            } elseif (! in_array($address, $valid, true)) {
                $valid[] = $address;
            }
        }

        return [$valid, $invalid];
    }
}
