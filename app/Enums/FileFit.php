<?php

namespace App\Enums;

/**
 * Come mostrare il file di un contenuto nel suo rettangolo sulla pagina
 * (revisione 2, Fase 2.4, 2026-10-27):
 * - FullPage: il file è impaginato a pagina intera con l'annuncio in una
 *   zona → si vede SOLO la porzione corrispondente al rettangolo del
 *   contenuto (mezza verticale a sinistra → metà sinistra del PDF);
 * - Format: il file è già nella misura del formato → intero, adattato al
 *   rettangolo senza tagli.
 * Rilevato da App\Support\PageGrid\ThumbnailCrop (dal 2026-10-30): intero
 * solo se il file ha la misura del riquadro del contenuto (±5%), se no a
 * pagina intera; la redazione può forzarlo dalla modale del contenuto
 * (contents.file_fit).
 */
enum FileFit: string
{
    case FullPage = 'full_page';
    case Format = 'format';

    public function label(): string
    {
        return match ($this) {
            self::FullPage => 'Il file è a pagina intera (mostra solo la parte del riquadro)',
            self::Format => 'Il file è già nel formato (mostralo intero)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::FullPage => 'a pagina intera',
            self::Format => 'nel formato',
        };
    }
}
