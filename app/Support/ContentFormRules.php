<?php

namespace App\Support;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\EditorialStatus;
use App\Models\Issue;
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
    /** Materiale: PDF o immagine, stesso ventaglio della pipeline miniature. */
    public const FILE_MAX_KILOBYTES = 20480;

    public const FILE = 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:'.self::FILE_MAX_KILOBYTES;

    /** @return array<string, mixed> */
    public static function rules(Issue $issue): array
    {
        return [
            'type' => 'required|in:articolo,pubblicita',
            'title' => 'required|string|max:255',
            'section_id' => 'nullable|exists:sections,id',
            'author' => 'nullable|string|max:180',
            'editorial_status' => 'required|in:'.implode(',', array_column(EditorialStatus::cases(), 'value')),
            'expected_length' => 'nullable|integer|min:1',
            'client' => 'required_if:type,pubblicita|nullable|string|max:180',
            'agency' => 'nullable|string|max:180',
            'format' => 'required_if:type,pubblicita|nullable|in:'.implode(',', array_column(AdFormat::cases(), 'value')),
            'confirmation_status' => 'required_if:type,pubblicita|nullable|in:'.implode(',', array_column(AdConfirmationStatus::cases(), 'value')),
            'commercial_notes' => 'nullable|string|max:2000',
            // Fase 3: pagine intere + frazione ammessa.
            'whole_pages' => 'nullable|integer|min:0|max:'.max(1, $issue->total_pages),
            'fraction_twelfths' => ['required', Rule::in(array_map('strval', [0, ...array_keys(Twelfths::FRACTIONS)]))],
            'file' => self::FILE,
        ];
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
        ];
    }
}
