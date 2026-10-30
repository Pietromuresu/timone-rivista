<?php

use App\Enums\UserRole;
use App\Livewire\Timone\ContentCreate;
use App\Livewire\Timone\Grid;
use App\Livewire\Timone\WeightPanel;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;
use App\Support\Manual\ManualRepository;
use App\Support\ManualRenderer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

// Aiuto contestuale (2026-10-30, Fase 5 del manuale): le «?» accanto agli
// elementi meno ovvi (<x-manual-help>) e i link fissi al manuale nelle view.

/** Gli argomenti chiesti dalla Fase 5, con la pagina (e la sezione) che li spiega. */
const CONTEXT_HELP = [
    'Peso' => 'peso',
    'Selezione multipla' => 'selezione-multipla',
    'Modalità scambio' => 'scambiare-pagine',
    'Pagine interne' => 'pagine-interne',
    'Bandella' => 'copertine#la-bandella',
    'Occupazione' => 'spazio-occupato',
];

/**
 * Ogni link al manuale scritto in una view: le «?» e le route a una pagina
 * con lo slug fisso.
 *
 * @return list<array{file: string, slug: string, anchor: ?string, label: ?string}>
 */
function manualLinksInViews(): array
{
    $links = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        $source = $file->getContents();
        $where = $file->getRelativePathname();

        preg_match_all('/<x-manual-help\b(.*?)\/>/s', $source, $tags);
        foreach ($tags[1] as $attributes) {
            // Solo valori fissi: uno slug calcolato sfuggirebbe al controllo.
            expect($attributes)->not->toMatch('/(^|\s):(slug|anchor)=/', "{$where}: slug e anchor vanno scritti come testo fisso");
            preg_match('/\bslug="([^"]*)"/', $attributes, $slug);
            preg_match('/\banchor="([^"]*)"/', $attributes, $anchor);
            preg_match('/\blabel="([^"]*)"/', $attributes, $label);
            expect($slug)->not->toBeEmpty("{$where}: <x-manual-help> senza slug");
            $links[] = ['file' => $where, 'slug' => $slug[1], 'anchor' => $anchor[1] ?? null, 'label' => $label[1] ?? null];
        }

        preg_match_all("/route\\(\\s*'documentation\\.page'\\s*,\\s*'([^']+)'/", $source, $routes);
        foreach ($routes[1] as $target) {
            [$slug, $anchor] = array_pad(explode('#', $target, 2), 2, null);
            $links[] = ['file' => $where, 'slug' => $slug, 'anchor' => $anchor, 'label' => null];
        }
    }

    return $links;
}

test('every manual slug and section used in the views exists', function () {
    $repository = ManualRepository::fromConfig();
    $links = manualLinksInViews();

    expect($links)->not->toBeEmpty();

    foreach ($links as $link) {
        $page = $repository->find($link['slug']);
        expect($page)->not->toBeNull("{$link['file']}: la pagina del manuale «{$link['slug']}» non esiste");

        if ($link['anchor'] !== null) {
            $ids = array_column(ManualRenderer::renderPage($page, fn ($slug) => '/manuale/'.$slug)['toc'], 'id');
            // toContain() di Pest prende solo valori, niente messaggio: lo dice toBeTrue().
            expect(in_array($link['anchor'], $ids, true))->toBeTrue("{$link['file']}: la sezione «{$link['anchor']}» non è in «{$link['slug']}»");
        }
    }
});

test('each topic of the contextual help has its «?» pointing to the right page', function () {
    $byLabel = collect(manualLinksInViews())->whereNotNull('label')
        ->mapWithKeys(fn ($link) => [$link['label'] => $link['slug'].($link['anchor'] ? '#'.$link['anchor'] : '')]);

    foreach (CONTEXT_HELP as $label => $target) {
        expect($byLabel->get($label))->toBe($target, "manca la «?» per «{$label}»");
    }
});

test('the «?» is a link that opens the manual section in a new tab, with a spoken label', function () {
    $html = Blade::render('<x-manual-help slug="copertine" anchor="la-bandella" label="Bandella" />');

    expect($html)->toContain('href="'.route('documentation.page', 'copertine').'#la-bandella"')
        ->toContain('target="_blank" rel="noopener"')
        ->toContain('aria-label="Aiuto: Bandella (si apre il manuale in una nuova scheda)"')
        ->toContain('class="manual-context-help"');
});

test('the timone shows the «?» where the feature appears', function () {
    $issue = Issue::factory()->create(['magazine_id' => Magazine::factory()->create()->id, 'total_pages' => 6]);
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($issue->magazine);

    $grid = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue]);
    expect($grid->html())->toContain('data-manual-help="copertine"')
        ->not->toContain('data-manual-help="scambiare-pagine"');

    expect($grid->call('toggleSwapMode')->html())->toContain('data-manual-help="scambiare-pagine"');
    expect($grid->call('toggleSwapMode')->call('toggleSelectionMode')->html())->toContain('data-manual-help="selezione-multipla"');
    expect($grid->call('togglePageCountEditor')->html())->toContain('data-manual-help="pagine-interne"');

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])->assertSeeHtml('data-manual-help="peso"');
    Livewire::actingAs($user)->test(ContentCreate::class, ['issue' => $issue])->call('toggleForm')->assertSeeHtml('data-manual-help="spazio-occupato"');
});
