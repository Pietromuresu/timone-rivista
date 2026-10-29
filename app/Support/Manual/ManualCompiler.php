<?php

namespace App\Support\Manual;

/**
 * Il manuale in un file unico (2026-10-29): MANUALE_UTENTE.md diventa un
 * file generato da `php artisan manuale:genera`, concatenando le pagine
 * per categoria. Così chi lo apre dal repository (README, docs/, CLAUDE.md
 * lo citano) trova ancora tutto, e `config('app.manual_path')` (in
 * config/app.php, file protetto) resta valido senza toccarlo.
 *
 * Markdown semplice, leggibile anche su GitHub: i blocchi `:::nome`
 * diventano paragrafi con un'intestazione in grassetto, i link tra pagine
 * puntano ai file in resources/manuale/. Un test controlla che il file
 * committato sia uguale a quello generato.
 */
final class ManualCompiler
{
    public const HEADER = "<!-- File generato da «php artisan manuale:genera»: NON modificare a mano.\n     Le pagine del manuale sono in resources/manuale/ (una per argomento). -->";

    public static function compile(ManualRepository $repository): string
    {
        $out = [self::HEADER, '', '# Manuale utente — Timone Elettronico', '', 'Il manuale si legge anche nell\'applicazione, dalla voce «📖 Manuale» nel menu in alto, una pagina per argomento.'];

        foreach ($repository->byCategory() as $category => $pages) {
            $out[] = '';
            $out[] = '## '.$category;

            foreach ($pages as $page) {
                $out[] = '';
                $out[] = '### '.$page->titolo;
                $out[] = '';
                $out[] = self::page($page);
            }
        }

        return implode("\n", $out)."\n";
    }

    private static function page(ManualPage $page): string
    {
        $parts = [];

        foreach (ManualBlocks::split($page->body) as $block) {
            // I titoli della pagina scendono di due livelli (## → ####): nel
            // file unico sopra ci sono la categoria (##) e la pagina (###).
            $markdown = preg_replace('/^(#{2,4}) /m', '$1## ', $block['markdown']);
            $markdown = preg_replace('/\]\(manuale:([a-z0-9-]+)(#[^)\s]*)?\)/', '](resources/manuale/$1.md)', $markdown);

            $parts[] = match ($block['type']) {
                'in-breve' => "**In breve**\n\n".$markdown,
                'dettagli' => "**Dettagli**\n\n".$markdown,
                'attenzione' => self::quote('**Attenzione**', $markdown),
                'suggerimento' => self::quote('**Suggerimento**', $markdown),
                'problema' => '**'.$block['arg']."**\n\n".$markdown,
                'legenda' => self::legend($markdown),
                default => $markdown,
            };
        }

        return implode("\n\n", array_filter($parts, fn ($part) => trim($part) !== ''));
    }

    private static function quote(string $title, string $markdown): string
    {
        return '> '.$title."\n>\n".preg_replace('/^/m', '> ', $markdown);
    }

    private static function legend(string $markdown): string
    {
        $lines = [];

        foreach (explode("\n", $markdown) as $line) {
            $parts = array_map('trim', explode('|', ltrim(trim($line), '- ')));

            if (count($parts) === 3) {
                $lines[] = '- '.$parts[1].': '.$parts[2];
            }
        }

        return implode("\n", $lines);
    }
}
