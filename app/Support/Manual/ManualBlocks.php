<?php

namespace App\Support\Manual;

/**
 * Divide il testo di una pagina del manuale in blocchi (2026-10-29): testo
 * normale e blocchi delimitati da `:::nome argomenti` … `:::` (in-breve,
 * dettagli, attenzione, suggerimento, screenshot, compiti, legenda,
 * problema). Un solo livello, niente annidamento: è voluto, così un
 * blocco non dipende dal contesto e il renderer resta semplice.
 *
 * Usata sia per l'HTML (ManualRenderer) sia per il file unico generato
 * (ManualCompiler) sia per estrarre l'«In breve» (ManualPage::inBreve()).
 */
final class ManualBlocks
{
    public const TYPES = ['in-breve', 'dettagli', 'attenzione', 'suggerimento', 'screenshot', 'compiti', 'legenda', 'problema'];

    /**
     * @return list<array{type: string, arg: string, markdown: string}>
     *                                                                  type 'testo' per il testo fuori dai blocchi
     */
    public static function split(string $body): array
    {
        $blocks = [];
        $buffer = [];
        $open = null;

        $flush = function (string $type, string $arg) use (&$buffer, &$blocks): void {
            $markdown = trim(implode("\n", $buffer), "\n");

            if ($markdown !== '' || $type !== 'testo') {
                $blocks[] = ['type' => $type, 'arg' => $arg, 'markdown' => $markdown];
            }

            $buffer = [];
        };

        foreach (explode("\n", $body) as $line) {
            if ($open === null && preg_match('/^:::([a-z-]+)\s*(.*)$/', $line, $m) && in_array($m[1], self::TYPES, true)) {
                $flush('testo', '');
                $open = ['type' => $m[1], 'arg' => trim($m[2])];

                continue;
            }

            if ($open !== null && trim($line) === ':::') {
                $flush($open['type'], $open['arg']);
                $open = null;

                continue;
            }

            $buffer[] = $line;
        }

        // Un blocco non chiuso inghiottirebbe il resto della pagina in
        // silenzio (per esempio tutto dentro i «Dettagli»): meglio un errore.
        if ($open !== null) {
            throw new \InvalidArgumentException("Blocco «:::{$open['type']}» senza la riga «:::» di chiusura.");
        }

        $flush('testo', '');

        return $blocks;
    }
}
