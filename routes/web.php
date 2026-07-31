<?php

use App\Http\Controllers\AdDashboardExportController;
use App\Http\Controllers\PageFileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TimoneExportController;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Issues\Show as IssueShow;
use App\Livewire\Magazines\Create as MagazineCreate;
use App\Livewire\Magazines\Index as MagazineIndex;
use App\Livewire\Magazines\Show as MagazineShow;
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

    Route::get('/riviste/{magazine:slug}/numeri/{issue}/export/timone.pdf', [TimoneExportController::class, 'pdf'])
        ->scopeBindings()
        ->name('issues.export.timone-pdf');

    Route::get('/page-files/{pageFile}', [PageFileController::class, 'show'])->name('page-files.show');
    Route::get('/page-files/{pageFile}/thumbnail', [PageFileController::class, 'thumbnail'])->name('page-files.thumbnail');

    Route::get('/utenti', UserIndex::class)->name('users.index');
    Route::get('/utenti/nuovo', UserCreate::class)->name('users.create');
    Route::get('/utenti/{user}/modifica', UserEdit::class)->name('users.edit');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
