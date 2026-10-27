<?php

namespace App\Console\Commands;

use App\Enums\ContentType;
use App\Models\Content;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bug 5 (2026-10-12): elenca i contenuti senza occupazione di pagina, da
 * valorizzare prima che la migration NOT NULL possa essere applicata. Solo
 * lettura, nessun backfill automatico: per ogni riga mostra gli indizi
 * utili a decidere (formato pubblicitario, pagine già assegnate e
 * percentuali, pagine del PDF allegato) — la decisione resta della
 * redazione, non del codice.
 *
 * Dalla Fase 3 (2026-10-27) serve solo su un database fermo a prima della
 * migration NOT NULL del 2026-10-12: dopo la conversione in dodicesimi
 * l'occupazione sta in `contents.occupancy_twelfths` (NOT NULL) e la
 * colonna vecchia, ora nullable, non viene più scritta — il comando lo
 * dice e non elenca nulla. Le percentuali sono lette dal query builder,
 * perché i modelli non caricano più la colonna `occupied_percentage`.
 */
class ListMissingOccupancy extends Command
{
    protected $signature = 'timone:missing-occupancy';

    protected $description = 'Conta ed elenca i contenuti senza occupazione di pagina (prerequisito della migration NOT NULL)';

    public function handle(): int
    {
        if (Schema::hasColumn('contents', 'occupancy_twelfths')) {
            $this->info('Comando non più necessario: l\'occupazione è in dodicesimi (contents.occupancy_twelfths, obbligatoria) dalla migration del 2026-10-27.');

            return self::SUCCESS;
        }

        $contents = Content::query()
            ->whereNull('page_occupancy')
            ->with(['issue.magazine', 'advertisement', 'files'])
            ->orderBy('issue_id')
            ->orderBy('id')
            ->get();

        if ($contents->isEmpty()) {
            $this->info('Nessun contenuto senza occupazione: la migration NOT NULL può essere applicata.');

            return self::SUCCESS;
        }

        $this->warn("{$contents->count()} contenuti senza occupazione di pagina:");

        $this->table(
            ['id', 'rivista / numero', 'tipo', 'titolo', 'formato ADV', 'pagine assegnate (%)', 'pagine PDF'],
            $contents->map(fn (Content $c) => [
                $c->id,
                ($c->issue->magazine->name ?? '?').' #'.$c->issue_id,
                $c->type->value,
                mb_strimwidth($c->title, 0, 40, '…'),
                $c->type === ContentType::Pubblicita ? ($c->advertisement?->format?->label() ?? '—') : '—',
                DB::table('page_content')
                    ->join('pages', 'pages.id', '=', 'page_content.page_id')
                    ->where('page_content.content_id', $c->id)
                    ->orderBy('pages.position')
                    ->get(['pages.position', 'page_content.occupied_percentage'])
                    ->map(fn (object $row) => $row->position.' ('.(float) $row->occupied_percentage.')')
                    ->implode(', ') ?: '—',
                $c->files->where('mime_type', 'application/pdf')->max('pdf_page_number') ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
