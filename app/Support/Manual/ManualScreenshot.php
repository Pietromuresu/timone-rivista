<?php

namespace App\Support\Manual;

use InvalidArgumentException;

/**
 * Un blocco `:::screenshot nome` di una pagina del manuale (2026-10-29, Fase 4):
 *
 *     :::screenshot timone-intero
 *     alt: Il timone di un numero, con …
 *     1 | 50% | 6% | Riepilogo del numero
 *     2 | 30% | 14% | Fascia dei comandi
 *     :::
 *
 * - `alt:` è il testo alternativo dell'immagine (obbligatorio se c'è
 *   un'immagine) e, finché l'immagine non c'è, il testo del segnaposto
 *   «[Screenshot da fare: …]».
 * - Ogni riga `N | x% | y% | testo` è un numero cerchiato sopra l'immagine,
 *   al punto x/y in percentuale della larghezza/altezza, con la sua
 *   spiegazione sotto. Le annotazioni NON sono disegnate nell'immagine:
 *   rigenerando lo screenshot restano, e si correggono qui come testo.
 * - Una riga «[Screenshot da fare: …]» (formato dei segnaposto della Fase 1)
 *   vale come testo del segnaposto.
 *
 * Una riga che sembra un'annotazione ma è scritta male è un errore, non
 * una riga ignorata: un numero che sparisce in silenzio è peggio.
 */
final class ManualScreenshot
{
    /**
     * @param  list<array{number: int, x: float, y: float, text: string}>  $markers
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $alt,
        public readonly array $markers,
        public readonly string $placeholder,
    ) {}

    public static function parse(string $name, string $markdown): self
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name)) {
            throw new InvalidArgumentException("Screenshot «{$name}»: il nome va scritto in minuscolo con i trattini (è il nome del file).");
        }

        $alt = null;
        $markers = [];
        $placeholder = null;

        foreach (explode("\n", trim($markdown)) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^alt:\s*(.+)$/u', $line, $m)) {
                $alt = trim($m[1]);
            } elseif (preg_match('/^\d+\s*\|/', $line)) {
                if (! preg_match('/^(\d+)\s*\|\s*(\d{1,3}(?:[.,]\d+)?)%\s*\|\s*(\d{1,3}(?:[.,]\d+)?)%\s*\|\s*(.+)$/u', $line, $m)) {
                    throw new InvalidArgumentException("Screenshot «{$name}»: annotazione non valida «{$line}» (formato: N | x% | y% | testo).");
                }

                $x = (float) str_replace(',', '.', $m[2]);
                $y = (float) str_replace(',', '.', $m[3]);

                if ($x > 100 || $y > 100) {
                    throw new InvalidArgumentException("Screenshot «{$name}»: l'annotazione {$m[1]} è fuori dall'immagine (oltre 100%).");
                }

                $markers[] = ['number' => (int) $m[1], 'x' => $x, 'y' => $y, 'text' => trim($m[4])];
            } elseif (preg_match('/^\[Screenshot da fare:\s*(.+)\]$/u', $line, $m)) {
                $placeholder = trim($m[1]);
            } else {
                throw new InvalidArgumentException("Screenshot «{$name}»: riga non riconosciuta «{$line}».");
            }
        }

        return new self($name, $alt, $markers, $placeholder ?? $alt ?? $name);
    }
}
