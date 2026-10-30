<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tolleranza controllo formato pubblicitario (Fase 2, §2.3)
    |--------------------------------------------------------------------------
    |
    | Il controllo formato confronta le dimensioni reali di un PDF caricato
    | con quelle nominali del formato pubblicitario assegnato più
    | l'abbondanza di stampa richiesta (+3mm per lato, +6mm per dimensione,
    | vedi App\Support\PdfFormatChecker::BLEED_MM). Questo valore è una
    | tolleranza AGGIUNTIVA oltre l'abbondanza, per assorbire piccoli errori
    | di esportazione — la richiesta esplicita dell'utente era "1-2mm",
    | esposta qui come env configurabile invece che una costante nel codice
    | perché è un parametro che la redazione potrebbe voler affinare senza
    | un intervento di sviluppo.
    |
    */
    'pdf_format_tolerance_mm' => (float) env('TIMONE_PDF_FORMAT_TOLERANCE_MM', 1.5),

    /*
    |--------------------------------------------------------------------------
    | Preavviso mail di sollecito scadenza stampa (Fase 5.3)
    |--------------------------------------------------------------------------
    |
    | Quanti giorni prima di issues.print_deadline_at inviare la mail di
    | sollecito al redattore di riferimento della rivista. Globale (non per
    | rivista): il prompt lo chiede come "parametro configurabile", senza
    | chiedere esplicitamente granularità per rivista — una singola issue
    | può quindi avere una scadenza propria (print_deadline_at), ma la
    | politica "con quanto anticipo avvisare" è la stessa per tutta
    | l'installazione, scelta pragmatica (CLAUDE.md, regola 3) per non
    | aggiungere una colonna per-rivista a un requisito che non la chiede.
    |
    */
    'deadline_reminder_days_before' => (int) env('TIMONE_DEADLINE_REMINDER_DAYS_BEFORE', 3),

    /*
    |--------------------------------------------------------------------------
    | Posizionamento delle pubblicità previste (Fase 5, 2026-10-27)
    |--------------------------------------------------------------------------
    |
    | Usati da App\Support\AdPlacement\AdPlacementService alla creazione di
    | un numero. cover_order: copertine riempite tutte, di fila, dalle prime
    | pubblicità previste di qualsiasi formato (prima le pagine intere), in
    | quest'ordine (la I è l'ultima: se usata il sistema lo segnala). start_page/step: la
    | sequenza alternata delle altre (2, 4, 6…). In config e non nel codice
    | perché sono abitudini redazionali che possono cambiare.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Dimensione massima di un file caricato (revisione 2, Fase 1, bug 5)
    |--------------------------------------------------------------------------
    |
    | In KB, come la regola `max:` di Laravel. UNICO punto del limite
    | applicativo: lo leggono la regola del modulo contenuti
    | (App\Support\ContentFormRules::maxKilobytes()), l'upload temporaneo
    | di Livewire (config/livewire.php) e il limite mostrato/controllato nel
    | browser (App\Support\Uploads\UploadLimits). Prima il modulo era
    | fermo a 20 MB mentre PHP, nginx e Livewire accettavano 100 MB: i PDF
    | di stampa ad alta risoluzione venivano rifiutati. Il server deve
    | accettare un po' di più (upload_max_filesize/post_max_size ≥ 110M,
    | client_max_body_size 110M): vedi docs/09-deploy.md e
    | `php artisan timone:diagnose-upload`.
    |
    */
    'upload_max_kb' => (int) env('TIMONE_UPLOAD_MAX_KB', 102400),

    'ad_placement' => [
        'cover_order' => ['II', 'III', 'IV', 'I'],
        'start_page' => (int) env('TIMONE_AD_PLACEMENT_START_PAGE', 2),
        'step' => (int) env('TIMONE_AD_PLACEMENT_STEP', 2),
    ],

    /*
    | Export PDF sfogliabile della rivista (revisione 2, Fase 5, 2026-10-28).
    | memory_limit: alzato SOLO dentro il job (TCPDF costruisce il PDF in RAM,
    | ~2 volte il file finale); timeout: secondi prima che il worker lo
    | interrompa; disk/directory: dove si salva il file (sostituito a ogni
    | generazione); preview_settings: profilo Ghostscript della bassa
    | risoluzione (/ebook = immagini ~150 dpi).
    */
    'magazine_export' => [
        'memory_limit' => env('TIMONE_EXPORT_MEMORY_LIMIT', '1024M'),
        'timeout' => (int) env('TIMONE_EXPORT_TIMEOUT', 1800),
        'disk' => 'local',
        'directory' => 'exports',
        'preview_settings' => '/ebook',
    ],

    /*
    |--------------------------------------------------------------------------
    | Manuale utente (2026-10-29)
    |--------------------------------------------------------------------------
    |
    | Cartella delle pagine del manuale (una per argomento, con il front
    | matter): App\Support\Manual\ManualRepository. Qui e non in
    | config/app.php, che è un file protetto (CLAUDE.md, regola 4): lì resta
    | `app.manual_path`, che punta a MANUALE_UTENTE.md, ora generato da
    | `php artisan manuale:genera`. Si cambia solo nei test.
    |
    */
    'manual_dir' => resource_path('manuale'),

    /*
    | Copia del manuale a pagina unica com'era il 2026-10-29: la usa
    | `php artisan manuale:verifica` per controllare che nelle pagine nuove
    | non manchi nulla. Non va modificata.
    */
    'manual_archive' => base_path('docs/archivio/MANUALE_UTENTE_2026-10-29.md'),

    // Screenshot del manuale (2026-10-29, Fase 4): un .webp per blocco
    // `:::screenshot nome`, generati da `npm run manuale:screenshot`
    // (App\Support\Manual\ManualScreenshotFiles). Modificabile solo nei test.
    'manual_images' => public_path('manuale/img'),

    /*
    |--------------------------------------------------------------------------
    | Backup (2026-10-30)
    |--------------------------------------------------------------------------
    |
    | Cartella in cui il servizio «backup-agent» scrive l'esito dei backup e
    | le impostazioni senza segreti (settings.json: destinatari degli
    | avvisi, soglie). È il volume compose `backup_status`, montato in sola
    | lettura nei container app e scheduler. La configurazione vera sta in
    | backup.env, che l'app di proposito non vede (contiene credenziali):
    | vedi docs/OPERATIONS.md. Nessuna variabile d'ambiente: si cambia solo
    | nei test.
    |
    */
    'backup' => [
        'status_path' => '/var/backup-status',
    ],
];
