<?php

namespace Database\Seeders;

use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\ThumbnailStatus;
use App\Enums\UserRole;
use App\Models\Advertisement;
use App\Models\Content;
use App\Models\ContentFile;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\Page;
use App\Models\User;
use App\Support\ContentFileExtras;
use App\Support\ContentPlacer;
use App\Support\PageGrid\PageGridPositions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\CropFixtures;

/**
 * Dati per la verifica visiva del ritaglio delle miniature (2026-10-30,
 * Fase B1), usati da `node scripts/verifica-crop.mjs` su un database e uno
 * storage temporanei. Un caso per pagina, con i PDF di prova a sesti
 * colorati (Tests\Support\CropFixtures): nello screenshot si vede subito
 * quale sesto compare. Le miniature le fa `pdftoppm` (poppler) invece di
 * Imagick, che sul computer di sviluppo può mancare: entrambi rendono la
 * MediaBox, quindi il ritaglio è lo stesso del server.
 *
 * Solo sviluppo: rifiuta di girare in produzione.
 */
class CropVerificationSeeder extends Seeder
{
    public const EMAIL = 'verifica@esempio.test';

    public const PASSWORD = 'verifica-crop';

    /** @var array<string, string> nome → PDF di prova sul disco temporaneo */
    private array $fixtures = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('CropVerificationSeeder è solo per lo sviluppo.');
        }

        $this->fixtures = CropFixtures::writeAll(storage_path('app/crop-fixtures'));
        $user = User::factory()->create(['name' => 'Verifica', 'email' => self::EMAIL, 'password' => bcrypt(self::PASSWORD), 'role' => UserRole::Admin]);
        $magazine = Magazine::factory()->create(['name' => 'Verifica ritaglio', 'slug' => 'verifica-ritaglio']);
        $user->magazines()->attach($magazine);

        $a4 = Issue::factory()->create(['magazine_id' => $magazine->id, 'title' => 'A4', 'total_pages' => 16, 'formato_base_mm' => 210, 'formato_altezza_mm' => 297]);
        $short = Issue::factory()->create(['magazine_id' => $magazine->id, 'title' => '210x270', 'total_pages' => 2, 'formato_base_mm' => 210, 'formato_altezza_mm' => 270]);

        $this->ad($a4, 1, AdFormat::MezzaPaginaVerticale, 'a4-sesti');                                  // metà sinistra: 1, 3, 5
        $this->ad($a4, 2, AdFormat::MezzaPaginaVerticale, 'a4-sesti', ['right']);                        // metà destra: 2, 4, 6
        $this->ad($a4, 3, AdFormat::MezzaPaginaOrizzontale, 'a4-sesti');                                // sopra: 1, 2 e metà di 3, 4
        $this->ad($a4, 4, AdFormat::MezzaPaginaOrizzontale, 'a4-sesti', ['down']);                       // sotto: metà di 3, 4 e 5, 6
        $this->ad($a4, 5, AdFormat::UnTerzoPaginaVerticale, 'a4-sesti');                                 // striscia sinistra di 1, 3, 5
        $this->ad($a4, 6, AdFormat::Piedino, 'a4-sesti');                                                // 5, 6
        foreach ([[], ['right'], ['down'], ['right', 'down']] as $moves) {                                 // le quattro vetrine
            $this->ad($a4, 7, AdFormat::UnQuartoPagina, 'a4-sesti', $moves);
        }
        $this->ad($a4, 8, AdFormat::DueTerziPagina, 'a4-sesti');                                         // 2/3 sinistri
        $this->ad($a4, 9, AdFormat::UnQuartoPagina, null, ['right', 'down']);                            // vetrina senza file…
        $this->article($a4, 9, 9, 'a4-sesti', 'Forma a L 3/4');                                          // …e la L intorno: 1, 2, 3, 4, 5
        $this->article($a4, 10, 30, 'a4-sesti-tre-pagine', 'Tre pagine');                                // pagine 10, 11 intere, 12 a metà
        $this->ad($a4, 13, AdFormat::MezzaPaginaVerticale, 'mezza-verticale-nel-formato');               // intero, verde «MEZZA»
        $this->ad($a4, 14, AdFormat::MezzaPaginaVerticale, 'a4-sesti-abbondanza');                       // come pagina 1, senza l'abbondanza
        $this->ad($short, 1, AdFormat::MezzaPaginaVerticale, 'a4-sesti');                                // A4 su 210×270: metà sinistra, centrata
    }

    /** @param  list<string>  $moves */
    private function ad(Issue $issue, int $position, AdFormat $format, ?string $fixture, array $moves = []): void
    {
        $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Pubblicita, 'title' => $format->label(), 'occupancy_twelfths' => $format->twelfths()]);
        Advertisement::factory()->create(['content_id' => $content->id, 'format' => $format, 'client' => $format->label().($fixture ? '' : ' (senza file)')]);
        $this->place($issue, $content, $position, $fixture, $moves);
    }

    private function article(Issue $issue, int $position, int $twelfths, string $fixture, string $title): void
    {
        $content = Content::factory()->create(['issue_id' => $issue->id, 'type' => ContentType::Articolo, 'title' => $title, 'occupancy_twelfths' => $twelfths]);
        $this->place($issue, $content, $position, $fixture, []);
    }

    /** @param  list<string>  $moves */
    private function place(Issue $issue, Content $content, int $position, ?string $fixture, array $moves): void
    {
        if ($fixture !== null) {
            $pages = $this->pageCount($this->fixtures[$fixture]);

            for ($n = 1; $n <= $pages; $n++) {
                $this->file($content, $fixture, $n);
            }
        }

        $page = $issue->pages()->where('position', $position)->firstOrFail();
        $result = ContentPlacer::place($content->fresh(['advertisement']), $page);

        if (is_string($result)) {
            throw new RuntimeException($result);
        }

        foreach ($moves as $direction) {
            PageGridPositions::move(Page::with(['contents.advertisement', 'issue'])->find($page->id), $content->id, $direction);
        }
    }

    private function file(Content $content, string $fixture, int $pdfPage): void
    {
        $path = "contents/{$content->id}/{$fixture}.pdf";
        Storage::disk('local')->put($path, file_get_contents($this->fixtures[$fixture]));
        $dir = "contents/{$content->id}/thumbnails";
        Storage::disk('local')->makeDirectory($dir);

        $thumbs = [];
        foreach ([400 => '', ContentFileExtras::LARGE_WIDTH => '-large'] as $width => $suffix) {
            $base = "{$dir}/{$fixture}-p{$pdfPage}{$suffix}";
            $process = new Process(['pdftoppm', '-png', '-singlefile', '-f', (string) $pdfPage, '-l', (string) $pdfPage, '-scale-to-x', (string) $width, '-scale-to-y', '-1', Storage::disk('local')->path($path), Storage::disk('local')->path($base)]);
            $process->mustRun();
            $thumbs[] = $base.'.png';
        }

        $file = ContentFile::create([
            'content_id' => $content->id,
            'disk' => 'local',
            'path' => $path,
            'thumbnail_path' => $thumbs[0],
            'large_thumbnail_path' => $thumbs[1],
            'original_name' => $fixture.'.pdf',
            'mime_type' => 'application/pdf',
            'size' => Storage::disk('local')->size($path),
            'thumbnail_status' => ThumbnailStatus::Ready,
            'pdf_page_number' => $pdfPage,
        ]);

        // Proporzioni e TrimBox come le misura il job vero.
        ContentFileExtras::complete($file);
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page[^s]/', (string) file_get_contents($pdf));
    }
}
