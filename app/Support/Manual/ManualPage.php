<?php

namespace App\Support\Manual;

use InvalidArgumentException;

/**
 * Una pagina del manuale utente (2026-10-29): un file
 * `resources/manuale/<slug>.md` con il front matter e il testo.
 *
 * Il front matter è letto da un parser minimo invece che da symfony/yaml:
 * nel progetto symfony/yaml c'è solo come dipendenza di sviluppo (non è
 * nell'immagine di produzione) e il formato serve solo per `chiave: valore`
 * e liste `[a, b]`. Un campo mancante o un valore di forma sbagliata è un
 * errore esplicito, non un default silenzioso: lo intercetta il test sulle
 * pagine prima che arrivi in produzione.
 */
final class ManualPage
{
    public const REQUIRED = ['titolo', 'slug', 'categoria', 'ruoli', 'parole_chiave', 'sinonimi', 'ordine'];

    public const LISTS = ['ruoli', 'parole_chiave', 'sinonimi'];

    public const ROLES = ['admin', 'redattore', 'commerciale', 'sola_lettura'];

    /**
     * @param  list<string>  $ruoli
     * @param  list<string>  $paroleChiave
     * @param  list<string>  $sinonimi
     */
    public function __construct(
        public readonly string $titolo,
        public readonly string $slug,
        public readonly string $categoria,
        public readonly array $ruoli,
        public readonly array $paroleChiave,
        public readonly array $sinonimi,
        public readonly int $ordine,
        public readonly string $body,
    ) {}

    public static function parse(string $source, string $origin = 'manuale'): self
    {
        $source = str_replace("\r\n", "\n", $source);

        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $source, $m)) {
            throw new InvalidArgumentException("{$origin}: manca il front matter tra due righe «---».");
        }

        $meta = [];

        foreach (explode("\n", trim($m[1])) as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (! preg_match('/^([a-z_]+):\s*(.*)$/', $line, $kv)) {
                throw new InvalidArgumentException("{$origin}: riga del front matter non valida: «{$line}».");
            }

            $meta[$kv[1]] = in_array($kv[1], self::LISTS, true) ? self::parseList($kv[2], $origin) : trim($kv[2]);
        }

        foreach (self::REQUIRED as $key) {
            if (! array_key_exists($key, $meta) || $meta[$key] === '' || $meta[$key] === []) {
                throw new InvalidArgumentException("{$origin}: manca «{$key}» nel front matter.");
            }
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $meta['slug'])) {
            throw new InvalidArgumentException("{$origin}: slug «{$meta['slug']}» non valido (solo minuscole, cifre e trattini).");
        }

        if (! ctype_digit($meta['ordine'])) {
            throw new InvalidArgumentException("{$origin}: «ordine» deve essere un numero intero.");
        }

        if (($unknown = array_diff($meta['ruoli'], self::ROLES)) !== []) {
            throw new InvalidArgumentException("{$origin}: ruoli sconosciuti: ".implode(', ', $unknown).'.');
        }

        return new self($meta['titolo'], $meta['slug'], $meta['categoria'], $meta['ruoli'], $meta['parole_chiave'], $meta['sinonimi'], (int) $meta['ordine'], ltrim($m[2], "\n"));
    }

    /**
     * Il blocco «In breve» in markdown: la risposta breve della pagina
     * (deve stare in piedi da sola: è anche il testo che mostrerà
     * l'assistente). Null se manca: lo segnala il test sulle pagine.
     */
    public function inBreve(): ?string
    {
        foreach (ManualBlocks::split($this->body) as $block) {
            if ($block['type'] === 'in-breve') {
                return $block['markdown'];
            }
        }

        return null;
    }

    /**
     * Impronte della pagina e del suo «In breve» (2026-10-29): salvate con
     * ogni voto dell'assistente, dicono al job notturno se il voto è stato
     * dato sulla versione di oggi (vale intero), su una pagina poi cambiata
     * (vale ½) o su un «In breve» che non c'è più (vale 0).
     */
    public function versionHash(): string
    {
        return sha1($this->body);
    }

    public function inBreveHash(): string
    {
        return sha1(trim((string) $this->inBreve()));
    }

    /** @return list<string> */
    private static function parseList(string $value, string $origin): array
    {
        $value = trim($value);

        if (! preg_match('/^\[(.*)\]$/', $value, $m)) {
            throw new InvalidArgumentException("{$origin}: una lista va scritta tra parentesi quadre: «{$value}».");
        }

        return array_values(array_filter(array_map('trim', explode(',', $m[1])), fn ($item) => $item !== ''));
    }
}
