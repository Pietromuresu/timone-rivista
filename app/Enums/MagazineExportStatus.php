<?php

namespace App\Enums;

/**
 * Stato dell'export PDF sfogliabile di un numero (revisione 2, Fase 5,
 * 2026-10-28), mostrato nel timone mentre il job lavora in coda.
 */
enum MagazineExportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'In coda',
            self::Processing => 'In generazione',
            self::Ready => 'Pronto',
            self::Failed => 'Errore',
        };
    }

    /** Un job per questa variante è già in corso: non se ne accoda un altro. */
    public function isRunning(): bool
    {
        return $this === self::Queued || $this === self::Processing;
    }
}
