<?php

namespace Database\Seeders;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\IssueStatus;
use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Enums\UserRole;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Dataset minimo per navigare il flusso rivista -> numero durante lo
     * sviluppo. Il seeder demo completo (pubblicità, PDF, più utenti per
     * ruolo) arriverà con le fasi successive.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@timone.test',
            'role' => UserRole::Admin,
        ]);

        $redattore = User::factory()->create([
            'name' => 'Redattore Test',
            'email' => 'redattore@timone.test',
            'role' => UserRole::Redattore,
        ]);

        $motori = Magazine::factory()->create([
            'name' => 'Motori Elettrici',
            'color' => '#3B82F6',
            'ad_threshold_percentage' => 30,
        ]);

        $casa = Magazine::factory()->create([
            'name' => 'Casa & Giardino',
            'color' => '#22C55E',
            'ad_threshold_percentage' => 25,
        ]);

        Magazine::factory()->create([
            'name' => 'Sport Weekly',
            'color' => '#F97316',
        ]);

        $redattore->magazines()->attach([$motori->id, $casa->id]);

        $novembre = Issue::factory()->create([
            'magazine_id' => $motori->id,
            'title' => 'Novembre 2026',
            'issue_date' => '2026-11-01',
            'status' => IssueStatus::InLavorazione,
            'total_pages' => 64,
        ]);

        Issue::factory()->create([
            'magazine_id' => $motori->id,
            'title' => 'Ottobre 2026',
            'issue_date' => '2026-10-01',
            'status' => IssueStatus::Chiuso,
            'total_pages' => 64,
        ]);

        Issue::factory()->create([
            'magazine_id' => $casa->id,
            'title' => 'Novembre 2026',
            'issue_date' => '2026-11-15',
            'status' => IssueStatus::Bozza,
            'total_pages' => 48,
        ]);

        $this->seedNovemberContents($novembre);
    }

    /**
     * Popola il numero "Novembre 2026" di Motori Elettrici con qualche
     * rubrica, articolo e pubblicità già assegnati, per avere subito una
     * griglia timone realistica da mostrare (pagine piene, miste e vuote).
     */
    private function seedNovemberContents(Issue $issue): void
    {
        $provaSuStrada = Section::factory()->create([
            'magazine_id' => $issue->magazine_id,
            'name' => 'Prova su strada',
        ]);

        $attualita = Section::factory()->create([
            'magazine_id' => $issue->magazine_id,
            'name' => 'Attualità',
        ]);

        // Articolo assegnato per intero a pagina 5.
        $reviewContent = Content::factory()->create([
            'issue_id' => $issue->id,
            'section_id' => $provaSuStrada->id,
            'type' => ContentType::Articolo,
            'title' => 'Prova su strada: la nuova berlina elettrica',
        ]);
        Article::factory()->create([
            'content_id' => $reviewContent->id,
            'author' => 'Giulia Bianchi',
            'editorial_status' => EditorialStatus::InRevisione,
            'expected_length' => 6,
        ]);
        $this->assignContentToPage($issue, $reviewContent, 5, 100);

        // Pubblicità a pagina intera assegnata a pagina 10.
        $fullPageAd = Content::factory()->create([
            'issue_id' => $issue->id,
            'type' => ContentType::Pubblicita,
            'title' => 'Pubblicità Fuchs',
        ]);
        Advertisement::factory()->create([
            'content_id' => $fullPageAd->id,
            'client' => 'Fuchs Lubrificanti',
            'format' => AdFormat::PaginaIntera,
            'confirmation_status' => AdConfirmationStatus::Confermata,
        ]);
        $this->assignContentToPage($issue, $fullPageAd, 10, 100);

        // Pagina mista a pagina 12: mezza pubblicità + mezzo articolo.
        $halfPageAd = Content::factory()->create([
            'issue_id' => $issue->id,
            'type' => ContentType::Pubblicita,
            'title' => 'Pubblicità Neoparts',
        ]);
        Advertisement::factory()->create([
            'content_id' => $halfPageAd->id,
            'client' => 'Neoparts',
            'format' => AdFormat::MezzaPaginaVerticale,
            'confirmation_status' => AdConfirmationStatus::Confermata,
        ]);

        $shortArticle = Content::factory()->create([
            'issue_id' => $issue->id,
            'section_id' => $attualita->id,
            'type' => ContentType::Articolo,
            'title' => 'In breve: incentivi statali per l\'elettrico',
        ]);
        Article::factory()->create([
            'content_id' => $shortArticle->id,
            'author' => 'Marco Verdi',
            'editorial_status' => EditorialStatus::Pronto,
            'expected_length' => 2,
        ]);

        $mixedPage = $issue->pages()->where('position', 12)->first();
        $mixedPage->update([
            'content_type' => PageContentType::Mista,
            'status' => PageStatus::Assegnata,
        ]);
        $mixedPage->contents()->attach($halfPageAd->id, ['occupied_percentage' => 50]);
        $mixedPage->contents()->attach($shortArticle->id, ['occupied_percentage' => 50]);

        // Contenuti non ancora assegnati a nessuna pagina.
        $unassignedArticle = Content::factory()->create([
            'issue_id' => $issue->id,
            'section_id' => $attualita->id,
            'type' => ContentType::Articolo,
            'title' => 'Motori elettrici: il punto sulla ricarica rapida',
        ]);
        Article::factory()->create([
            'content_id' => $unassignedArticle->id,
            'author' => 'Sara Neri',
            'editorial_status' => EditorialStatus::InScrittura,
            'expected_length' => 4,
        ]);

        $unassignedAd = Content::factory()->create([
            'issue_id' => $issue->id,
            'type' => ContentType::Pubblicita,
            'title' => 'Pubblicità Bullettina Tools',
        ]);
        Advertisement::factory()->create([
            'content_id' => $unassignedAd->id,
            'client' => 'Bullettina Tools',
            'format' => AdFormat::UnQuartoPagina,
            'confirmation_status' => AdConfirmationStatus::InTrattativa,
        ]);
    }

    private function assignContentToPage(Issue $issue, Content $content, int $position, float $percentage): void
    {
        $page = $issue->pages()->where('position', $position)->first();

        $page->update([
            'content_type' => $content->type === ContentType::Articolo
                ? PageContentType::Editoriale
                : PageContentType::Pubblicita,
            'status' => PageStatus::Assegnata,
        ]);

        $page->contents()->attach($content->id, ['occupied_percentage' => $percentage]);
    }
}
