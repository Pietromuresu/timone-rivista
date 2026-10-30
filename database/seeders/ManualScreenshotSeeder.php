<?php

namespace Database\Seeders;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\CoverSlot;
use App\Enums\EditorialStatus;
use App\Enums\IssueStatus;
use App\Enums\PageStatus;
use App\Enums\UserRole;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\Page;
use App\Models\PageComment;
use App\Models\User;
use App\Support\ContentCreator;
use App\Support\ContentPlacer;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Dati demo per gli screenshot del manuale (2026-10-29, Fase 4): una
 * rivista e un numero INVENTATI, con contenuti e clienti fittizi. Nessun
 * dato reale: nomi di persone, aziende e marchi sono di fantasia (non va
 * usato DatabaseSeeder, che ha nomi di aziende vere).
 *
 * Usato solo da `npm run manuale:screenshot` (scripts/manuale-screenshot.mjs)
 * su un database e uno storage temporanei; mai in produzione.
 *
 * I contenuti passano dai servizi veri (ContentCreator e ContentPlacer,
 * che crea anche i gruppi collegati), così la griglia è quella che vede un utente, con
 * pagine piene, miste, una forma a L, una doppia pagina, un segnaposto.
 * Le «pubblicità» sono immagini disegnate qui con GD (riquadri colorati),
 * per avere anteprime nelle card senza file esterni.
 */
class ManualScreenshotSeeder extends Seeder
{
    public const EMAIL = 'laura.esempio@esempio.test';

    public const PASSWORD = 'manuale-demo';

    private User $user;

