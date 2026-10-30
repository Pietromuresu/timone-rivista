<?php

namespace App\Support\Backup;

/**
 * Accesso in sola lettura alla cartella di stato del backup (volume
 * `backup_status`, scritto dal servizio «backup-agent»). Unico punto che
 * conosce i nomi dei file JSON, così comandi e pagina admin non
 * dipendono dal formato su disco.
 */
final class BackupStatusDirectory
{
    public function __construct(private readonly string $path) {}

    public static function fromConfig(): self
    {
        return new self((string) config('timone.backup.status_path'));
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Null se l'agente non ha mai scritto le impostazioni (profilo «backup»
     * non attivo, oppure agente mai partito) o se il file è illeggibile.
     */
    public function settings(): ?BackupSettings
    {
        $data = $this->readJson('settings.json');

        return $data === null ? null : BackupSettings::fromArray($data);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $file): ?array
    {
        $full = $this->path.DIRECTORY_SEPARATOR.$file;
        if (! is_file($full) || ! is_readable($full)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($full), true);

        return is_array($data) ? $data : null;
    }
}
