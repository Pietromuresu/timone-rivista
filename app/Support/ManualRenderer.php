<?php

namespace App\Support;

use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

/**
 * Converte MANUALE_UTENTE.md in HTML + indice cliccabile (bug 3,
 * 2026-10-12). Estratta dal controller perché è logica pura (stringa in,
 * stringa+array out) testabile senza HTTP né filesystem.
 *
 * - `Str::markdown()` (CommonMark GFM, già in vendor/ come dipendenza di
 *   Laravel — nessun pacchetto nuovo) con `html_input => escape`: un tag
 *   HTML scritto nel manuale viene mostrato come testo, mai eseguito. Il
 *   manuale è scritto dal team, ma la pagina è servita a tutti gli utenti
 *   loggati: nessun motivo di fidarsi di HTML grezzo.
 * - HeadingPermalinkExtension genera gli id dei titoli con lo stesso slug
 *   degli anchor già scritti a mano nel manuale (`[§4bis](#4bis-...)`,
 *   verificato in Fase 6.1), quindi indice e link interni puntano agli
 *   stessi id.
 */
final class ManualRenderer
{
    /**
     * $variables sostituisce i segnaposto `{{NOME}}` nel markdown PRIMA
     * della conversione — usato per `{{APP_URL}}`: il manuale non deve
     * contenere "http://localhost" scritto a mano, in produzione l'indirizzo
     * è un altro. Sostituzione prima di Str::markdown() così un URL
     * diventa un link vero (`<{{APP_URL}}>` è un autolink CommonMark), e
     * resta comunque soggetto all'escape dell'HTML grezzo.
     *
     * @param  array<string, string>  $variables
     * @return array{html: string, toc: list<array{level: int, id: string, text: string}>}
     */
    public static function render(string $markdown, array $variables = []): array
    {
        $markdown = strtr($markdown, array_combine(
            array_map(fn (string $name) => '{{'.$name.'}}', array_keys($variables)),
            array_values($variables),
        ));

        $html = (string) Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'before',
                'symbol' => '#',
                'title' => 'Link a questa sezione',
            ],
        ], [new HeadingPermalinkExtension]);

        return ['html' => $html, 'toc' => self::tableOfContents($html)];
    }

    /**
     * Indice dai titoli di livello 2 e 3 (il livello 1 è il titolo del
     * manuale stesso), letto dall'HTML già renderizzato invece che dal
     * markdown: così l'id è per costruzione quello assegnato dal
     * convertitore, non uno slug ricalcolato a parte che potrebbe divergere.
     *
     * @return list<array{level: int, id: string, text: string}>
     */
    private static function tableOfContents(string $html): array
    {
        preg_match_all('/<h([23])>\s*<a id="([^"]+)"[^>]*>.*?<\/a>(.*?)<\/h\1>/s', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $m) => [
            'level' => (int) $m[1],
            'id' => $m[2],
            'text' => trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5)),
        ], $matches);
    }
}
