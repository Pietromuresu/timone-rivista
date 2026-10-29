<?php

namespace App\Support;

use App\Support\Manual\ManualBlocks;
use App\Support\Manual\ManualPage;
use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

/**
 * Converte il manuale utente in HTML + indice cliccabile (bug 3,
 * 2026-10-12). Estratta dal controller perché è logica pura (stringa in,
 * stringa+array out) testabile senza HTTP né filesystem.
 *
 * - `Str::markdown()` (CommonMark GFM, già in vendor/ come dipendenza di
 *   Laravel — nessun pacchetto nuovo) con `html_input => escape`: un tag
 *   HTML scritto nel manuale viene mostrato come testo, mai eseguito. Il
 *   manuale è scritto dal team, ma la pagina è servita a tutti gli utenti
 *   loggati: nessun motivo di fidarsi di HTML grezzo.
 * - HeadingPermalinkExtension genera gli id dei titoli; l'indice li legge
 *   dall'HTML già convertito, così punta sempre agli id veri.
 *
 * Dal 2026-10-29 il manuale è diviso in pagine (App\Support\Manual):
 * renderPage() converte una pagina con i suoi blocchi `:::nome` (In
 * breve, Dettagli, Attenzione…). L'HTML dei blocchi lo scrive questa
 * classe, attorno a markdown che passa sempre dallo stesso escape: il
 * testo delle pagine non può comunque inserire HTML.
 */
final class ManualRenderer
{
    /**
     * Riquadri evidenziati: classe CSS (scritta per intero, perché Tailwind
     * tiene solo le classi che trova così nei file) e titolo con l'icona.
     */
    private const CALLOUTS = [
        'attenzione' => ['manual-callout manual-callout-attenzione', '⚠️ Attenzione'],
        'suggerimento' => ['manual-callout manual-callout-suggerimento', '💡 Suggerimento'],
    ];

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
        $html = self::markdown(self::substitute($markdown, $variables));

