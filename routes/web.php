<?php

use App\Http\Controllers\AdDashboardExportController;
use App\Http\Controllers\ContentFileController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\ManualAssistantController;
use App\Http\Controllers\MagazineExportController;
use App\Http\Controllers\PageFileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TimoneExportController;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Issues\Show as IssueShow;
use App\Livewire\Magazines\Create as MagazineCreate;
use App\Livewire\Magazines\Edit as MagazineEdit;
use App\Livewire\Magazines\Index as MagazineIndex;
use App\Livewire\Magazines\Show as MagazineShow;
use App\Livewire\Manual\Questions as ManualQuestions;
use App\Livewire\Users\Create as UserCreate;
use App\Livewire\Users\Edit as UserEdit;
use App\Livewire\Users\Index as UserIndex;
use Illuminate\Support\Facades\Route;

// Nessuna landing page pubblica: è uno strumento redazionale interno,
// non un sito con una home page di marketing (a differenza dello
// scaffold Breeze di default, mai personalizzato finché non scoperto
// durante la sessione del 2026-07-29 — "welcome.blade.php" mostrava
// ancora la pagina stock di Laravel). "/" porta dritto al login o,
// se già autenticati, alla lista riviste.
Route::get('/', function () {
    return redirect()->to(auth()->check() ? route('dashboard') : route('login'));
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', MagazineIndex::class)->name('dashboard');

    Route::get('/riviste', MagazineIndex::class)->name('magazines.index');

    Route::get('/riviste/nuova', MagazineCreate::class)->name('magazines.create');

    Route::get('/riviste/{magazine:slug}', MagazineShow::class)->name('magazines.show');

    Route::get('/riviste/{magazine:slug}/modifica', MagazineEdit::class)->name('magazines.edit');

    Route::get('/riviste/{magazine:slug}/numeri/nuovo', IssueCreate::class)->name('issues.create');

    Route::get('/riviste/{magazine:slug}/numeri/{issue}', IssueShow::class)
        ->scopeBindings()
        ->name('issues.show');

    Route::get('/riviste/{magazine:slug}/numeri/{issue}/export/carico-pubblicitario.csv', [AdDashboardExportController::class, 'csv'])
        ->scopeBindings()
        ->name('issues.export.ad-dashboard-csv');

    Route::get('/riviste/{magazine:slug}/numeri/{issue}/export/carico-pubblicitario.pdf', [AdDashboardExportController::class, 'pdf'])
        ->scopeBindings()
        ->name('issues.export.ad-dashboard-pdf');

    // Revisione 2, Fase 5: PDF sfogliabile della rivista (generato in coda, qui solo il download).
    Route::get('/riviste/{magazine:slug}/numeri/{issue}/export/rivista-{variant}.pdf', [MagazineExportController::class, 'download'])
        ->scopeBindings()
        ->whereIn('variant', ['full', 'preview'])
        ->name('issues.export.magazine-pdf');

    Route::get('/riviste/{magazine:slug}/numeri/{issue}/export/timone.pdf', [TimoneExportController::class, 'pdf'])
        ->scopeBindings()
        ->name('issues.export.timone-pdf');

    Route::get('/page-files/{pageFile}', [PageFileController::class, 'show'])->name('page-files.show');
    Route::get('/page-files/{pageFile}/thumbnail', [PageFileController::class, 'thumbnail'])->name('page-files.thumbnail');

    Route::get('/content-files/{contentFile}', [ContentFileController::class, 'show'])->name('content-files.show');
    Route::get('/content-files/{contentFile}/thumbnail', [ContentFileController::class, 'thumbnail'])->name('content-files.thumbnail');

    // Contenuto d'aiuto, non uno strumento di lavoro su un dato specifico:
    // nessun controllo oltre auth+verified (già richiesto da tutto il resto
    // del gruppo) — stesso trattamento di /dashboard, che non ha altra
    // policy oltre "sei loggato", a differenza delle rotte annidate sotto
    // {magazine:slug} che verificano canAccessMagazine() per quella rivista
    // specifica (qui non c'è alcuna rivista specifica da verificare).
    Route::get('/manuale', [DocumentationController::class, 'show'])->name('documentation.show');
    // Indice di ricerca del manuale (2026-10-29): JSON usato dal browser
    // (la ricerca nel campo del manuale resta nel browser).
    Route::get('/manuale/indice.json', [DocumentationController::class, 'searchIndex'])->name('documentation.search-index');
    // Assistente «Chiedi al manuale» (2026-10-29): domanda → pagina, e voti.
    // Limite per utente: è un servizio interno, ma ogni domanda costa
    // qualche millisecondo di CPU e scrive le domande senza risposta.
    Route::post('/manuale/assistente', [ManualAssistantController::class, 'ask'])
        ->middleware('throttle:30,1')
        ->name('documentation.assistant.ask');
    Route::post('/manuale/assistente/voto', [ManualAssistantController::class, 'vote'])
        ->middleware('throttle:30,1')
        ->name('documentation.assistant.vote');
    // «Cosa chiedono gli utenti»: solo admin (controllo nel componente).
    Route::get('/manuale/amministrazione/domande', ManualQuestions::class)->name('documentation.questions');
    // Una pagina del manuale per argomento (2026-10-29), resources/manuale/{slug}.md.
    Route::get('/manuale/{slug}', [DocumentationController::class, 'page'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('documentation.page');
    // Vecchio indirizzo (Fase 6.1): reindirizzato per non rompere segnalibri.
    Route::redirect('/documentazione', '/manuale', 301);

    Route::get('/utenti', UserIndex::class)->name('users.index');
    Route::get('/utenti/nuovo', UserCreate::class)->name('users.create');
    Route::get('/utenti/{user}/modifica', UserEdit::class)->name('users.edit');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Revisione 2, Fase 4.3: rotta profile.destroy (auto-eliminazione) tolta, vedi UserPolicy::delete().
});

require __DIR__.'/auth.php';
