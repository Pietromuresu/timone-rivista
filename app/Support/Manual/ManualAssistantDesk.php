<?php

namespace App\Support\Manual;

use App\Support\ManualRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lo sportello dell'assistente (2026-10-29): dalla domanda alla risposta
 * da mostrare. Chiede la pagina a ManualAssistant (con il contesto e le
 * correzioni imparate), prepara il testo («In breve», titolo, link) e la
 * ricevuta per il voto, e annota le domande rimaste senza una risposta
 * sicura per la pagina «Cosa chiedono gli utenti».
 */
final class ManualAssistantDesk
{
    public function __construct(
        private readonly ManualAssistantFactory $factory,
        private readonly ManualRepository $repository,
        private readonly LearnedCorrections $corrections,
    ) {}

    public static function fromConfig(): self
    {
        return new self(ManualAssistantFactory::fromConfig(), ManualRepository::fromConfig(), new StoredLearnedCorrections);
    }

    /**
     * @return array{stato: string, risposta: ?array<string, string>, alternative: list<array<string, string>>, compiti: list<array{titolo: string, url: string}>, ricevuta: ?string}
     */
    public function ask(string $question, ?string $context): array
    {
        $question = Str::limit(trim(preg_replace('/\s+/u', ' ', $question)), 300, '');
        $answer = $this->factory->current()->answer($question, ManualContexts::pages($context), $this->corrections);

        // Pagine scomparse nel frattempo (cache vecchia): si saltano.
        $shown = array_values(array_filter([$answer['pagina'], ...$answer['alternative']], fn ($slug) => $slug !== null && $this->repository->find($slug) !== null));

        if ($answer['stato'] !== 'risposta') {
            $this->rememberUnanswered($answer['chiave'], $question);
        }

        if ($answer['stato'] === 'niente' || $shown === []) {
            return ['stato' => 'niente', 'risposta' => null, 'alternative' => [], 'compiti' => ManualSearchIndex::fromConfig()->tasks(), 'ricevuta' => null];
        }

        $pages = array_map(fn (string $slug) => $this->present($slug), $shown);
        $main = $answer['stato'] === 'risposta' ? array_shift($pages) : null;

        $token = AnswerToken::encode([
            'id' => Str::random(32),
            'chiave' => $answer['chiave'],
            'domanda' => $question,
            'contesto' => $context,
            'stato' => $answer['stato'],
            'pagina' => $main['slug'] ?? null,
            'alternative' => array_column($pages, 'slug'),
            'hash' => collect($shown)->mapWithKeys(fn (string $slug) => [$slug => [$this->repository->find($slug)->versionHash(), $this->repository->find($slug)->inBreveHash()]])->all(),
            'ora' => now()->getTimestamp(),
        ]);

        return ['stato' => $answer['stato'], 'risposta' => $main, 'alternative' => $pages, 'compiti' => [], 'ricevuta' => $token];
    }

    /** @return array{slug: string, titolo: string, url: string, in_breve: string} */
    private function present(string $slug): array
    {
        $page = $this->repository->find($slug);

        return [
            'slug' => $slug,
            'titolo' => $page->titolo,
            'url' => route('documentation.page', $slug),
            'in_breve' => (string) ManualRenderer::inBreve($page, fn (string $s) => route('documentation.page', $s), ['APP_URL' => rtrim((string) config('app.url'), '/')]),
        ];
    }

    /** Domande senza risposta sicura, per chiave: quante volte e l'ultima formulazione. Nessun utente. */
    private function rememberUnanswered(string $key, string $question): void
    {
        if ($key === '') {
            return;
        }

        DB::transaction(function () use ($key, $question) {
            $updated = DB::table('manual_unanswered')->where('chiave', $key)
                ->update(['domanda' => $question, 'volte' => DB::raw('volte + 1'), 'ultima_volta' => now()]);

            if ($updated === 0) {
                DB::table('manual_unanswered')->insert(['chiave' => $key, 'domanda' => $question, 'volte' => 1, 'ultima_volta' => now()]);
            }
        });
    }
}
