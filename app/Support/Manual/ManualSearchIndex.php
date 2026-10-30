<?php

namespace App\Support\Manual;

use App\Support\ManualRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Indice di ricerca del manuale (2026-10-29), servito come JSON a
 * /manuale/indice.json e usato dal browser con MiniSearch
 * (resources/js/manual-search-core.js): nessuna chiamata esterna.
 *
 * Un documento per pagina (titolo, parole chiave, sinonimi, «In breve») e
 * uno per ogni sezione `##` della pagina, con l'ancora vera del titolo
 * (letta dall'HTML convertito, come l'indice laterale): un risultato porta
 * direttamente alla sezione. Il testo è quello che l'utente legge (HTML
 * convertito e ripulito), non il markdown con i suoi segni.
 *
 * Cache: la chiave contiene l'impronta dei file (percorso, data, dimensione
 * delle pagine e di sinonimi.yml), quindi cambiando un file l'indice si
 * rigenera da solo alla richiesta successiva.
 */
final class ManualSearchIndex
{
    /** Quanti compiti di «Cosa vuoi fare?» proporre quando la ricerca non trova nulla. */
    public const FALLBACK_TASKS = 5;

    /**
     * Pagine fuori dall'indice: «Cosa vuoi fare?» elenca tutti i compiti
     * con le loro parole, quindi risponderebbe a quasi ogni ricerca prima
     * della pagina che spiega davvero (i suoi riquadri sono già sulla
     * pagina d'ingresso e tra i suggerimenti quando non si trova nulla).
     */
    public const NOT_INDEXED = ['cosa-vuoi-fare'];

    public function __construct(
        private readonly ManualRepository $repository,
        private readonly string $synonymsPath,
    ) {}

    public static function fromConfig(): self
    {
        return new self(ManualRepository::fromConfig(), (string) config('timone.manual_dir').'/sinonimi.yml');
    }

    /** @return array<string, mixed> */
    public function cached(): array
    {
        return Cache::remember('manual-search-index:'.$this->fingerprint(), now()->addDay(), fn () => $this->build());
    }

    /**
     * @return array{versione: string, documenti: list<array<string, mixed>>, sinonimi: list<list<string>>, lingua: array<string, mixed>, compiti: list<array{titolo: string, url: string}>}
     */
    public function build(): array
    {
        $documents = [];

        foreach ($this->repository->all()->except(self::NOT_INDEXED) as $page) {
            $url = route('documentation.page', $page->slug, false);
            $html = ManualRenderer::renderPage(
                $page,
                fn (string $slug) => route('documentation.page', $slug, false),
                ['APP_URL' => rtrim((string) config('app.url'), '/')],
                // Fase 4: con l'immagine nel testo indicizzato entra la
                // spiegazione dei numeri, non il segnaposto «Screenshot da fare».
                ManualScreenshotFiles::fromConfig()->find(...),
            )['html'];
            $common = [
                'slug' => $page->slug,
                'titolo' => $page->titolo,
                'categoria' => $page->categoria,
                'ruoli' => $page->ruoli,
            ];

            // Prima del primo «##»: il blocco «In breve» e l'introduzione.
            $parts = preg_split('/(?=<h2>)/', $html);
            $documents[] = $common + [
                'id' => $page->slug,
                'url' => $url,
                'ancora' => null,
                'sezione' => null,
                'parole_chiave' => implode(' ', $page->paroleChiave),
                'sinonimi' => implode(' ', $page->sinonimi),
                'testo' => self::plain(array_shift($parts)),
            ];

            foreach ($parts as $part) {
                if (! preg_match('/^<h2>\s*<a id="([^"]+)"[^>]*>.*?<\/a>(.*?)<\/h2>(.*)$/s', $part, $m) || $m[1] === 'vedi-anche') {
                    continue;
                }

                $documents[] = $common + [
                    'id' => $page->slug.'#'.$m[1],
                    'url' => $url.'#'.$m[1],
                    'ancora' => $m[1],
                    'sezione' => self::plain($m[2]),
                    'parole_chiave' => '',
                    'sinonimi' => '',
                    'testo' => self::plain($m[3]),
                ];
            }
        }

        return [
            'versione' => $this->fingerprint(),
            'documenti' => $documents,
            'sinonimi' => ManualSynonyms::load($this->synonymsPath),
            'lingua' => [
                'piega' => ItalianText::FOLD,
                'paroleVuote' => ItalianText::STOPWORDS,
                'desinenze' => ItalianText::SUFFIXES,
                'radiceMinima' => ItalianText::MIN_STEM,
            ],
            'compiti' => $this->tasks(),
        ];
    }

    /** Impronta dei file del manuale: cambia appena cambia un file. */
    public function fingerprint(): string
    {
        // Anche gli screenshot (Fase 4): un'immagine aggiunta cambia il testo indicizzato.
        $files = array_merge(
            File::glob(config('timone.manual_dir').'/*.md') ?: [],
            File::exists($this->synonymsPath) ? [$this->synonymsPath] : [],
            File::glob(config('timone.manual_images').'/*.'.ManualScreenshotFiles::EXTENSION) ?: [],
        );

        return substr(sha1(implode('|', array_map(fn ($f) => $f.':'.filemtime($f).':'.filesize($f).':'.md5_file($f), $files))), 0, 16);
    }

    /**
     * I primi compiti di «Cosa vuoi fare?», da proporre quando la ricerca non
     * trova nulla (li scrive anche la vista, senza aspettare il JSON).
     *
     * @return list<array{titolo: string, url: string}>
     */
    public function tasks(): array
    {
        $page = $this->repository->find('cosa-vuoi-fare');

        if ($page === null) {
            return [];
        }

        preg_match_all('/^- \[([^\]]+)\]\(manuale:([a-z0-9-]+)\)/m', $page->body, $m, PREG_SET_ORDER);

        return array_map(fn ($task) => ['titolo' => $task[1], 'url' => route('documentation.page', $task[2], false)], array_slice($m, 0, self::FALLBACK_TASKS));
    }

    private static function plain(string $html): string
    {
        // Ogni tag diventa uno spazio: le parole di due blocchi vicini non si
        // incollano («In breveScegli»); poi via gli spazi prima della punteggiatura.
        $text = preg_replace('/<[^>]+>/', ' ', preg_replace('/<a [^>]*class="heading-permalink"[^>]*>.*?<\/a>/s', '', $html));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace(['/\s+/u', '/ ([,.;:!?»)])/u', '/([«(]) /u'], [' ', '$1', '$1'], $text));
    }
}
