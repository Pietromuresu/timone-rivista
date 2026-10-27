<?php

namespace App\Models;

use App\Enums\FormatCheckStatus;
use App\Enums\ThumbnailStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * File allegato a un Content (Fase 4.1), non a una Page — a differenza di
 * PageFile, che è per costruzione legato alla pagina fisica (vedi
 * HANDOFF.md, limite noto già segnalato nel Punto 5 sul cruscotto
 * pubblicitario: "impossibile distinguere quale contenuto di una pagina
 * mista ha effettivamente il PDF"). Una riga qui risolve esattamente quel
 * limite per i nuovi contenuti che allegano un file da questa fase in poi
 * (i PageFile già esistenti restano legati alla pagina, nessuna migrazione
 * a ritroso: sono due concetti che continuano a convivere).
 *
 * Colonne pdf_page_number/format_check_status/measured_*_mm/
 * format_override_* (Punto 4 del prompt, 2026-09-02): parallele a quelle
 * già presenti su PageFile, stesso significato — un contenuto è ora
 * l'unico oggetto a cui si può allegare un PDF, quindi deve poter
 * riprodurre esattamente lo stesso comportamento multi-pagina/controllo
 * formato di prima, non una versione ridotta.
 */
class ContentFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_id',
        'uploaded_by',
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'size',
        'thumbnail_status',
        'pdf_page_number',
        'format_check_status',
        'measured_width_mm',
        'measured_height_mm',
        // Revisione 2, Fase 2.4: larghezza/altezza della pagina del file (ritaglio anteprima).
        'aspect_ratio',
        'format_override_confirmed_by',
        'format_override_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'thumbnail_status' => ThumbnailStatus::class,
            'pdf_page_number' => 'integer',
            'format_check_status' => FormatCheckStatus::class,
            'measured_width_mm' => 'decimal:1',
            'measured_height_mm' => 'decimal:1',
            'aspect_ratio' => 'float',
            'format_override_confirmed_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function formatOverrideConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'format_override_confirmed_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Stesso principio di PageFile::hasUnresolvedFormatMismatch() — vedi lì.
     */
    public function hasUnresolvedFormatMismatch(): bool
    {
        return $this->format_check_status === FormatCheckStatus::Mismatch
            && $this->format_override_confirmed_at === null;
    }

    /**
     * La pagina timone a cui QUESTO file specifico è rilevante (Punto 4
     * del prompt, 2026-09-02) — serve ad App\Jobs\GenerateContentFileThumbnail
     * per sapere quale pubblicità/formato controllare (stesso principio di
     * PageFile, che era invece sempre legato a un'unica pagina per
     * costruzione). Due casi:
     * - il pivot page_content punta esplicitamente a questo file
     *   (PageContent::contentFile(), pagina di un PDF multi-pagina);
     * - il contenuto ha un solo file (questo) e una pagina lo usa senza
     *   specificare un file esplicito (content_file_id nullo sul pivot) —
     *   il caso comune per un'immagine o un PDF di una sola pagina.
     * Null se il contenuto non è ancora assegnato a nessuna pagina, o se
     * ne ha più di una senza un riferimento esplicito (ambiguo, nessun
     * controllo formato possibile — stesso "NotApplicable" già usato per
     * gli altri casi non determinabili).
     */
    public function relevantPage(): ?Page
    {
        $viaPivot = PageContent::where('content_file_id', $this->id)->with('page')->first();

        if ($viaPivot !== null) {
            return $viaPivot->page;
        }

        if ($this->content->files->first()?->id !== $this->id) {
            return null;
        }

        $implicitPivots = PageContent::where('content_id', $this->content_id)
            ->whereNull('content_file_id')
            ->with('page')
            ->get();

        return $implicitPivots->count() === 1 ? $implicitPivots->first()->page : null;
    }
}
