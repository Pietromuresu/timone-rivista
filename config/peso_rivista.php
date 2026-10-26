<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scaglioni di peso (grammi)
    |--------------------------------------------------------------------------
    |
    | Limiti superiori degli scaglioni di spedizione, in ordine crescente.
    | Una copia ricade nel PRIMO scaglione il cui limite è maggiore o uguale
    | al suo peso (500,0 g → 500; 500,01 g → 1000). Oltre l'ultimo limite la
    | copia è "Fuori scaglione". Il calcolo legge questo array a ogni
    | richiesta (App\Support\Weight\MagazineWeightCalculator::bracketFor):
    | nessuno scaglione è salvato sui numeri, quindi cambiarlo qui cambia
    | subito lo scaglione mostrato per TUTTI i numeri, anche quelli già usciti
    | (il loro peso invece non cambia).
    |
    */
    'scaglioni' => [250, 350, 500, 1000],

    /*
    |--------------------------------------------------------------------------
    | Intervallo ammesso per le grammature (g/m²)
    |--------------------------------------------------------------------------
    |
    | Vale sia per la grammatura interno sia per la copertina, in tutti i form
    | (creazione numero, modale Peso del numero, impostazioni rivista) e nel
    | calcolo: una grammatura fuori intervallo già salvata nel DB rende il
    | risultato "incompleto" invece di produrre un peso plausibile ma
    | sbagliato (App\Support\Weight\WeightLimits). Serve soprattutto a
    | bloccare l'errore di unità più comune: inserire il peso di una pagina
    | (es. 0,8) invece della grammatura scritta sulla carta (es. 80).
    |
    */
    'grammatura' => [
        'min' => 30,
        'max' => 400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Intervallo ammesso per il formato (mm, base e altezza)
    |--------------------------------------------------------------------------
    |
    | Stesso meccanismo delle grammature (App\Support\Weight\WeightLimits),
    | per l'altro errore di unità comune: il formato in centimetri (21 × 27)
    | invece che in millimetri (210 × 270), che rende l'area 100 volte più
    | piccola. Sotto 50 mm è quasi certamente un valore in cm (anche un A3 è
    | 42 cm); 1000 mm lascia margine a qualunque formato da rivista/poster.
    |
    */
    'formato' => [
        'min' => 50,
        'max' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagine di copertina ammesse
    |--------------------------------------------------------------------------
    |
    | Numero pari (un foglio ha due facciate) compreso tra questi limiti.
    | Solo validazione dei form (App\Support\Weight\WeightParameters::rules).
    |
    */
    'pagine_copertina' => [
        'min' => 2,
        'max' => 12,
    ],

];
