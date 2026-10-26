<?php

namespace App\Providers;

use App\Models\Issue;
use App\Observers\IssueObserver;
use App\Support\Uploads\RequestRootSignedUploadUrl;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fase 1, bug 9 (2026-10-26): l'URL firmato dell'upload temporaneo
        // Livewire va generato sull'indirizzo reale della richiesta, non su
        // APP_URL forzato qui sotto — altrimenti la firma non torna e
        // l'upload fallisce con 401. Vedi la classe per il dettaglio.
        $this->app->bind(GenerateSignedUploadUrl::class, RequestRootSignedUploadUrl::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Issue::observe(IssueObserver::class);

        // route()/url()/asset() per default ignorano APP_URL durante una
        // richiesta HTTP reale e usano l'header Host in arrivo (vedi
        // problema thumbnail con host sbagliato). Forziamo la root URL a
        // partire da APP_URL così le URL generate sono sempre coerenti con
        // il .env, indipendentemente da proxy/dominio/localhost.
        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));

            if (str_starts_with(config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
