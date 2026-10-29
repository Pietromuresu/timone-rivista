<?php

namespace App\Console\Commands;

use App\Support\Manual\ManualRepository;
use App\Support\Manual\ManualVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * `php artisan manuale:verifica` (2026-10-29): nessun termine del manuale
 * a pagina unica (grassetti, emoji-comando, scorciatoie, valori con unità)
 * deve mancare nelle pagine nuove. Elenca quelli mancanti ed esce con
 * errore; le eccezioni motivate sono in ManualVerifier::EXCEPTIONS. Lo
 * stesso controllo gira nella suite (tests/Feature/ManualVerifyTest.php).
 */
class VerifyManual extends Command
{
    protected $signature = 'manuale:verifica';

    protected $description = 'Controlla che nel nuovo manuale non manchi nessun termine del manuale archiviato';

    public function handle(): int
    {
        $archive = File::get((string) config('timone.manual_archive'));
        $pages = ManualRepository::fromConfig()->all();
        $text = $pages->map(fn ($page) => $page->titolo."\n".$page->body)->implode("\n\n");

        $terms = ManualVerifier::requiredTerms($archive);
        $missing = ManualVerifier::missing($archive, $text);

        $this->info(sprintf(
            'Termini controllati: %d grassetti, %d emoji, %d scorciatoie, %d valori; eccezioni motivate: %d.',
            count($terms['bold']), count($terms['emoji']), count($terms['shortcuts']), count($terms['numbers']), count(ManualVerifier::EXCEPTIONS),
        ));

        if ($missing === []) {
            $this->info('Nessun termine mancante nelle '.$pages->count().' pagine.');

            return self::SUCCESS;
        }

        $this->table(['Tipo', 'Termine del manuale archiviato', 'Atteso nel nuovo'], array_map(fn ($m) => [$m['kind'], $m['term'], $m['expected']], $missing));
        $this->error(count($missing).' termini mancanti.');

        return self::FAILURE;
    }
}
