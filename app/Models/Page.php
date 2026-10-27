<?php

namespace App\Models;

use App\Enums\CoverSlot;
use App\Enums\PageContentType;
use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'position',
        'content_type',
        'status',
        'notes',
        'locked_at',
        'locked_by',
        'page_group_id',
        'cover_slot',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'content_type' => PageContentType::class,
            'status' => PageStatus::class,
            'locked_at' => 'datetime',
            // Fase 2 (2026-10-26): `role` (copertina1…battente) non è più
            // letto dal codice — resta in tabella solo come traccia e per
            // il rollback della conversione. La copertina è `cover_slot`.
            'cover_slot' => CoverSlot::class,
        ];
    }

    /**
     * Fase 2: una copertina è una riga di `pages` con `cover_slot` e senza
     * posizione — fuori dalla sequenza e dai conteggi delle pagine interne.
     */
    public function isCover(): bool
    {
        return $this->cover_slot !== null;
    }

    /**
     * Nome della pagina nei messaggi: "pagina 5" o "II di copertina". Mai
     * `position` da solo, che per una copertina è NULL.
     */
    public function label(): string
    {
        return $this->cover_slot?->label() ?? "pagina {$this->position}";
    }

    /** Come label() ma compatto, per elenchi: "5" o "II cop.". */
    public function shortLabel(): string
    {
        return $this->cover_slot?->shortLabel() ?? (string) $this->position;
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Gruppo di pagine "collegate" (Fase 2.3): null se la pagina non è
     * collegata a nessuna. Vedi PageGroup e App\Support\PageGroupResolver.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PageGroup::class, 'page_group_id');
    }

    /**
     * §6.6 dello spec: "una pagina bloccata non può essere spostata,
     * eliminata, sovrascritta, o modificata". Copre solo l'azione diretta
     * sulla pagina bloccata stessa — non impedisce che la sua posizione
     * cambi come effetto collaterale dello spostamento di un'altra pagina
     * che le "slitta attraverso" (richiederebbe riscrivere l'algoritmo di
     * riordino per trattare le pagine bloccate come ostacoli fissi, non
     * solo una guardia sull'id target — fuori scopo, documentato in
     * HANDOFF.md).
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Contenuti assegnati a questa pagina, con la percentuale di spazio occupato.
     */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'page_content')
            ->using(PageContent::class)
            // content_file_id (Punto 4, 2026-09-02): quale file di quel
            // contenuto è rilevante per QUESTA pagina — vedi
            // PageContent::contentFile().
            ->withPivot('occupied_percentage', 'content_file_id')
            ->withTimestamps();
    }

    /**
     * File PDF caricati per questa pagina, dal più recente.
     */
    public function files(): HasMany
    {
        return $this->hasMany(PageFile::class)->latest();
    }

    /**
     * Ultimo PDF caricato direttamente sulla pagina (percorso "legacy",
     * pre-Punto 4): quello mostrato come anteprima principale sulla card
     * PRIMA che esistesse il caricamento via contenuto.
     */
    public function latestFile(): ?PageFile
    {
        return $this->files()->first();
    }

    /**
     * File da mostrare come anteprima principale sulla card/riga (Punto 4
     * del prompt, 2026-09-02): il file del primo contenuto assegnato a
     * questa pagina che ne ha uno — tramite il file specifico registrato
     * sul pivot (`content_file_id`, per una pagina derivata da un PDF
     * multi-pagina caricato su un contenuto) o il file più recente del
     * contenuto se il pivot non lo specifica (contenuto a file singolo:
     * immagine, PDF di una pagina, o nessun file). Ricade sul vecchio
     * PageFile diretto solo se nessun contenuto assegnato ha un file —
     * cioè per le pagine caricate PRIMA di questa modifica e non ancora
     * migrate (Punto 8 del prompt, non eseguito in questa sessione: i
     * dati esistenti restano leggibili finché non vengono migrati
     * esplicitamente). Richiede 'contents.files' e 'files' eager-loaded
     * (vedi Grid::render()) per non generare N+1.
     */
    public function previewFile(): PageFile|ContentFile|null
    {
        foreach ($this->contents as $content) {
            $file = $content->pivot?->content_file_id !== null
                ? $content->files->firstWhere('id', $content->pivot->content_file_id)
                // sortByDesc(), non solo first(): la relazione Content::files()
                // ordina "latest()" a livello di query, ma una collection
                // già in memoria (es. eager-loaded diversamente, o un
                // fixture di test con setRelation()) non rispetta quell'ordine
                // da sola — stesso principio già seguito da
                // App\Support\AdMaterialStatusResolver prima di questa
                // modifica, qui esteso anche al ramo PageFile sotto.
                : $content->files->sortByDesc('created_at')->first();

            if ($file !== null) {
                return $file;
            }
        }

        return $this->files->sortByDesc('created_at')->first();
    }

    public function reorderLogs(): HasMany
    {
        return $this->hasMany(PageReorderLog::class);
    }
}
