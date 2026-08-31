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

];
