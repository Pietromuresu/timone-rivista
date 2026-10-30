<?php

namespace App\Support\MagazineExport;

use App\Enums\ContentType;
use App\Enums\FileFit;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use App\Models\Page;
use App\Models\PageFile;
use App\Support\PageGrid\CropPlan;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfReader\PageBoundaries;
use setasign\Fpdi\Tcpdf\Fpdi;
use Throwable;

/**
 * Compone il PDF sfogliabile di un numero (revisione 2, Fase 5,
 * 2026-10-28) con TCPDF + FPDI (scelta dell'utente): ogni pagina del
 * numero diventa un foglio A4 con la pagina scalata e centrata (PageBox),
 * nell'ordine di MagazinePageSequence, e ogni contenuto è disegnato nel suo
 * rettangolo della griglia 6×6 (Page::layout()):
 * - PDF: la pagina del file corrispondente a QUESTA pagina del timone
 *   (Page::previewFileFor()), importata come oggetto vettoriale;
 * - stessa regola e stesse finestre di ritaglio delle anteprime
 *   (ThumbnailCrop, dal 2026-10-30): «a pagina intera» → la pagina
 *   rifilata del file copre la pagina, centrata, e si vede solo la porzione
 *   di ogni pezzo (clipping vettoriale, qualità invariata); «nel formato» →
 *   intero dentro il rettangolo;
 * - senza file, o file assente/illeggibile su disco → riquadro tratteggiato
 *   con titolo, tipo e «File mancante». Mai un'eccezione per un file: il
 *   numero si esporta comunque.
 * Il file si apre a coppie (TwoPageRight) e ha un segnalibro per pagina.
 *
 * Classe di I/O (filesystem, librerie PDF), non iniettata: un'unica
 * implementazione, come le altre App\Support (CLAUDE.md, regola 3).
 */
final class MagazinePdfComposer
{
    private const FONT = 'dejavusans';

    private Fpdi $pdf;

    private SourceFiles $sources;

    /** @var array<string, string|false> "percorso#pagina" => template FPDI (false = non importabile) */
    private array $templates = [];

    private int $missing = 0;

    public function __construct(private readonly string $workDir) {}

    /** Scrive il PDF in $outputPath. */
    public function compose(Issue $issue, string $outputPath): ComposeResult
    {
        $this->sources = new SourceFiles($this->workDir);
        $this->templates = [];
        $this->missing = 0;
        $this->pdf = $this->newDocument($issue);

        $box = PageBox::forRatio($issue->pageAspectRatio());
        $pages = MagazinePageSequence::for($issue);

        foreach ($pages as ['page' => $page, 'label' => $label]) {
            $this->drawPage($page, $label, $box);
        }

        $this->pdf->Output($outputPath, 'F');

        return new ComposeResult($pages->count(), $this->missing);
    }

    private function newDocument(Issue $issue): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCreator('Timone Rivista');
        $pdf->SetTitle($issue->magazine->name.' — '.$issue->title);
        // Sfogliabile: si apre a coppie affiancate con la copertina da sola.
        $pdf->SetDisplayMode('fullpage', 'TwoPageRight');

