<?php

namespace App\Enums;

/**
 * Stato del materiale di una pubblicità (Fase 3, §3) — distinto dallo stato
 * commerciale (`AdConfirmationStatus`, "in trattativa/confermata/annullata",
 * già esistente) e dallo stato della pagina (`PageStatus`): questo riguarda
 * solo "a che punto è il materiale stampabile", calcolato sempre da
 * App\Support\AdMaterialStatusResolver a partire dalle pagine assegnate e
 * dai loro file — mai memorizzato in colonna, per non poter mai andare
 * fuori sincrono con lo stato reale.
 */
enum AdMaterialStatus: string
{
    /**
     * Nessuna pagina assegnata ancora — lo spazio è "venduto"/riservato
     * (cliente, formato, note commerciali già presenti sull'Advertisement)
     * ma non ha ancora un posto fisico nel timone.
     */
    case Prenotato = 'prenotato';

    /**
     * Assegnata a una o più pagine, ma almeno una di quelle pagine non ha
     * ancora un PDF, oppure ne ha uno con un formato non conforme non
     * ancora accettato esplicitamente (§2.3).
     */
    case Assegnato = 'assegnato';

    /**
     * Ogni pagina a cui è assegnata ha un PDF, senza problemi di formato
     * irrisolti.
     */
    case Completo = 'completo';

    public function label(): string
    {
        return match ($this) {
            self::Prenotato => 'Prenotato',
            self::Assegnato => 'Assegnato',
            self::Completo => 'Completo',
        };
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::Prenotato => 'bg-amber-100 text-amber-700',
            self::Assegnato => 'bg-sky-100 text-sky-700',
            self::Completo => 'bg-green-100 text-green-700',
        };
    }
}
