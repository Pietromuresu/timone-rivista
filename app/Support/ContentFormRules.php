<?php

namespace App\Support;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\EditorialStatus;
use App\Models\Issue;
use App\Support\Uploads\UploadLimits;
use Illuminate\Validation\Rule;

/**
 * Regole di validazione dei campi di un contenuto, condivise da creazione
 * (App\Livewire\Timone\ContentCreate) e modifica (ContentEdit, Fase 4,
 * 2026-10-27): stessi campi, stessi vincoli, un solo punto da cambiare.
 * I vincoli che dipendono da più campi insieme (occupazione > 0, coerenza
 * col PDF) restano in ContentCreator/ContentUpdater, che valgono per ogni
 * percorso di scrittura, non solo per i moduli.
 */
final class ContentFormRules
{
    /**
     * Limite di dimensione del materiale, in KB. Da config (timone.upload_max_kb,
     * unico punto del limite): prima era una costante a 20 MB, più stretta di
     * PHP/nginx/Livewire (100 MB), e rifiutava i PDF di stampa (revisione 2,
     * Fase 1, bug 5).
     */
    public static function maxKilobytes(): int
    {
        return (int) config('timone.upload_max_kb');
    }

    /** Materiale: PDF o immagine, stesso ventaglio della pipeline miniature. */
    public static function fileRule(): string
    {
        return 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:'.self::maxKilobytes();
    }

    /**
     * $placeholder (Fase 5): modificando una pubblicità segnaposto il
     * cliente può restare vuoto — finché manca, resta un segnaposto.
     *
     * @return array<string, mixed>
     */
    public static function rules(Issue $issue, bool $placeholder = false): array
    {
        return [
            'type' => 'required|in:articolo,pubblicita',
            'title' => 'required|string|max:255',
            'section_id' => 'nullable|exists:sections,id',
            'author' => 'nullable|string|max:180',
            'editorial_status' => 'required|in:'.implode(',', array_column(EditorialStatus::cases(), 'value')),
            'expected_length' => 'nullable|integer|min:1',
            'client' => ($placeholder ? '' : 'required_if:type,pubblicita|').'nullable|string|max:180',
            'agency' => 'nullable|string|max:180',
            'format' => 'required_if:type,pubblicita|nullable|in:'.implode(',', array_column(AdFormat::cases(), 'value')),
            'confirmation_status' => 'required_if:type,pubblicita|nullable|in:'.implode(',', array_column(AdConfirmationStatus::cases(), 'value')),
            'commercial_notes' => 'nullable|string|max:2000',
            // Fase 3: pagine intere + frazione ammessa.
            'whole_pages' => 'nullable|integer|min:0|max:'.max(1, $issue->total_pages),
            'fraction_twelfths' => ['required', Rule::in(array_map('strval', [0, ...array_keys(Twelfths::FRACTIONS)]))],
            'file' => self::fileRule(),
        ];
    }

    /**
     * Messaggio chiaro per un file rifiutato dall'upload TEMPORANEO di
     * Livewire perché troppo grande (revisione 2, Fase 1, bug 5): quel
     * controllo arriva prima della regola del modulo e restituiva il testo
     * generico «Il campo files.0 non può superare 102400 kilobyte». Lo si
     * riconosce dal limite in KB citato negli errori; null per gli altri
     * errori, che restano quelli di Livewire.
     */
    public static function temporaryUploadMessage(?string $errorsInJson): ?string
    {
        if ($errorsInJson === null || ! str_contains($errorsInJson, (string) self::maxKilobytes())) {
            return null;
        }

        return self::messages()['file.max'];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'whole_pages.integer' => 'Le pagine intere devono essere un numero intero.',
            'whole_pages.min' => 'Le pagine intere non possono essere negative.',
            'whole_pages.max' => 'Il numero ha solo :max pagine interne.',
            'fraction_twelfths.in' => 'Frazione non valida: scegline una dall\'elenco.',
            'format.required_if' => 'Scegli il formato della pubblicità.',
            // Revisione 2, Fase 1, bug 5: il limite in MB, non "102400 kilobyte".
            'file.max' => 'Il file supera il limite di '.UploadLimits::humanMegabytes(self::maxKilobytes() * 1024).': riducilo (es. PDF ottimizzato per la stampa) e riprova.',
            'file.mimes' => 'Formato non accettato: carica un PDF o un\'immagine JPG, PNG o WebP.',
        ];
    }
}