    private Issue $issue;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('ManualScreenshotSeeder è solo per gli screenshot del manuale.');
        }

        User::create(['name' => 'Admin Esempio', 'email' => 'admin@esempio.test', 'password' => Hash::make(self::PASSWORD), 'role' => UserRole::Admin, 'email_verified_at' => now()]);
        $this->user = User::create(['name' => 'Laura Esempio', 'email' => self::EMAIL, 'password' => Hash::make(self::PASSWORD), 'role' => UserRole::Redattore, 'email_verified_at' => now()]);
        $colleague = User::create(['name' => 'Marco Prova', 'email' => 'marco.prova@esempio.test', 'password' => Hash::make(self::PASSWORD), 'role' => UserRole::Redattore, 'email_verified_at' => now()]);

        $magazine = Magazine::create([
            'name' => 'Orto e Cortile',
            'slug' => 'orto-e-cortile',
            'periodicity' => 'mensile',
            'color' => '#0EA5E9',
            'notes' => 'Rivista di esempio per il manuale.',
            'redattore_id' => $this->user->id,
            'signature_pages' => 16,
            'formato_base_mm' => 210,
            'formato_altezza_mm' => 280,
            'grammatura_interno' => 80,
            'grammatura_copertina' => 200,
            'pagine_copertina' => 4,
            'maggiorazione_percentuale' => 2,
        ]);
        $this->user->magazines()->attach($magazine->id);
        $colleague->magazines()->attach($magazine->id);

        $this->issue = Issue::create([
            'magazine_id' => $magazine->id,
            'title' => 'Dicembre (numero di prova)',
            'issue_date' => now()->addMonth()->startOfMonth(),
            'print_deadline_at' => now()->addDays(10)->setTime(12, 0),
            'status' => IssueStatus::InLavorazione,
            'total_pages' => 24,
            'formato_base_mm' => 210,
            'formato_altezza_mm' => 280,
            'grammatura_interno' => 80,
            'grammatura_copertina' => 200,
            'pagine_copertina' => 4,
            'maggiorazione_percentuale' => 2,
            'peso_bancale_kg' => 25,
            'n_copie' => 5000,
            'n_bancali' => 2,
        ]);

        Issue::create(['magazine_id' => $magazine->id, 'title' => 'Novembre', 'issue_date' => now()->startOfMonth(), 'status' => IssueStatus::Chiuso, 'total_pages' => 24]);

        // Copertine.
        $this->ad('Trattori Bruno', AdFormat::CopertinaSecondaTerzaQuarta, '#B45309', $this->cover(CoverSlot::II));
        $this->ad('Fiori Ottavia', AdFormat::CopertinaSecondaTerzaQuarta, '#BE185D', $this->cover(CoverSlot::IV));
        $this->article('Copertina: l\'orto d\'inverno', 12, $this->cover(CoverSlot::I), EditorialStatus::Pronto);

        // Pagine interne. Dalla 7 alla 14 (due righe vicine nella griglia)
        // tutti i formati: forma a L, piedino, mezza orizzontale, doppia
        // pagina, intera, mezza verticale, 1/3 verticale.
        $this->article('Editoriale', 12, $this->page(1), EditorialStatus::Pronto);
        $this->article('Sommario', 12, $this->page(2), EditorialStatus::Pronto);
        $this->article('Speciale potature', 30, $this->page(3), EditorialStatus::InRevisione);
        $this->article('Il calendario delle semine', 12, $this->page(6), EditorialStatus::InScrittura);
        $this->article('Attrezzi: come sceglierli', 9, $this->page(7), EditorialStatus::InRevisione);
        $this->ad('Ferramenta Lumaca', AdFormat::UnQuartoPagina, '#7C3AED', $this->page(7));
        $this->article('Ricette dall\'orto', 8, $this->page(8), EditorialStatus::Pronto);
        $this->ad('Conserve Valle Verde', AdFormat::Piedino, '#CA8A04', $this->page(8));
        $this->article('L\'orto sul balcone', 6, $this->page(9), EditorialStatus::Pronto);
        $this->ad('Vivai Fiordaliso', AdFormat::MezzaPaginaOrizzontale, '#15803D', $this->page(9));
        $this->ad('Serre Aurora', AdFormat::DoppiaPagina, '#0369A1', $this->page(10));
        $this->ad('Sementi Girasole', AdFormat::PaginaIntera, '#EA580C', $this->page(12));
        $this->article('Notizie in breve', 6, $this->page(13), EditorialStatus::Pronto);
        $this->ad('Irrigazione Goccia', AdFormat::MezzaPaginaVerticale, '#0891B2', $this->page(13));
        $this->article('Il pollaio in inverno', 8, $this->page(14), EditorialStatus::DaScrivere);
        $this->ad('Mangimi Cortese', AdFormat::UnTerzoPaginaVerticale, '#65A30D', $this->page(14));
        $this->article('Lettere dei lettori', 12, $this->page(16), EditorialStatus::InScrittura);

        // Segnaposto: pubblicità prevista, senza cliente né file.
        $this->ad(null, AdFormat::MezzaPaginaOrizzontale, null, $this->page(18));

        // Da assegnare, nel pannello Contenuti.
        $this->article('Intervista al giardiniere', 24, null, EditorialStatus::InScrittura);
        $this->ad('Vasi Terracotta Rossi', AdFormat::UnQuartoPagina, '#9A3412', null, AdConfirmationStatus::InTrattativa);
        $this->ad('Concimi Primavera', AdFormat::PaginaIntera, '#4D7C0F', null);

        // Stati diversi, un blocco, un commento.
        $this->page(1)->update(['status' => PageStatus::OkStampa, 'locked_at' => now(), 'locked_by' => $this->user->id]);
        $this->page(2)->update(['status' => PageStatus::OkStampa]);
        $this->page(3)->update(['status' => PageStatus::Revisionata]);
        $this->page(4)->update(['status' => PageStatus::Revisionata]);
        $this->page(6)->update(['status' => PageStatus::InBozza]);
        $this->page(8)->update(['status' => PageStatus::InBozza]);
        $this->page(12)->update(['status' => PageStatus::Revisionata]);
        // Il gruppo collegato nasce da solo: la doppia pagina e lo speciale
        // su più pagine vengono collegati da ContentPlacer.

        PageComment::create([
            'issue_id' => $this->issue->id,
            'page_id' => $this->page(8)->id,
            'user_id' => $colleague->id,
            'body' => 'Il piedino va spostato in alto: lo chiede il cliente.',
            'page_label' => 'pagina 8',
        ]);
    }

    private function page(int $position): Page
    {
        return $this->issue->pages()->where('position', $position)->firstOrFail();
    }

    private function cover(CoverSlot $slot): Page
    {
        return $this->issue->covers()->where('cover_slot', $slot->value)->firstOrFail();
    }

    private function article(string $title, int $twelfths, ?Page $page, EditorialStatus $status): Content
    {
        $content = ContentCreator::create($this->issue, [
            'type' => ContentType::Articolo->value,
            'title' => $title,
            'author' => 'Redazione Esempio',
            'editorial_status' => $status->value,
            'whole_pages' => intdiv($twelfths, 12),
            'fraction_twelfths' => $twelfths % 12,
        ], null, $this->user->id);

        return $this->place($content, $page);
    }

    private function ad(?string $client, AdFormat $format, ?string $color, ?Page $page, AdConfirmationStatus $status = AdConfirmationStatus::Confermata): Content
    {
        $file = $client !== null && $color !== null ? $this->adImage($client, $format, $color) : null;

        $content = ContentCreator::create($this->issue, [
            'type' => ContentType::Pubblicita->value,
            'title' => $client !== null ? 'Pubblicità '.$client : null,
            'client' => $client ?? '',
            'format' => $format->value,
            'confirmation_status' => $status->value,
        ], $file, $this->user->id);

        return $this->place($content, $page);
    }

    private function place(Content $content, ?Page $page): Content
    {
        if ($page !== null) {
            $result = ContentPlacer::place($content, $page);

            if (is_string($result)) {
                throw new RuntimeException($result);
            }
        }

        return $content;
    }

    /**
     * Un'«inserzione» di fantasia nelle proporzioni del formato: fondo
     * colorato, due cerchi chiari e il nome del cliente (font interno di
     * GD, ingrandito). Nessun file esterno, nessun marchio vero.
     */
    private function adImage(string $client, AdFormat $format, string $color): UploadedFile
    {
        [$w, $h] = match ($format) {
            AdFormat::DoppiaPagina => [840, 560],
            AdFormat::MezzaPaginaOrizzontale => [420, 280],
            AdFormat::MezzaPaginaVerticale => [210, 560],
            AdFormat::UnTerzoPaginaVerticale => [140, 560],
            AdFormat::Piedino => [420, 187],
            AdFormat::UnQuartoPagina => [210, 280],
            default => [420, 560],
        };

        $image = imagecreatetruecolor($w, $h);
        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
        $light = imagecolorallocatealpha($image, 255, 255, 255, 90);
        imagefilledellipse($image, (int) ($w * 0.8), (int) ($h * 0.25), (int) ($w * 0.7), (int) ($w * 0.7), $light);
        imagefilledellipse($image, (int) ($w * 0.15), (int) ($h * 0.85), (int) ($w * 0.5), (int) ($w * 0.5), $light);

        // Testo: font 5 di GD su una tela piccola, poi ingrandito.
        $words = explode(' ', $client);
        $lineHeight = 16;
        $small = imagecreatetruecolor(max(array_map('strlen', $words)) * 9 + 8, count($words) * $lineHeight + 6);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        foreach ($words as $i => $word) {
            imagestring($small, 5, 4, 3 + $i * $lineHeight, $word, imagecolorallocate($small, 255, 255, 255));
        }
        $scale = max(1, min(4, intdiv($w - 20, imagesx($small))));
        imagecopyresized($image, $small, 10, 10, 0, 0, imagesx($small) * $scale, imagesy($small) * $scale, imagesx($small), imagesy($small));

        $path = tempnam(sys_get_temp_dir(), 'adv').'.png';
        imagepng($image, $path);

        return new UploadedFile($path, str($client)->slug().'.png', 'image/png', null, true);
    }
}
