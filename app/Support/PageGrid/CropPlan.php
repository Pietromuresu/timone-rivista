<?php

namespace App\Support\PageGrid;

use App\Enums\FileFit;

/**
 * Risultato di ThumbnailCrop: cosa si vede del file in ciascun pezzo di un
 * contenuto. Tutte le «finestre» sono in % dell'immagine della miniatura
 * (che è la MediaBox del PDF, abbondanza compresa): la card, l'export del
 * timone (immagini già ritagliate) e il PDF sfogliabile le leggono da qui,
 * quindi mostrano la stessa porzione.
 *
 * - FullPage: `page` è dove cade la pagina del numero dentro l'immagine;
 *   ogni pezzo ha la sua `window`.
 * - Format: il file intero, adattato senza tagli al rettangolo principale.
 */
final class CropPlan
{
    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $trim  pagina rifilata nell'immagine
     * @param  array{0: float, 1: float, 2: float, 3: float}  $page  pagina del numero nell'immagine (FullPage)
     * @param  list<array{cells: GridRect, rect: array{x: float, y: float, w: float, h: float}, window: array{0: float, 1: float, 2: float, 3: float}}>  $pieces
     */
    public function __construct(
        public readonly FileFit $fit,
        public readonly array $trim,
        public readonly array $page,
        public readonly array $pieces,
    ) {}

    public function crops(): bool
    {
        return $this->fit === FileFit::FullPage;
    }

    /** Finestra del pezzo con queste celle (null se il contenuto non lo occupa). */
    public function windowFor(GridRect $cells): ?array
    {
        foreach ($this->pieces as $piece) {
            if ($piece['cells']->equals($cells)) {
                return $piece['window'];
            }
        }

        return null;
    }

    /**
     * Stile dell'<img> dentro il riquadro del pezzo (position: relative,
     * overflow: hidden): l'immagine è ingrandita e spostata in modo che nel
     * riquadro cada esattamente la finestra. Le proporzioni del riquadro
     * dell'<img> sono quelle dell'immagine, quindi `fill` non deforma.
     */
    public function imageStyle(GridRect $cells): string
    {
        $window = $this->crops() ? $this->windowFor($cells) : null;

        if ($window === null) {
            return 'position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain;';
        }

        [$x, $y, $w, $h] = $window;
        // Mai «-0%»: stesso testo per la stessa posizione.
        $f = fn (float $n) => round($n, 4) == 0 ? 0 : round($n, 4);

        return 'position: absolute; left: '.$f(-$x / $w * 100).'%; top: '.$f(-$y / $h * 100).'%; width: '.$f(100 / $w * 100).'%; height: '.$f(100 / $h * 100).'%; max-width: none; object-fit: fill;';
    }

    /**
     * Dove disegnare la sorgente intera perché il riquadro $at (pezzo, in
     * unità della pagina di destinazione, es. punti del PDF) mostri la sua
     * finestra: per il PDF sfogliabile, che disegna il file e poi ritaglia.
     * $trimmedSource: la sorgente è già la pagina rifilata (template FPDI
     * importato con la TrimBox), non l'immagine con l'abbondanza.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $at
     * @param  array{0: float, 1: float, 2: float, 3: float}  $window
     * @return array{x: float, y: float, w: float, h: float}
     */
    public function sourceRect(array $at, array $window, bool $trimmedSource): array
    {
        [$x, $y, $w, $h] = $window;

        if ($trimmedSource) {
            [$tx, $ty, $tw, $th] = $this->trim;
            [$x, $y, $w, $h] = [($x - $tx) / $tw * 100, ($y - $ty) / $th * 100, $w / $tw * 100, $h / $th * 100];
        }

        $width = $at['w'] * 100 / $w;
        $height = $at['h'] * 100 / $h;

        return ['x' => $at['x'] - $x / 100 * $width, 'y' => $at['y'] - $y / 100 * $height, 'w' => $width, 'h' => $height];
    }
}
