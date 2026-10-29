<?php

namespace App\Support\Manual;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Domande iniziali dell'assistente (2026-10-29), da resources/manuale/domande.yml:
 * ogni voce collega alcuni modi di chiedere a una pagina, senza testo di
 * risposta (la risposta è sempre l'«In breve» della pagina).
 *
 * Letto senza symfony/yaml (solo dipendenza di sviluppo, vedi ManualPage):
 * il formato ammesso è solo quello documentato in testa al file, e una riga
 * diversa è un errore, non una riga ignorata in silenzio.
 */
final class ManualQuestions
{
    /**
     * @return list<array{pagina: string, domande: list<string>}>
     */
    public static function load(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $entries = [];
        $inQuestions = false;

        foreach (explode("\n", str_replace("\r\n", "\n", File::get($path))) as $number => $line) {
            $where = 'domande.yml, riga '.($number + 1);

            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match('/^- pagina:\s*([a-z0-9]+(?:-[a-z0-9]+)*)\s*$/', $line, $m)) {
                $entries[] = ['pagina' => $m[1], 'domande' => []];
                $inQuestions = false;
            } elseif ($entries !== [] && preg_match('/^  domande:\s*$/', $line)) {
                $inQuestions = true;
            } elseif ($inQuestions && preg_match('/^    - (.+)$/u', $line, $m)) {
                $entries[array_key_last($entries)]['domande'][] = trim($m[1]);
            } else {
                throw new InvalidArgumentException("{$where}: formato atteso «- pagina: slug», «  domande:», «    - domanda».");
            }
        }

        foreach ($entries as $entry) {
            if ($entry['domande'] === []) {
                throw new InvalidArgumentException("domande.yml: la voce per «{$entry['pagina']}» non ha domande.");
            }
        }

        return $entries;
    }

    /**
     * Tutte le domande con la pagina attesa, una per riga.
     *
     * @param  list<array{pagina: string, domande: list<string>}>  $entries
     * @return list<array{domanda: string, pagina: string}>
     */
    public static function flatten(array $entries): array
    {
        $out = [];

        foreach ($entries as $entry) {
            foreach ($entry['domande'] as $question) {
                $out[] = ['domanda' => $question, 'pagina' => $entry['pagina']];
            }
        }

        return $out;
    }
}
