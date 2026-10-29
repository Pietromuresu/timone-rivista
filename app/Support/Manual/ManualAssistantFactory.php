<?php

namespace App\Support\Manual;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mette insieme l'assistente (2026-10-29): pagine e sinonimi
 * (ManualSearchIndex), domande iniziali (domande.yml), domande imparate
 * pubblicate dal job (tabella manual_learned_questions, meno quelle spente).
 *
 * Costruire il motore costa qualche centinaio di millisecondi: l'assistente
 * resta in cache finché non cambia uno di quei tre ingressi (la chiave
 * della cache contiene la loro impronta), come l'indice di ricerca.
 */
final class ManualAssistantFactory
{
    public function __construct(
        private readonly ManualSearchIndex $index,
        private readonly ManualRepository $repository,
        private readonly string $directory,
    ) {}

    public static function fromConfig(): self
    {
        return new self(ManualSearchIndex::fromConfig(), ManualRepository::fromConfig(), (string) config('timone.manual_dir'));
    }

    /** @return list<array{domanda: string, pagina: string}> */
    public function initialQuestions(): array
    {
        return ManualQuestions::flatten(ManualQuestions::load($this->directory.'/domande.yml'));
    }

    /** @return list<array{domanda: string, pagina: string}> */
    public function controlQuestions(): array
    {
        return ManualQuestions::flatten(ManualQuestions::load($this->directory.'/domande-di-controllo.yml'));
    }

    /**
     * Domande imparate pubblicate, senza quelle spente da un admin e senza
     * quelle che portano a una pagina che non esiste più.
     *
     * @return list<array{domanda: string, pagina: string}>
     */
    public function learnedQuestions(): array
    {
        $pages = $this->repository->all();
        $disabled = DB::table('manual_disabled')->where('tipo', 'domanda')->get(['chiave', 'slug'])
            ->map(fn ($row) => $row->chiave."\0".$row->slug)->all();

        return DB::table('manual_learned_questions')->orderBy('id')->get()
            ->filter(fn ($row) => $pages->has($row->slug) && ! in_array($row->chiave."\0".$row->slug, $disabled, true))
            ->map(fn ($row) => ['domanda' => $row->domanda, 'pagina' => $row->slug])
            ->values()
            ->all();
    }

    public function current(): ManualAssistant
    {
        $questions = [...$this->initialQuestions(), ...$this->learnedQuestions()];
        $key = 'manual-assistant:'.$this->index->fingerprint().':'.md5(json_encode($questions));

        return Cache::remember($key, now()->addDay(), fn () => ManualAssistant::build($this->index->cached(), $questions));
    }

    public function gate(): ManualLearningGate
    {
        return new ManualLearningGate($this->index->cached(), $this->initialQuestions(), $this->controlQuestions());
    }

    /** @return list<string> chiavi delle domande iniziali */
    public function initialKeys(): array
    {
        $keys = new QuestionKey(ManualSynonyms::load($this->directory.'/sinonimi.yml'));

        return array_values(array_unique(array_map(fn (array $q) => $keys->of($q['domanda']), $this->initialQuestions())));
    }

    /** @return array<string, array{0: string, 1: string}> slug → [hash della pagina, hash dell'In breve] */
    public function pageHashes(): array
    {
        return $this->repository->all()->map(fn (ManualPage $page) => [$page->versionHash(), $page->inBreveHash()])->all();
    }
}
