<?php

namespace App\Console\Commands;

use App\Enums\ContentType;
use App\Models\Content;
use Illuminate\Console\Command;

/**
 * Bug 5 (2026-10-12): elenca i contenuti senza occupazione di pagina, da
 * valorizzare prima che la migration NOT NULL possa essere applicata. Solo
 * lettura, nessun backfill automatico: per ogni riga mostra gli indizi
 * utili a decidere (formato pubblicitario, pagine già assegnate e
 * percentuali, pagine del PDF allegato) — la decisione resta della
 * redazione, non del codice.
 */
class ListMissingOccupancy extends Command
{
    protected $signature = 'timone:missing-occupancy';

    protected $description = 'Conta ed elenca i contenuti senza occupazione di pagina (prerequisito della migration NOT NULL)';

    public function handle(): int
    {
        $contents = Content::query()
            ->whereNull('page_occupancy')
            ->with(['issue.magazine', 'advertisement', 'pages', 'files'])
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
                $c->pages->isEmpty()
                    ? '—'
                    : $c->pages->sortBy('position')->map(fn ($p) => $p->position.' ('.(float) $p->pivot->occupied_percentage.')')->implode(', '),
                $c->files->where('mime_type', 'application/pdf')->max('pdf_page_number') ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