        return ['html' => $html, 'toc' => self::tableOfContents($html)];
    }

    /**
     * Una pagina del manuale. $pageUrl trasforma uno slug nell'indirizzo
     * della pagina: i link tra pagine si scrivono `[testo](manuale:slug)`,
     * così il testo non dipende da come sono fatti gli indirizzi (la
     * pagina web usa le route, il file unico generato i file .md).
     *
     * @param  callable(string): string  $pageUrl
     * @param  array<string, string>  $variables
     * @return array{html: string, toc: list<array{level: int, id: string, text: string}>}
     */
    public static function renderPage(ManualPage $page, callable $pageUrl, array $variables = []): array
    {
        $html = '';

        foreach (ManualBlocks::split($page->body) as $block) {
            $markdown = self::links(self::substitute($block['markdown'], $variables), $pageUrl);
            $html .= self::block($block['type'], $block['arg'], $markdown, $pageUrl);
        }

        $html = self::labelTables($html);

        return ['html' => $html, 'toc' => self::tableOfContents($html)];
    }

    /**
     * Solo il blocco «In breve» di una pagina, in HTML (2026-10-29): è la
     * risposta che mostra l'assistente. Stessa conversione della pagina
     * (link tra pagine, segnaposto, escape dell'HTML grezzo).
     *
     * @param  callable(string): string  $pageUrl
     * @param  array<string, string>  $variables
     */
    public static function inBreve(ManualPage $page, callable $pageUrl, array $variables = []): ?string
    {
        $markdown = $page->inBreve();

        return $markdown === null ? null : self::markdown(self::links(self::substitute($markdown, $variables), $pageUrl));
    }

    /**
     * Ogni cella di una tabella riceve data-label = intestazione della sua
     * colonna: su schermi stretti la tabella diventa un elenco (app.css) e
     * senza l'intestazione accanto non si capirebbe cosa dice la cella.
     */
    private static function labelTables(string $html): string
    {
        return preg_replace_callback('/<table>(.*?)<\/table>/s', function (array $table) {
            preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $table[1], $headers);
            $labels = array_map(fn ($h) => trim(html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5)), $headers[1]);

            $body = preg_replace_callback('/<tr>(.*?)<\/tr>/s', function (array $row) use ($labels) {
                $i = 0;

                return '<tr>'.preg_replace_callback('/<td(?=[\s>])/', function () use (&$i, $labels) {
                    return '<td data-label="'.e($labels[$i++] ?? '').'"';
                }, $row[1]).'</tr>';
            }, $table[1]);

            return '<table>'.$body.'</table>';
        }, $html);
    }

    /** `](manuale:slug)` e `](manuale:slug#ancora)` → indirizzo della pagina. */
    public static function links(string $markdown, callable $pageUrl): string
    {
        return preg_replace_callback(
            '/\]\(manuale:([a-z0-9-]+)(#[^)\s]*)?\)/',
            fn (array $m) => ']('.$pageUrl($m[1]).($m[2] ?? '').')',
            $markdown,
        );
    }

    /** @param  callable(string): string  $pageUrl */
    private static function block(string $type, string $arg, string $markdown, callable $pageUrl): string
    {
        return match ($type) {
            'in-breve' => '<section class="manual-in-breve" aria-label="In breve"><p class="manual-in-breve-title">In breve</p>'.self::markdown($markdown).'</section>',
            'dettagli' => '<details class="manual-details"><summary>Dettagli</summary><div class="manual-details-body">'.self::markdown($markdown).'</div></details>',
            'attenzione', 'suggerimento' => '<aside class="'.self::CALLOUTS[$type][0].'" role="note"><p class="manual-callout-title">'.self::CALLOUTS[$type][1].'</p>'.self::markdown($markdown).'</aside>',
            // Fase 4: qui andrà l'immagine con le annotazioni; finché non c'è
            // resta il segnaposto visibile «[Screenshot da fare: …]».
            'screenshot' => '<figure class="manual-screenshot" data-screenshot="'.e($arg).'"><div class="manual-screenshot-missing">'.self::markdown($markdown).'</div></figure>',
            'compiti' => self::tasks($markdown),
            'legenda' => self::legend($markdown),
            'problema' => '<div class="manual-problem"><h3 class="manual-problem-title">'.self::inline($arg).'</h3>'.self::markdown($markdown).'</div>',
            default => self::markdown($markdown),
        };
    }

    /** Riquadri «Cosa vuoi fare?»: `- [Titolo](indirizzo) — descrizione`. */
    private static function tasks(string $markdown): string
    {
        $items = '';

        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^- \[([^\]]+)\]\(([^)\s]+)\)\s*(?:—\s*(.*))?$/u', trim($line), $m)) {
                $items .= '<li><a class="manual-task" href="'.e($m[2]).'"><span class="manual-task-title">'.e($m[1]).'</span>'
                    .(($m[3] ?? '') !== '' ? '<span class="manual-task-desc">'.self::inline($m[3]).'</span>' : '').'</a></li>';
            }
        }

        return '<ul class="manual-tasks">'.$items.'</ul>';
    }

    /**
     * Legenda visiva: `- tipo:valore | etichetta | significato`, tipo =
     * colore (riquadro pieno), bordo (riquadro col bordo) o icona (testo).
     * Il colore entra in uno style solo se è un esadecimale: il resto del
     * testo passa dall'escape come ovunque.
     */
    private static function legend(string $markdown): string
    {
        $rows = '';

        foreach (explode("\n", $markdown) as $line) {
            $parts = array_map('trim', explode('|', ltrim(trim($line), '- ')));

            if (count($parts) !== 3 || ! preg_match('/^(colore|bordo|icona):(.+)$/u', $parts[0], $kind)) {
                continue;
            }

            // Nomi delle classi scritti per intero: Tailwind tiene nel CSS solo
            // le classi che trova scritte così nei file (con «manual-swatch-».$tipo
            // la regola del bordo spariva dal build).
            $hex = preg_match('/^#[0-9a-fA-F]{3,8}$/', $kind[2]) === 1;
            $sample = match (true) {
                $kind[1] === 'colore' && $hex => '<span class="manual-swatch manual-swatch-colore" style="background: '.$kind[2].'"></span>',
                $kind[1] === 'bordo' && $hex => '<span class="manual-swatch manual-swatch-bordo" style="border-color: '.$kind[2].'"></span>',
                default => '<span class="manual-swatch manual-swatch-icona">'.e($kind[2]).'</span>',
            };

            $rows .= '<li>'.$sample.'<span class="manual-legend-label">'.self::inline($parts[1]).'</span><span class="manual-legend-meaning">'.self::inline($parts[2]).'</span></li>';
        }

        return '<ul class="manual-legend">'.$rows.'</ul>';
    }

    private static function substitute(string $markdown, array $variables): string
    {
        if ($variables === []) {
            return $markdown;
        }

        return strtr($markdown, array_combine(
            array_map(fn (string $name) => '{{'.$name.'}}', array_keys($variables)),
            array_values($variables),
        ));
    }

    private static function markdown(string $markdown): string
    {
        return (string) Str::markdown($markdown, self::options(), [new HeadingPermalinkExtension]);
    }

    private static function inline(string $markdown): string
    {
        return trim((string) Str::inlineMarkdown($markdown, self::options()));
    }

    /** @return array<string, mixed> */
    private static function options(): array
    {
        return [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'before',
                'symbol' => '#',
                // Il clic copia il link (resources/js/manual-search.js).
                'title' => 'Copia il link a questa sezione',
            ],
        ];
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
