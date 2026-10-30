<?php

namespace App\Support\Manual;

/**
 * Le immagini degli screenshot del manuale (2026-10-29, Fase 4): un file
 * `public/manuale/img/<nome>.webp` per blocco `:::screenshot nome`,
 * generato da `npm run manuale:screenshot`. Serviti come file statici da
 * nginx (sono in public/), non da PHP.
 *
 * Se il file manca la pagina mostra il segnaposto «[Screenshot da fare: …]»:
 * nessuna immagine inventata.
 */
final class ManualScreenshotFiles
{
    public const EXTENSION = 'webp';

    /**
     * Gli screenshot sono catturati a densità 2× (nitidi sugli schermi ad
     * alta densità): nella pagina si mostrano a metà dei loro pixel, mai
     * più grandi (un'immagine piccola allargata a tutta la colonna diventa
     * sfocata). Lo script li cattura sempre a un multiplo di questa densità.
     */
    public const PIXEL_RATIO = 2;

    public function __construct(
        private readonly string $directory,
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self((string) config('timone.manual_images'), rtrim(asset('manuale/img'), '/'));
    }

    public function path(string $name): string
    {
        return $this->directory.'/'.$name.'.'.self::EXTENSION;
    }

    /**
     * Indirizzo e misure dell'immagine, o null se non c'è. L'indirizzo
     * porta la data del file (?v=…): rigenerando gli screenshot il browser
     * non tiene la versione vecchia in cache.
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public function find(string $name): ?array
    {
        $path = $this->path($name);
        $size = is_file($path) ? @getimagesize($path) : false;

        if ($size === false) {
            return null;
        }

        return ['url' => $this->baseUrl.'/'.$name.'.'.self::EXTENSION.'?v='.filemtime($path), 'width' => $size[0], 'height' => $size[1]];
    }
}
