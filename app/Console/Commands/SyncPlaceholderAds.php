<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Support\AdPlaceholder;
use Illuminate\Console\Command;

/**
 * `php artisan timone:placeholder-sync [--dry-run]` (revisione 2, Fase 3.1,
 * 2026-10-28): riallinea contents.is_placeholder alla regola nuova
 * (AdPlaceholder: segnaposto = pubblicità senza cliente e senza file).
 *
 * Serve solo per i dati scritti con la regola della Fase 5, in cui anche un
 * titolo proprio rendeva "vera" una pubblicità: quelle senza cliente né
 * file tornano segnaposto. Non è una migration perché cambia il significato
 * di dati che gli utenti vedono: va lanciato dopo aver visto il dry-run
 * (regola 4 del batch), non in automatico all'avvio dello stack.
 */
class SyncPlaceholderAds extends Command
{
    protected $signature = 'timone:placeholder-sync {--issue= : Solo questo numero (id)} {--dry-run : Mostra cosa cambierebbe senza salvare}';

    protected $description = 'Riallinea le pubblicità segnaposto alla regola "senza cliente e senza file" (Fase 3.1)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $toPlaceholder = 0;
        $toReal = 0;

        Content::query()
            ->when($this->option('issue'), fn ($query, $issueId) => $query->where('issue_id', $issueId))
            ->with(['advertisement', 'issue'])
            ->withExists('files')
            ->chunkById(200, function ($contents) use ($dryRun, &$toPlaceholder, &$toReal) {
                foreach ($contents as $content) {
                    $placeholder = AdPlaceholder::applies($content->type, $content->advertisement?->client, (bool) $content->files_exists);

                    if ($placeholder === $content->isPlaceholder()) {
                        continue;
                    }

                    $placeholder ? $toPlaceholder++ : $toReal++;
                    $this->line(sprintf(
                        '  [%d] numero «%s»: «%s» → %s',
                        $content->id,
                        $content->issue?->title,
                        $content->title,
                        $placeholder ? 'diventa segnaposto (nessun cliente, nessun file)' : 'diventa pubblicità vera',
                    ));

                    if (! $dryRun) {
                        $content->update(['is_placeholder' => $placeholder]);
                    }
                }
            });

        $this->newLine();
        $this->info(($dryRun ? 'Dry-run: ' : '')."{$toPlaceholder} tornano segnaposto, {$toReal} diventano pubblicità vere.".($dryRun ? ' Nessuna modifica scritta.' : ''));

        return self::SUCCESS;
    }
}
