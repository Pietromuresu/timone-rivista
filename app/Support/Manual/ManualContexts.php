<?php

namespace App\Support\Manual;

/**
 * Da quale schermata si apre l'assistente (2026-10-29): il bottone «?»
 * manda il contesto, e le pagine del manuale legate a quella schermata
 * ricevono un bonus fisso (ManualAssistant::CONTEXT_BONUS). Fisso, non
 * imparato: chi è sul timone chiede quasi sempre del timone.
 *
 * Una tabella invece di condizioni sparse: una schermata nuova si
 * aggiunge qui (un test controlla che le pagine esistano).
 */
final class ManualContexts
{
    /** Nome della route (con * finale) → contesto. Vince la prima che corrisponde. */
    public const ROUTES = [
        'issues.show' => 'timone',
        'issues.create' => 'nuovo-numero',
        'magazines.index' => 'riviste',
        'dashboard' => 'riviste',
        'magazines.*' => 'rivista',
        'users.*' => 'utenti',
    ];

    /** Contesto → pagine del manuale che ricevono il bonus. */
    public const PAGES = [
        'timone' => [
            'spostare-pagine', 'assegnare-contenuto', 'stato-pagina', 'pannello-contenuti', 'viste-del-timone',
            'annullare-ripetere', 'commenti', 'copertine', 'caricare-file', 'selezione-multipla', 'scambiare-pagine',
            'pagine-interne', 'disposizione-nella-pagina', 'riepilogo-del-numero', 'legenda',
        ],
        'nuovo-numero' => ['creare-numero', 'segnaposto', 'copertine', 'scadenza-stampa', 'peso'],
        'riviste' => ['riviste-e-numeri', 'rivista', 'inizia-qui'],
        'rivista' => ['rivista', 'riviste-e-numeri', 'creare-numero', 'chiudere-numero', 'eliminare', 'segnature', 'peso'],
        'utenti' => ['utenti', 'ruoli', 'eliminare'],
    ];

    public static function forRoute(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        foreach (self::ROUTES as $pattern => $context) {
            if ($routeName === $pattern || (str_ends_with($pattern, '*') && str_starts_with($routeName, substr($pattern, 0, -1)))) {
                return $context;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function pages(?string $context): array
    {
        return self::PAGES[$context] ?? [];
    }
}
