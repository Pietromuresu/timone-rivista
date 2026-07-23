<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\Issues\Show as IssueShow;
use App\Livewire\Magazines\Index as MagazineIndex;
use App\Livewire\Magazines\Show as MagazineShow;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', MagazineIndex::class)->name('dashboard');

    Route::get('/riviste', MagazineIndex::class)->name('magazines.index');

    Route::get('/riviste/{magazine:slug}', MagazineShow::class)->name('magazines.show');

    Route::get('/riviste/{magazine:slug}/numeri/{issue}', IssueShow::class)
        ->scopeBindings()
        ->name('issues.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
