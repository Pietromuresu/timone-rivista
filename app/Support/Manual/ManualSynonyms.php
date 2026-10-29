<?php

namespace App\Support\Manual;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Gruppi di sinonimi del dominio per la ricerca (2026-10-29), da
 * resources/manuale/sinonimi.yml: una riga «- [parola, parola, …]» per
 * gruppo. Formato volutamente minimo e letto senza symfony/yaml (solo
 * dipendenza di sviluppo, vedi ManualPage). Una riga che non rispetta il
 * formato è un errore, non una riga ignorata in silenzio.
 */
final class ManualSynonyms
{
    /**
     * @return list<list<string>>
     */
    public static function load(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $groups = [];

        foreach (explode("\n", str_replace("\r\n", "\n", File::get($path))) as $number => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! preg_match('/^- \[(.+)\]$/u', $line, $m)) {
                throw new InvalidArgumentException('sinonimi.yml, riga '.($number + 1).': formato atteso «- [parola, parola]».');
            }

            $words = array_values(array_filter(array_map('trim', explode(',', $m[1])), fn ($word) => $word !== ''));

            foreach ($words as $word) {
                if (count(ItalianText::words($word)) !== 1) {
                    throw new InvalidArgumentException('sinonimi.yml, riga '.($number + 1).": «{$word}» non è una parola singola.");
                }
            }

            if (count($words) < 2) {
                throw new InvalidArgumentException('sinonimi.yml, riga '.($number + 1).': un gruppo ha almeno due parole.');
            }

            $groups[] = $words;
        }

        return $groups;
    }
}
