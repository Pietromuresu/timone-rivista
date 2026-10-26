<?php

namespace App\Support;

use App\Enums\AdFormat;
use Illuminate\Validation\ValidationException;

/**
 * Quali pagine di un PDF pubblicitario usare (Fase 1, bug 8, 2026-10-26).
 *
 * Prima un PDF con più pagine di quante ne occupa il formato era un errore
 * bloccante ("Il PDF ha 3 pagine, ma il formato «Copertina (2ª/3ª/4ª)» ne
 * occupa 1"). Ora il numero di pagine del PDF è solo un suggerimento:
 * quando supera l'occupazione del formato l'utente sceglie se usarle TUTTE
 * (la pubblicità occuperà N pagine) o solo un intervallo da X a Y. Il
 * formato resta il dato commerciale del listino; l'occupazione segue la
 * scelta.
 *
 * Un PDF con MENO pagine del formato (es. una doppia pagina consegnata come
 * pagina singola) resta com'era: nessuna scelta, l'occupazione è quella del
 * formato.
 *
 * Classe pura (nessuna query, nessun I/O): la usano ContentCreate (UI) e
 * ContentCreator (salvataggio), testata a sé in tests/Unit/AdPdfPagePlanTest.php.
 */
final class AdPdfPagePlan
{
    public const ALL = 'all';

    public const RANGE = 'range';

    private function __construct(
        public readonly int $from,
        public readonly int $to,
        public readonly int $pdfPageCount,
    ) {}

    /**
     * Vero quando il PDF ha più pagine di quante ne occupa il formato: solo
     * allora il modulo chiede quali usare.
     */
    public static function needsChoice(AdFormat $format, ?int $pdfPageCount): bool
    {
        return $pdfPageCount !== null && $pdfPageCount > self::formatSpan($format);
    }

    public static function formatSpan(AdFormat $format): int
    {
        return PageOccupancy::forAdFormat($format)->pagesSpanned();
    }

    /**
     * Piano dalla scelta dell'utente. Errori sotto le chiavi del modulo
     * (`pdfPageChoice`, `pdfPageFrom`, `pdfPageTo`), così ContentCreate li
     * mostra accanto ai campi.
     *
     * @throws ValidationException
     */
    public static function fromChoice(int $pdfPageCount, ?string $choice, ?int $from, ?int $to): self
    {
        if ($choice === self::ALL) {
            return new self(1, $pdfPageCount, $pdfPageCount);
        }

        if ($choice !== self::RANGE) {
            throw ValidationException::withMessages([
                'pdfPageChoice' => "Il PDF ha {$pdfPageCount} pagine: scegli se usarle tutte o solo alcune.",
            ]);
        }

        $errors = [];

        if ($from === null || $from < 1 || $from > $pdfPageCount) {
            $errors['pdfPageFrom'] = "Indica una pagina iniziale tra 1 e {$pdfPageCount}.";
        }

        if ($to === null || $to < 1 || $to > $pdfPageCount) {
            $errors['pdfPageTo'] = "Indica una pagina finale tra 1 e {$pdfPageCount}.";
        } elseif ($from !== null && $to < $from) {
            $errors['pdfPageTo'] = 'La pagina finale non può essere prima di quella iniziale.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new self($from, $to, $pdfPageCount);
    }

    /** Piano "tutto il PDF", per i casi in cui non serve una scelta. */
    public static function whole(int $pdfPageCount): self
    {
        return new self(1, $pdfPageCount, $pdfPageCount);
    }

    public function pageCount(): int
    {
        return $this->to - $this->from + 1;
    }

    /**
     * Numeri di pagina ORIGINALI del PDF da usare, in ordine: diventano
     * `content_files.pdf_page_number`, così ogni pagina del timone mostra
     * la pagina del PDF che le corrisponde.
     *
     * @return list<int>
     */
    public function pdfPages(): array
    {
        return range($this->from, $this->to);
    }

    /**
     * Occupazione risultante: quella del formato se il numero di pagine
     * scelte coincide con la sua (es. "usa solo la pagina 2" su una mezza
     * pagina resta mezza pagina), altrimenti N pagine intere.
     */
    public function occupancy(AdFormat $format): PageOccupancy
    {
        return $this->pageCount() === self::formatSpan($format)
            ? PageOccupancy::forAdFormat($format)
            : PageOccupancy::from($this->pageCount());
    }

    /**
     * Vero se la scelta fa occupare alla pubblicità più pagine del formato:
     * in quel caso ogni pagina è occupata per intero (vedi
     * ContentCreator::create(), percentuale 100 se non indicata a mano).
     */
    public function expandsFormat(AdFormat $format): bool
    {
        return $this->pageCount() > self::formatSpan($format);
    }
}
