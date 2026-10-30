<?php

namespace App\Models;

use App\Enums\CoverSlot;
use App\Enums\FileFit;
use App\Enums\PageContentType;
use App\Enums\PageFillStatus;
use App\Enums\PageStatus;
use App\Enums\ThumbnailStatus;
use App\Jobs\CompleteContentFileThumbnail;
use App\Jobs\WarmCroppedThumbnail;
use App\Support\ContentFileAspectRatio;
use App\Support\ContentFileExtras;
use App\Support\CroppedThumbnails;
use App\Support\PageCapacity;
use App\Support\PageGrid\CropPlan;
use App\Support\PageGrid\GridRect;
use App\Support\PageGrid\PageGridPlanner;
use App\Support\PageGrid\PageGridPositions;
use App\Support\PageGrid\ThumbnailCrop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

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
     * Contenuti assegnati a questa pagina, con lo spazio occupato da
     * ciascuno in dodicesimi (pivot occupied_twelfths, Fase 3).
     */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'page_content')
            ->using(PageContent::class)
            // content_file_id (Punto 4, 2026-09-02): quale file di quel
            // contenuto è rilevante per QUESTA pagina — vedi
            // PageContent::contentFile().
            // grid_* (revisione 2, Fase 2.3): rettangolo del contenuto sulla
            // griglia 6×6 della pagina (App\Support\PageGrid).
            ->withPivot('occupied_twelfths', 'content_file_id', 'position', 'grid_x', 'grid_y', 'grid_w', 'grid_h')
            ->withTimestamps()
            // Fase 7b: ordine di assegnazione sulla pagina (page_content.position;
            // le righe senza posizione in coda). Dalla revisione 2, Fase 2.3, la
            // disposizione sta nelle colonne grid_*: l'ordine decide solo chi
            // viene sistemato prima quando un contenuto non ha ancora un
            // rettangolo (App\Support\PageGrid\PageGridPlanner).
            ->orderByRaw('page_content.position is null')
            ->orderBy('page_content.position')
            ->orderBy('page_content.id');
    }

    /**
     * Dodicesimi occupati sulla pagina (Fase 3). Usa la relazione già
     * caricata se c'è (Grid::render() la eager-loada: nessuna query per
     * card), altrimenti la interroga. $exceptContentId esclude il
     * contenuto in modifica, per chiedersi "ci sta con il nuovo valore?".
     */
    public function occupiedTwelfths(?int $exceptContentId = null): int
    {
        $contents = $this->relationLoaded('contents')
            ? $this->contents
            : $this->contents()->get();

        return $contents
            ->reject(fn (Content $content) => $content->id === $exceptContentId)
            ->sum(fn (Content $content) => (int) $content->pivot->occupied_twelfths);
    }

    public function fillStatus(): PageFillStatus
    {
        return PageCapacity::status($this->occupiedTwelfths());
    }

    /**
     * File PDF caricati per questa pagina, dal più recente.
     */
    /** Commenti (2026-10-29): seguono la pagina anche quando viene spostata. */
    public function comments(): HasMany
    {
        return $this->hasMany(PageComment::class);
    }

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
            $file = $this->previewFileFor($content);

            if ($file !== null) {
                return $file;
            }
        }

        return $this->files->sortByDesc('created_at')->first();
    }

    /**
     * File di QUEL contenuto da mostrare su questa pagina (estratto da
     * previewFile() nella Fase 7a, dove ogni blocco della card mostra la
     * propria anteprima): quello indicato sul pivot (pagina di un PDF
     * multipagina) o il più recente del contenuto.
     */
    public function previewFileFor(Content $content): ?ContentFile
    {
        return $content->pivot?->content_file_id !== null
            ? $content->files->firstWhere('id', $content->pivot->content_file_id)
            // sortByDesc(), non solo first(): una collection già in memoria
            // (eager-load diverso, fixture di test) non garantisce l'ordine
            // "latest()" della relazione.
            : $content->files->sortByDesc('created_at')->first();
    }

    /** Disposizione già calcolata in questo render (vedi layout()). */
    private ?array $gridLayoutCache = null;

    /**
     * Disposizione dei contenuti nella card sulla griglia 6×6 (revisione 2,
     * Fase 2.3, 2026-10-27; prima calcolata con PageLayoutPlanner, Fase
     * 7a): un blocco per contenuto con il suo rettangolo (in celle e in %
     * della card), i pezzi se è a L, l'anteprima del PROPRIO file, quali
     * frecce sono possibili e se si può ruotare; più lo spazio libero.
     * Usa le relazioni già caricate da Grid::render() ed è memorizzata
     * sull'istanza: card, menu dei comandi ed export la chiedono più volte
     * nello stesso render.
     *
     * @return array{blocks: list<array{content: Content, kind: string, cells: GridRect, rect: array, pieces: list<array>, file: ContentFile|null, imageUrl: string|null, moves: array<string, bool>, canRotate: bool}>, free: list<array>, overfull: bool}
     */
    public function layout(): array
    {
        if ($this->gridLayoutCache !== null) {
            return $this->gridLayoutCache;
        }

        $plan = PageGridPositions::resolve($this);
        $rects = PageGridPlanner::rectsOf($plan);
        $byId = $this->contents->keyBy('id');
        $blocks = [];

        foreach ($plan['blocks'] as $id => $block) {
            $content = $byId[$id];
            $file = $this->previewFileFor($content);
            $movable = $block['kind'] === 'rect';
            // 2026-10-30: ritaglio per pezzo (ThumbnailCrop): a pagina intera
            // ogni pezzo, L comprese, mostra la sua porzione della pagina
            // rifilata; nel formato il file intero.
            $pieces = $block['kind'] === 'fluid' ? $block['pieces'] : array_filter([$block['rect']]);
            $crop = $file !== null && $pieces !== [] ? self::cropFor($file, $content, array_values($pieces), $this->issue->pageSizeMm()) : null;

            $blocks[] = [
                'content' => $content,
                'kind' => $block['kind'],
                'cells' => $block['rect'],
                'rect' => $block['rect']->toPercent(),
                'pieces' => array_map(fn ($piece) => $piece->toPercent(), $block['pieces']),
                'file' => $file,
                'imageUrl' => $file?->thumbnail_status === ThumbnailStatus::Ready ? route('content-files.thumbnail', $file) : null,
                // Ritagliato: la miniatura grande, nitida anche ingrandita.
                'largeImageUrl' => $file?->thumbnail_status === ThumbnailStatus::Ready && $file->large_thumbnail_path !== null && $crop?->crops() ? route('content-files.thumbnail', [$file, 'size' => 'large']) : null,
                'crop' => $crop,
                'fit' => $crop?->fit ?? FileFit::FullPage,
                'imageStyle' => $crop !== null && $block['rect'] !== null ? $crop->imageStyle($block['rect']) : '',
                'pieceStyles' => $crop !== null ? array_map(fn ($piece) => $crop->imageStyle($piece), $block['pieces']) : [],
                'moves' => collect(PageGridPlanner::DIRECTIONS)
                    ->mapWithKeys(fn (string $direction) => [$direction => $movable && PageGridPlanner::moveTarget($rects, $id, $direction) !== null])
                    ->all(),
                'canRotate' => $movable && PageGridPlanner::rotateTarget($rects, $id) !== null,
            ];
        }

        return $this->gridLayoutCache = [
            'blocks' => $blocks,
            'free' => array_map(fn ($rect) => $rect->toPercent(), $plan['free']),
            'overfull' => $plan['overfull'],
        ];
    }

    /**
     * Piano di ritaglio di un file in questi pezzi. Un file mai misurato
     * prende subito le proporzioni dalla sua miniatura (lettura di un PNG,
     * istantanea) e accoda il resto (TrimBox, miniatura grande) una volta
     * sola: la griglia non aspetta la rasterizzazione.
     *
     * @param  list<GridRect>  $pieces
     */
    private static function cropFor(ContentFile $file, Content $content, array $pieces, array $pageMm): CropPlan
    {
        if ($file->thumbnail_status === ThumbnailStatus::Ready && $file->aspect_ratio === null && ($ratio = ContentFileAspectRatio::measure($file)) !== null) {
            $file->forceFill(['aspect_ratio' => $ratio])->saveQuietly();
        }

        if (ContentFileExtras::incomplete($file) && Cache::add('content-file-complete:'.$file->id, true, now()->addHour())) {
            CompleteContentFileThumbnail::dispatch($file);
        }

        $plan = ThumbnailCrop::plan($pageMm, $pieces, $file->aspect_ratio, $file->trim_box, $content->file_fit);

        // Ritagli per l'export del timone, preparati in coda (una volta per finestra).
        if ($plan->crops() && $file->thumbnail_status === ThumbnailStatus::Ready) {
            foreach ($plan->pieces as $piece) {
                if (Cache::add('crop-warm:'.CroppedThumbnails::relativePath($file, $piece['window']), true, now()->addDay()) && ! CroppedThumbnails::exists($file, $piece['window'])) {
                    WarmCroppedThumbnail::dispatch($file, $piece['window']);
                }
            }
        }

        return $plan;
    }

    /** Blocco della disposizione di un contenuto (menu dei comandi). */
    public function layoutBlockFor(Content $content): ?array
    {
        return collect($this->layout()['blocks'])->first(fn (array $block) => $block['content']->id === $content->id);
    }

    /**
     * URL dell'anteprima pronta di previewFile() (card e riga), o null se
     * non c'è o non è ancora generata — calcolato qui invece che in due
     * copie nei Blade (Fase 4, 2026-10-27).
     */
    public function previewImageUrl(): ?string
    {
        $file = $this->previewFile();

        if ($file === null || $file->thumbnail_status !== ThumbnailStatus::Ready) {
            return null;
        }

        return $file instanceof PageFile ? route('page-files.thumbnail', $file) : route('content-files.thumbnail', $file);
    }

    public function reorderLogs(): HasMany
    {
        return $this->hasMany(PageReorderLog::class);
    }
}