        return $pdf;
    }

    private function drawPage(Page $page, string $label, PageBox $box): void
    {
        $this->pdf->AddPage();
        $this->pdf->Bookmark($label, 0, 0, '', '', [0, 0, 0]);

        // Bordo sottile della pagina del numero: dove finisce la rivista.
        $this->pdf->SetLineStyle(['width' => 0.15, 'dash' => 0, 'color' => [200, 200, 200]]);
        $this->pdf->Rect($box->x, $box->y, $box->width, $box->height, 'D');

        if ($page->contents->isNotEmpty()) {
            foreach ($page->layout()['blocks'] as $block) {
                $this->drawBlock($block, $box);
            }
        } elseif (($legacy = $page->files->first()) !== null) {
            // PDF caricato direttamente sulla pagina prima del 2026-09-02.
            $this->placeFile($legacy, FileFit::FullPage, [$box->full()], $box->full(), $box, null, null);
        } else {
            $this->text($box->x, $box->y + $box->height / 2 - 3, $box->width, 'Pagina bianca', 8, [150, 150, 150]);
        }

        $this->text($box->x, $box->footerY(), $box->width, $label, 7, [90, 90, 90]);
    }

    /** @param  array{content: Content, kind: string, rect: array, pieces: list<array>, file: ContentFile|null, fit: FileFit, crop: CropPlan|null}  $block */
    private function drawBlock(array $block, PageBox $box): void
    {
        $rect = $box->rect($block['rect']);
        $pieces = array_map(fn (array $piece) => $box->rect($piece), $block['pieces'] ?: [$block['rect']]);

        if ($block['file'] !== null && $this->placeFile($block['file'], $block['fit'], $pieces, $rect, $box, $block['content'], $block['crop'] ?? null)) {
            return;
        }

        $this->missing++;
        $this->drawMissing($block['content'], $rect, $pieces, $block['file'] === null ? 'File mancante' : 'File mancante (non trovato o non leggibile)');
    }

    /**
     * Disegna il file; false se non si può (assente, vuoto, illeggibile).
     *
     * @param  list<array{x: float, y: float, w: float, h: float}>  $clips  pezzi visibili (uno, o più per una L)
     */
    private function placeFile(ContentFile|PageFile $file, FileFit $fit, array $clips, array $rect, PageBox $box, ?Content $content, ?CropPlan $crop): bool
    {
        $path = $this->sources->path($file);

        if ($path === null) {
            return false;
        }

        $isPdf = SourceFiles::isPdf($file, $path);
        $template = $isPdf ? $this->template($path, max(1, (int) ($file->pdf_page_number ?? 1))) : null;

        if ($isPdf && $template === null) {
            return false;
        }

        try {
            if ($crop !== null && $crop->crops()) {
                // 2026-10-30: stessa finestra della card (CropPlan): per ogni
                // pezzo la sorgente è disegnata in modo che nel pezzo cada la
                // sua porzione, poi ritagliata. Il template FPDI è già la
                // pagina rifilata solo se il PDF ha una TrimBox vera.
                $trimmedSource = $template !== null && ($file->trim_box['trim'] ?? null) !== null;

                foreach ($crop->pieces as $index => $piece) {
                    $clip = $clips[$index] ?? $box->rect($piece['rect']);
                    $this->pdf->StartTransform();
                    $this->pdf->Rect($clip['x'], $clip['y'], $clip['w'], $clip['h'], 'CNZ');
                    $this->drawSource($template, $path, $crop->sourceRect($clip, $piece['window'], $trimmedSource));
                    $this->pdf->StopTransform();
                }
            } elseif ($fit === FileFit::FullPage) {
                // La pagina intera del file su tutta la pagina del numero,
                // visibile solo dentro i pezzi del contenuto (vecchi file di pagina).
                foreach ($clips as $clip) {
                    $this->pdf->StartTransform();
                    $this->pdf->Rect($clip['x'], $clip['y'], $clip['w'], $clip['h'], 'CNZ');
                    $this->drawSource($template, $path, $box->full());
                    $this->pdf->StopTransform();
                }
            } else {
                [$sourceWidth, $sourceHeight] = $template !== null
                    ? [$this->pdf->getTemplateSize($template)['width'], $this->pdf->getTemplateSize($template)['height']]
                    : $this->imageSize($path);
                $this->drawSource($template, $path, PageBox::contain($rect, (float) $sourceWidth, (float) $sourceHeight));
            }
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    /** @param  array{x: float, y: float, w: float, h: float}  $at */
    private function drawSource(?string $template, string $path, array $at): void
    {
        if ($template !== null) {
            $this->pdf->useTemplate($template, $at['x'], $at['y'], $at['w'], $at['h']);

            return;
        }

        $this->pdf->Image($path, $at['x'], $at['y'], $at['w'], $at['h'], '', '', '', false, 300);
    }

    /**
     * Template FPDI della pagina $pageNumber (TrimBox: la pagina rifilata,
     * senza abbondanza). Se FPDI non apre il file (xref compresse, PDF
     * 1.5+) lo riprova sulla copia riscritta in PDF 1.4 da Ghostscript.
     */
    private function template(string $path, int $pageNumber): ?string
    {
        $key = $path.'#'.$pageNumber;

        if (! array_key_exists($key, $this->templates)) {
            $this->templates[$key] = $this->import($path, $pageNumber)
                ?? (($copy = $this->sources->rewrittenPdf($path)) !== null ? $this->import($copy, $pageNumber) : null)
                ?? false;
        }

        return $this->templates[$key] ?: null;
    }

    private function import(string $path, int $pageNumber): ?string
    {
        try {
            $count = $this->pdf->setSourceFile($path);

            return $pageNumber <= $count ? $this->pdf->importPage($pageNumber, PageBoundaries::TRIM_BOX) : null;
        } catch (PdfParserException|Throwable) {
            return null;
        }
    }

    /** @return array{0: float, 1: float} larghezza e altezza in pixel, per le proporzioni */
    private function imageSize(string $path): array
    {
        $size = @getimagesize($path);

        return $size ? [(float) $size[0], (float) $size[1]] : [1.0, 1.0];
    }

    /** @param  list<array{x: float, y: float, w: float, h: float}>  $pieces */
    private function drawMissing(Content $content, array $rect, array $pieces, string $reason): void
    {
        $this->pdf->SetLineStyle(['width' => 0.3, 'dash' => '1.5,1', 'color' => [180, 83, 9]]);
        $this->pdf->SetFillColor(255, 251, 235);

        foreach ($pieces as $piece) {
            $this->pdf->Rect($piece['x'], $piece['y'], $piece['w'], $piece['h'], 'DF');
        }

        $type = $content->type === ContentType::Pubblicita ? ($content->isPlaceholder() ? 'Pubblicità (segnaposto)' : 'Pubblicità') : 'Articolo';
        // Testo proporzionato al riquadro: leggibile su una pagina intera, che ci stia in una vetrina.
        $size = match (true) {
            $rect['w'] >= 100 && $rect['h'] >= 60 => 12,
            $rect['w'] >= 40 => 9,
            default => 6,
        };

        $this->pdf->SetTextColor(68, 64, 60);
        $this->pdf->SetFont(self::FONT, 'B', $size);
        $this->pdf->SetXY($rect['x'] + 1.5, $rect['y'] + 1.5);
        $this->pdf->MultiCell($rect['w'] - 3, 0, $content->displayLabel(), 0, 'L', false, 1, '', '', true, 0, false, true, max(4, $rect['h'] / 2), 'T');
        $this->pdf->SetFont(self::FONT, '', $size - 1);
        $this->pdf->SetX($rect['x'] + 1.5);
        $this->pdf->MultiCell($rect['w'] - 3, 0, $type, 0, 'L', false, 1);
        $this->pdf->SetTextColor(180, 83, 9);
        $this->pdf->SetX($rect['x'] + 1.5);
        $this->pdf->MultiCell($rect['w'] - 3, 0, $reason, 0, 'L', false, 1);
    }

    /** @param  array{0: int, 1: int, 2: int}  $color */
    private function text(float $x, float $y, float $width, string $text, int $size, array $color): void
    {
        $this->pdf->SetFont(self::FONT, '', $size);
        $this->pdf->SetTextColor(...$color);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($width, 4, $text, 0, 0, 'C');
    }
}
