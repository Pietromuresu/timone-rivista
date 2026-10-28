<?php

namespace App\Providers;

use App\Models\Issue;
use App\Observers\IssueObserver;
use App\Support\Uploads\RequestRootSignedUploadUrl;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        //$this->app->bind(GenerateSignedUploadUrl::class, RequestRootSignedUploadUrl::class);

        // fix livewire 401
        UrlGenerator::macro('alternateHasCorrectSignature', function (Request $request, $absolute = true, array $ignoreQuery = []) {
            $ignoreQuery[] = 'signature';

            // ensure the base path is applied to absolute url
            $absoluteUrl = url($request->path()); // forceRootUrl and forceScheme will apply
            $url = $absolute ? $absoluteUrl : '/'.$request->path();

            $queryString = collect(explode('&', (string) $request->server->get('QUERY_STRING')))
            ->reject(fn ($parameter) => in_array(Str::before($parameter, '='), $ignoreQuery))
            ->join('&');
            $original = rtrim($url.'?'.$queryString, '?');

            $keys = call_user_func($this->keyResolver);

            $keys = is_array($keys) ? $keys : [$keys];

            foreach ($keys as $key) {
                if (hash_equals(
                    hash_hmac('sha256', $original, $key),
                                (string) $request->query('signature', '')
                )) {
                    return true;
                }
            }
        });

        UrlGenerator::macro('alternateHasValidSignature', function (Request $request, $absolute = true, array $ignoreQuery = []) {
            return \URL::alternateHasCorrectSignature($request, $absolute, $ignoreQuery)
            && \URL::signatureHasNotExpired($request);
        });

        Request::macro('hasValidSignature', function ($absolute = true, array $ignoreQuery = []) {
            return \URL::alternateHasValidSignature($this, $absolute, $ignoreQuery);
        });
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
