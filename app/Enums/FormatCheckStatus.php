<?php

namespace App\Enums;

/**
 * Esito del controllo dimensioni PDF rispetto al formato pubblicitario
 * assegnato alla pagina (§2.3) — calcolato da App\Jobs\GeneratePageFileThumbnail
 * insieme alla miniatura, mai un blocco: solo un avviso, forzabile
 * dall'utente (vedi Grid::confirmFormatOverride()).
 */
enum FormatCheckStatus: string
{
    /**
     * La pagina non ha un formato pubblicitario univoco a cui riferirsi
     * (nessuna pubblicità assegnata, più di una, o un formato senza misure
     * note nel listino) — nessun controllo significativo possibile, non
     * mostrato come problema.
     */
    case NotApplicable = 'not_applicable';

    /**
     * Un formato era applicabile ma le dimensioni reali del PDF non sono
     * state misurabili (file illeggibile/corrotto) — mai un'eccezione,
     * solo questo stato.
     */
    case Unverifiable = 'unverifiable';

    case Matching = 'matching';
    case Mismatch = 'mismatch';

    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'Non applicabile',
            self::Unverifiable => 'Dimensioni non verificabili',
            self::Matching => 'Formato conforme',
            self::Mismatch => 'Formato non conforme',
        };
    }
}
