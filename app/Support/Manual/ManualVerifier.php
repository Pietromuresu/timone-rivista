<?php

namespace App\Support\Manual;

/**
 * «Non si perde niente» (2026-10-29): estrae dal manuale archiviato
 * (docs/archivio/MANUALE_UTENTE_2026-10-29.md) i termini che non devono
 * sparire — grassetti, emoji-comando, scorciatoie da tastiera, valori con
 * unità — e controlla che ciascuno compaia nelle pagine nuove.
 *
 * Il confronto è su testo normalizzato (minuscole, niente virgolette né
 * asterischi, link ridotti al testo, «(dal AAAA-MM-GG)» tolto perché le
 * date stanno in «Novità»), così una frase riscritta solo nella
 * punteggiatura non è un falso allarme. Le eccezioni hanno sempre un
 * motivo: un bottone rinominato (con il nome nuovo, che allora deve
 * comparire lui) o un'intestazione di paragrafo sostituita da un titolo.
 */
final class ManualVerifier
{
    /**
     * Termine del manuale vecchio → [termine che deve comparire al suo posto (null = nessuno), motivo].
     *
     * @var array<string, array{0: string|null, 1: string}>
     */
    public const EXCEPTIONS = [
        // Bottoni rinominati nell'app (verificati nelle view il 2026-10-29):
        // nel manuale nuovo deve comparire il nome attuale.
        '☑️ Selezione multipla' => ['☑️ Selezione', 'bottone «☑️ Selezione» (grid.blade.php; «multipla» solo per i lettori di schermo)'],
        '🔗 Collega selezionate' => ['🔗 Collega', 'bottone «🔗 Collega» nella fascia della selezione (partials/selection-bar.blade.php)'],
        '"✂️ Scollega selezionate dal gruppo"' => ['✂️ Scollega', 'bottone «✂️ Scollega»; nel menu stretto «✂️ Scollega dal gruppo»'],
        'Bloccare selezionate' => ['🔒 Blocca', 'bottone «🔒 Blocca» nella fascia della selezione'],
        'Sbloccare selezionate' => ['🔓 Sblocca', 'bottone «🔓 Sblocca» nella fascia della selezione'],
        'Seleziona tutte' => ['Tutte', 'bottone «Tutte» nella fascia della selezione'],
        'Deseleziona tutte' => ['Deseleziona', 'bottone «Deseleziona» nella fascia della selezione'],
        'Cambiare stato' => ['Stato', 'il menu «Cambia stato in» è diventato «Stato:» nella fascia della selezione'],
        '"📜 Storico spostamenti"' => ['📜 Storico', 'bottone «📜 Storico»; il pannello si intitola ancora «Storico spostamenti»'],
        'Modalità scambio (bottone "🔀 Modalità scambio" in barra, funziona in tutte e tre le modalità):' => ['🔀 Scambio', 'bottone «🔀 Scambio»; il banner dice «Modalità scambio attiva»'],
        // Battenti (2026-10-30): la bandella è diventata battente di I e di IV.
        '«+ Aggiungi bandella»' => ['«+ Battente I»', 'bottoni «+ Battente I» / «+ Battente IV» (partials/cover-band.blade.php, etichetta da CoverFlap::label())'],
        '«− Togli bandella»' => ['− Togli battente I', 'bottoni «− Togli battente I» / «− Togli battente IV» (partials/cover-band.blade.php)'],
        '20MB' => ['100 MB', 'limite vecchio (incoerenza 2 dell\'inventario): oggi 100 MB, config/timone.php upload_max_kb; «prima 20 MB» è in Novità'],
        // Intestazioni di paragrafo diventate titoli di sezione o pagine.
        'Ogni card/riga mostra:' => [null, 'intestazione della vecchia tabella: ora è la pagina «Legenda»'],
        'Con il mouse (tutte e tre le modalità):' => ['Con il mouse', 'intestazione diventata titolo; «in tutte e tre le viste» è nel testo'],
        'Da tastiera (tutte e tre le modalità):' => ['Da tastiera', 'intestazione diventata titolo di sezione'],
        'Per creare un nuovo articolo o una nuova pubblicità:' => [null, 'intestazione: ora pagina «Creare un articolo o una pubblicità»'],
        'Per collegare' => ['Collegare', 'intestazione diventata titolo di sezione'],
        'Per scollegare' => ['Scollegare', 'intestazione diventata titolo di sezione'],
        'Per cambiare lo spazio su una pagina' => ['Cambiare lo spazio su una pagina', 'intestazione diventata titolo di sezione'],
        'Per un contenuto che continua su più pagine' => ['Un contenuto che continua su più pagine', 'intestazione diventata titolo di sezione'],
        'Come si inserisce' => [null, 'intestazione: i passi sono nell\'«In breve» di «Spazio occupato»'],
        'Assegnare senza risalire' => ['I tre modi per assegnare', 'intestazione diventata titolo di sezione'],
        'occupazione di pagina (obbligatoria, vedi [§5bis](#5bis-occupazione-di-pagina-obbligatoria))' => ['occupazione', 'il rimando a §5bis è ora un link alla pagina «Spazio occupato»'],
        'Ctrl+click — o Cmd+click su Mac — direttamente sulla card/riga' => ['Cmd+click su Mac', 'frase riscritta senza incisi'],
    ];

