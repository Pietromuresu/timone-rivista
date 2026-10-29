<?php

namespace App\Http\Controllers;

use App\Support\Manual\LegacyAnchors;
use App\Support\Manual\ManualPage;
use App\Support\Manual\ManualRepository;
use App\Support\Manual\ManualSearchIndex;
use App\Support\ManualRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Manuale utente consultabile dall'app. Dal 2026-10-29 una pagina per
 * argomento: /manuale è la pagina d'ingresso («Inizia qui» + «Cosa vuoi
 * fare?» + l'indice), /manuale/{slug} una pagina. Le pagine sono lette a
 * ogni richiesta da resources/manuale/ (App\Support\Manual\ManualRepository):
 * nessuna copia in public/, basta che i file siano leggibili dall'utente PHP.
 * Nessun componente Livewire: pagine di sola lettura.
 *
 * Bug 3 (2026-10-12): in produzione la pagina andava in 500 perché
 * `.dockerignore` escludeva i *.md; corretto lì (oggi anche
 * resources/manuale/), e qui un manuale mancante mostra un messaggio
 * invece di un errore.
 */
class DocumentationController extends Controller
{
    public function show(): View
    {
        $repository = ManualRepository::fromConfig();

        if (! $repository->exists()) {
            report(new \RuntimeException('Manuale utente non trovato: '.config('timone.manual_dir')));

            return view('documentation.index', ['available' => false]);
        }

        return view('documentation.index', [
            'available' => true,
            'categories' => $repository->byCategory(),
            'start' => $this->render($repository->find('inizia-qui')),
            'tasks' => $this->render($repository->find('cosa-vuoi-fare')),
            // Ancore del manuale a pagina unica → pagina nuova: le legge lo
            // script della pagina (la parte dopo # non arriva al server).
            'legacyAnchors' => LegacyAnchors::MAP,
        ]);
    }

    /**
     * Indice di ricerca (2026-10-29): documenti, sinonimi e regole della
     * lingua per la ricerca nel browser (MiniSearch). In cache finché i
     * file del manuale non cambiano (ManualSearchIndex::fingerprint()).
     */
    public function searchIndex(): JsonResponse
    {
        $repository = ManualRepository::fromConfig();

        if (! $repository->exists()) {
            throw new NotFoundHttpException;
        }

        $index = ManualSearchIndex::fromConfig()->cached();

        return response()->json($index)->setEtag($index['versione']);
    }

    public function page(string $slug): View
    {
        $repository = ManualRepository::fromConfig();
        $page = $repository->exists() ? $repository->find($slug) : null;

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        $slugs = $repository->all()->keys()->values();
        $index = $slugs->search($slug);

        return view('documentation.page', [
            'page' => $page,
            'rendered' => $this->render($page),
            'categories' => $repository->byCategory(),
            'previous' => $index > 0 ? $repository->find($slugs[$index - 1]) : null,
            'next' => $index < $slugs->count() - 1 ? $repository->find($slugs[$index + 1]) : null,
        ]);
    }

    /** @return array{html: string, toc: list<array{level: int, id: string, text: string}>}|null */
    private function render(?ManualPage $page): ?array
    {
        if ($page === null) {
            return null;
        }

        // APP_URL dal .env (via config/app.php): l'indirizzo reale di questa
        // installazione, non il localhost dello sviluppo.
        return ManualRenderer::renderPage(
            $page,
            fn (string $slug) => route('documentation.page', $slug),
            ['APP_URL' => rtrim((string) config('app.url'), '/')],
        );
    }
}
