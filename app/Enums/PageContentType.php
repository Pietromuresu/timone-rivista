<?php

namespace App\Enums;

enum PageContentType: string
{
    case Editoriale = 'editoriale';
    case Pubblicita = 'pubblicita';
    case Mista = 'mista';
    case Bianca = 'bianca';

    public function label(): string
    {
        return match ($this) {
            self::Editoriale => 'Editoriale',
            self::Pubblicita => 'Pubblicità',
            self::Mista => 'Mista',
            self::Bianca => 'Bianca',
        };
    }

    /**
     * Sfondo/testo dominante della card/riga (Fase 4, §4: "pagine
     * pubblicitarie: colore di sfondo dedicato e distinto da qualsiasi
     * altro stato, riconoscibile immediatamente"). Deliberatamente senza
     * `border-*` (a differenza della versione precedente a questo enum):
     * il bordo della card è riservato a PageStatus::borderClasses() —
     * due canali visivi distinti (sfondo = tipo pagina, bordo = stato),
     * un'unica classe `border-*` alla volta, mai due in conflitto.
     * hexColors() sotto è la stessa palette in esadecimale per l'export
     * PDF (Dompdf non esegue Tailwind) — i due metodi vanno tenuti
     * allineati quando si ritocca la palette, stessa nota di PageStatus.
     */
    public function colorClasses(): string
    {
        return match ($this) {
            self::Editoriale => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200',
            self::Pubblicita => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200',
            self::Mista => 'bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-200',
            self::Bianca => 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400',
        };
    }

    /**
     * @return array{bg: string, text: string}
     */
    public function hexColors(): array
    {
        return match ($this) {
            self::Editoriale => ['bg' => '#dbeafe', 'text' => '#1e40af'],
            self::Pubblicita => ['bg' => '#fef3c7', 'text' => '#92400e'],
            self::Mista => ['bg' => '#f3e8ff', 'text' => '#6b21a8'],
            self::Bianca => ['bg' => '#f3f4f6', 'text' => '#6b7280'],
        };
    }
}