    /**
     * @return array{bold: list<string>, emoji: list<string>, shortcuts: list<string>, numbers: list<string>}
     */
    public static function requiredTerms(string $archive): array
    {
        preg_match_all('/\*\*(.+?)\*\*/u', $archive, $bold);
        preg_match_all('/[\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{2460}-\x{27BF}\x{2B00}-\x{2BFF}\x{1F300}-\x{1FAFF}]\x{FE0F}?/u', $archive, $emoji);
        preg_match_all('/(?:Ctrl|Cmd|⌘)(?:\/⌘)?(?:\+(?:Shift|Ctrl|clic|click|[A-Z]))+|\bEsc\b|\bInvio\b|\bTab\b/u', $archive, $keys);
        preg_match_all('/\b\d+(?:[.,]\d+)?\s?(?:MB|GB|g|mm|ore|giorni|passi|secondi|px|caratteri)\b/u', $archive, $numbers);

        $unique = fn (array $items) => array_values(array_unique(array_map('trim', $items)));

        return [
            'bold' => $unique($bold[1]),
            'emoji' => $unique($emoji[0]),
            'shortcuts' => $unique($keys[0]),
            'numbers' => $unique($numbers[0]),
        ];
    }

    /**
     * Termini mancanti nel testo nuovo, per tipo, eccezioni già applicate.
     *
     * @return list<array{kind: string, term: string, expected: string}>
     */
    public static function missing(string $archive, string $newText): array
    {
        $haystack = self::normalize($newText);
        $missing = [];

        foreach (self::requiredTerms($archive) as $kind => $terms) {
            foreach ($terms as $term) {
                $expected = array_key_exists($term, self::EXCEPTIONS) ? self::EXCEPTIONS[$term][0] : $term;

                if ($expected === null) {
                    continue;
                }

                $needle = $kind === 'emoji' ? self::stripVariation($expected) : self::normalize($expected);

                if ($needle !== '' && ! str_contains($kind === 'emoji' ? self::stripVariation($newText) : $haystack, $needle)) {
                    $missing[] = ['kind' => $kind, 'term' => $term, 'expected' => $expected];
                }
            }
        }

        return $missing;
    }

    public static function normalize(string $text): string
    {
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $text);
        $text = preg_replace('/\s*\(dal \d{4}-\d{2}-\d{2}[^)]*\)/u', '', $text);
        $text = str_replace(['**', '"', '«', '»', '“', '”', '`', '*', '_'], '', $text);
        $text = rtrim(mb_strtolower($text));
        $text = preg_replace('/\s+/u', ' ', $text);
        // «3mm» e «3 mm» sono lo stesso valore.
        $text = preg_replace('/(\d)\s+(mb|gb|g|mm|ore|giorni|passi|secondi|px|caratteri)\b/u', '$1$2', $text);

        return rtrim($text, ': ');
    }

    /** Il selettore di variante (U+FE0F) dopo un'emoji: ☑️ e ☑ sono lo stesso simbolo. */
    private static function stripVariation(string $text): string
    {
        return str_replace("\u{FE0F}", '', $text);
    }
}
