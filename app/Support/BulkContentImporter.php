<?php

namespace App\Support;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\Content;
use App\Models\Issue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Caricamento massivo (revisione 2, Fase 3.2, 2026-10-28): un file → un
 * contenuto "da assegnare", senza modulo. Il componente
 * App\Livewire\Timone\BulkUpload riceve i file UNO ALLA VOLTA (il browser li
 * manda in sequenza) e per ciascuno chiama import(): un file non valido non
 * blocca gli altri perché ogni chiamata è indipendente.
 *
 * Nessuna logica di creazione propria: valida con la stessa regola del
 * modulo (ContentFormRules::fileRule()), misura il PDF con
 * ContentCreator::pdfPageCount() (che rifiuta i PDF illeggibili) e crea con
 * ContentCreator::create() — stessi file per pagina, miniature, cronologia
 * e regola del segnaposto (AdPlaceholder) di un contenuto creato a mano.
 */
final class BulkContentImporter
{
    /**
     * Valori di default di un contenuto caricato in blocco, da correggere a
     * mano: occupazione = pagine intere quante le pagine del PDF (1 per
     * un'immagine); una pubblicità nasce "pagina intera" (usando tutte le
     * pagine del PDF) senza cliente — col file è comunque una pubblicità
     * vera, non un segnaposto.
     *
     * @throws ValidationException file non accettato (tipo, dimensione, PDF illeggibile)
     */
    public static function import(Issue $issue, ContentType $type, UploadedFile $file, ?int $userId): BulkImportResult
    {
        Validator::make(
            ['file' => $file],
            ['file' => 'required|'.ContentFormRules::fileRule()],
            ContentFormRules::messages(),
        )->validate();

        $pdfPageCount = ContentCreator::pdfPageCount($file);
        $pages = $pdfPageCount ?? 1;

        $baseTitle = self::titleFromFilename($file->getClientOriginalName());
        $title = self::uniqueTitle($issue, $baseTitle);

        $data = [
            'type' => $type->value,
            'title' => $title,
            'whole_pages' => $pages,
            'fraction_twelfths' => '0',
            'editorial_status' => EditorialStatus::DaScrivere->value,
            'client' => null,
            'format' => $type === ContentType::Pubblicita ? AdFormat::PaginaIntera->value : null,
            'confirmation_status' => AdConfirmationStatus::InTrattativa->value,
            // Pubblicità con PDF più lungo del formato: "usa tutte le pagine".
            'pdf_page_from' => 1,
            'pdf_page_to' => $pages,
        ];

        $content = ContentCreator::create($issue, $data, $file, $userId, $pdfPageCount);

        return new BulkImportResult($content, $title !== $baseTitle ? $baseTitle : null);
    }

    /**
     * «servizio_moto-elettrica  v2.pdf» → «servizio moto elettrica v2»:
     * senza estensione, underscore e trattini come spazi, spazi multipli
     * ridotti. Un nome che resta vuoto (es. «___.pdf») diventa «Senza titolo».
     */
    public static function titleFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/[\s_\-]+/u', ' ', $name) ?? $name;
        $name = trim($name);

        return mb_substr($name !== '' ? $name : 'Senza titolo', 0, 240);
    }

    /**
     * Titolo libero nel numero: se esiste già (senza distinguere maiuscole,
     * come il confronto di MySQL), aggiunge « (2)», « (3)»… La specifica
     * chiede di avvisare ma creare comunque.
     */
    public static function uniqueTitle(Issue $issue, string $title): string
    {
        $taken = Content::where('issue_id', $issue->id)->pluck('title')
            ->map(fn (string $existing) => mb_strtolower($existing))
            ->flip();

        if (! $taken->has(mb_strtolower($title))) {
            return $title;
        }

        $suffix = 2;

        while ($taken->has(mb_strtolower("{$title} ({$suffix})"))) {
            $suffix++;
        }

        return "{$title} ({$suffix})";
    }
}
