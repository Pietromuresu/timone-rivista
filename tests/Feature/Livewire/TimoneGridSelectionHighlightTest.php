<?php

use App\Livewire\Timone\Grid;
use Livewire\Livewire;

// Bug 4 (2026-10-12): con Ctrl+click l'ultima pagina cliccata non mostrava
// il bordo verde di selezione. La selezione vive solo lato server
// (Grid::$selectedPageIds) e il bordo verde è la classe ring-emerald-500
// applicata da page-card.blade.php — ma la card ha tabindex="0" e
// `focus:ring-2 focus:ring-indigo-500`: l'ultima card cliccata è anche
// quella con il focus, e la variante focus: (emessa da Tailwind DOPO le
// utility base, stessa specificità) sovrascrive larghezza e colore del ring.
// Il rendering reale nel browser non è verificabile qui (nessun Dusk): il
// test controlla che la card selezionata non porti con sé una variante
// focus: che possa sostituire il verde.
// reorderableIssue()/editorFor() sono in TimoneGridReorderTest.php.

/**
 * Attributo class del <div> card con data-page-id dato, estratto dall'HTML
 * renderizzato del componente.
 */
function pageCardClasses(string $html, int $pageId): string
{
    preg_match('/<div\s[^>]*data-page-id="'.$pageId.'"[^>]*\sclass="([^"]*)"/s', $html, $m);

    return $m[1] ?? '';
}

test('bug 4: every ctrl+clicked page, including the last one (which keeps focus), keeps the green selection ring', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageIds = $issue->pages()->orderBy('pages.position')->limit(3)->pluck('id')->all();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('viewMode', 'griglia');

    foreach ($pageIds as $pageId) {
        $component->call('ctrlClickPage', $pageId);
    }

    $html = $component->html();

    foreach ($pageIds as $pageId) {
        $classes = pageCardClasses($html, $pageId);

        expect($classes)->toContain('ring-emerald-500')
            // Nessuna variante focus: di un altro colore può vincere sul verde.
            ->and($classes)->not->toMatch('/focus:ring-(?!emerald)[a-z]+-\d+/');
    }
});

test('bug 4: in list view too, the focused (last ctrl+clicked) row keeps the green selection ring', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    $pageIds = $issue->pages()->orderBy('pages.position')->limit(3)->pluck('id')->all();

    $component = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('setViewMode', 'lista');

    foreach ($pageIds as $pageId) {
        $component->call('ctrlClickPage', $pageId);
    }

    $html = $component->html();

    foreach ($pageIds as $pageId) {
        preg_match('/<li\s[^>]*data-page-id="'.$pageId.'"[^>]*\sclass="([^"]*)"/s', $html, $m);

        expect($m[1] ?? '')->toContain('ring-emerald-500')
            ->and($m[1] ?? '')->not->toMatch('/focus:ring-(?!emerald|inset|\d)[a-z]+-\d+/');
    }
});

test('bug 4: a page that is not selected keeps the usual indigo keyboard-focus ring', function () {
    $issue = reorderableIssue();
    $user = editorFor($issue);
    [$selected, $other] = $issue->pages()->orderBy('pages.position')->limit(2)->pluck('id')->all();

    $html = Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->call('ctrlClickPage', $selected)
        ->html();

    expect(pageCardClasses($html, $other))->toContain('focus:ring-indigo-500');
});
