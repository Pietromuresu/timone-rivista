<?php

namespace App\Support\Manual;

/**
 * Ancore del manuale a pagina unica (fino al 2026-10-29) → pagina nuova.
 *
 * Un link vecchio come `/manuale#9bis-le-copertine-i-ii-iii-iv-e-la-bandella`
 * arriva al server senza la parte dopo `#`: lo legge un piccolo script su
 * /manuale (documentation/index.blade.php) che, con questa mappa, porta a
 * /manuale/copertine. Senza JavaScript si resta sulla pagina d'ingresso.
 *
 * Contiene TUTTE le ancore dei titoli del manuale archiviato
 * (docs/archivio/MANUALE_UTENTE_2026-10-29.md): lo verifica un test, che
 * fallisce anche se una pagina di destinazione viene rinominata. Chi
 * rinomina uno slug aggiorna questa mappa.
 */
final class LegacyAnchors
{
    public const MAP = [
        '1-prima-di-iniziare' => 'inizia-qui',
        '2-scegliere-rivista-e-numero' => 'riviste-e-numeri',
        '3-leggere-la-griglia-del-timone' => 'viste-del-timone',
        '4-spostare-le-pagine' => 'spostare-pagine',
        '4bis-bloccare-una-pagina' => 'bloccare-pagina',
        '5-creare-assegnarerimuovere-contenuti-e-modificare-lo-spazio-occupato' => 'assegnare-contenuto',
        '5bis-occupazione-di-pagina-obbligatoria' => 'spazio-occupato',
        '5ter-modificare-un-contenuto-e-rinominarlo' => 'modificare-contenuto',
        '6-cambiare-lo-stato-di-una-pagina' => 'stato-pagina',
        '6bis-selezione-multipla-e-azioni-di-massa' => 'selezione-multipla',
        '6ter-collegare-pagine-in-un-gruppo-permanente' => 'gruppi-collegati',
        '6quater-scorciatoie-da-tastiera' => 'scorciatoie',
        '6quinquies-annullare-e-ripetere-ctrlz' => 'annullare-ripetere',
        '6sexies-commenti-sulle-pagine' => 'commenti',
        '7-caricare-e-vedere-un-pdf' => 'caricare-file',
        '8-leggere-il-cruscotto-pubblicitario' => 'cruscotto-pubblicitario',
        '8bis-pubblicità-prenotate-e-chiusura-del-numero' => 'pubblicita-prenotate',
        '9-modificare-il-numero-totale-di-pagine' => 'pagine-interne',
        '9bis-le-copertine-i-ii-iii-iv-e-la-bandella' => 'copertine',
        '9ter-peso-della-rivista' => 'peso',
        '9quater-segnature-trentaduesimi' => 'segnature',
        '10-consultare-lo-storico-spostamenti-e-la-cronologia-generale' => 'storico-e-cronologia',
        '11-avvisi-automatici' => 'avvisi',
        '12-creare-una-rivista--un-numero-duplicare-la-struttura' => 'creare-numero',
        '12bis-modificare-una-rivista-redattore-di-riferimento-e-scadenza-stampa' => 'rivista',
        '13-gestire-gli-utenti-solo-admin' => 'utenti',
        '13bis-eliminare-una-rivista-un-numero-o-un-utente-solo-admin' => 'eliminare',
        '14-cosa-può-fare-ciascun-ruolo' => 'ruoli',
    ];
}
