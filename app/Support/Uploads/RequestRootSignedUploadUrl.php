<?php

namespace App\Support\Uploads;

use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;

/**
 * URL firmato dell'upload temporaneo Livewire generato sull'indirizzo REALE
 * della richiesta (Fase 1, bug 9, 2026-10-26).
 *
 * AppServiceProvider forza tutte le URL generate su APP_URL
 * (URL::forceRootUrl, introdotto per le miniature con host sbagliato). Ma
 * Livewire firma l'URL di upload con quella root e poi lo verifica in
 * FileUploadController con `request()->hasValidSignature()`, che usa
 * l'indirizzo con cui la richiesta è DAVVERO arrivata. Se APP_URL non
 * coincide al carattere con l'indirizzo usato dal browser (http/https,
 * IP invece del nome, porta, "localhost"), la firma non torna mai: 401, il
 * file non viene caricato e l'utente vede solo "caricamento non riuscito"
 * — la causa più probabile del bug "in produzione non si caricano media".
 *
 * Generando l'URL sulla root della richiesta corrente, firma e verifica
 * usano lo stesso indirizzo (lo stesso da cui il browser sta già parlando
 * con l'app, quindi nessun problema di origine/cookie). Tutte le altre URL
 * dell'app continuano a seguire APP_URL come prima. Registrata nel
 * container in AppServiceProvider: Livewire risolve questa classe tramite
 * una real-time facade.
 *
 * Non risolve un proxy HTTPS che non invia X-Forwarded-Proto (Laravel
 * vedrebbe http, il browser usa https): quello è nella checklist di
 * docs/09-deploy.md.
 */
class RequestRootSignedUploadUrl extends GenerateSignedUploadUrl
{
    public function forLocal()
    {
        $request = request();

        if (! $request || ! $request->getHost()) {
            return parent::forLocal();
        }

        URL::forceRootUrl($request->getSchemeAndHttpHost().$request->getBaseUrl());

        try {
            return parent::forLocal();
        } finally {
            // Ripristina esattamente il comportamento globale di AppServiceProvider.
            URL::forceRootUrl(config('app.url') ?: null);
        }
    }
}
