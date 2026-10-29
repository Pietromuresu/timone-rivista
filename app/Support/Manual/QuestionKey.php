<?php

namespace App\Support\Manual;

/**
 * Chiave di una domanda (2026-10-29): la stessa domanda scritta in modi
 * diversi deve dare la stessa chiave, perché i voti si sommano per chiave.
 *
 * Ogni parola passa da ItalianText (minuscole, accenti, parole vuote,
 * desinenze) e poi diventa il rappresentante del suo gruppo di sinonimi
 * (la radice della PRIMA parola del gruppo in sinonimi.yml). Parole uniche,
 * in ordine alfabetico: l'ordine in cui si scrive non conta. Così «Come
 * tolgo una pubblicità?», «rimuovere la pubblicità» ed «eliminare una
 * inserzione» danno tutte «pubblic togli».
 */
final class QuestionKey
{
    /** @var array<string, string> radice → rappresentante del gruppo */
    private array $canonical = [];

    /** @param  list<list<string>>  $synonymGroups */
    public function __construct(array $synonymGroups)
    {
        foreach ($synonymGroups as $group) {
            $representative = ItalianText::stem(ItalianText::words($group[0])[0]);

            foreach ($group as $word) {
                // Una parola in due gruppi resta al primo: la chiave non
                // deve dipendere dall'ordine di lettura dei gruppi successivi.
                $this->canonical[ItalianText::stem(ItalianText::words($word)[0])] ??= $representative;
            }
        }
    }

    public function of(string $question): string
    {
        $tokens = array_map(fn (string $t) => $this->canonical[$t] ?? $t, ItalianText::tokens($question));

        // Domanda fatta solo di parole vuote («chi può fare cosa?»): senza di
        // loro non resterebbe nulla, quindi qui la chiave le tiene.
        if ($tokens === []) {
            $tokens = ItalianText::words($question);
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        // La colonna «chiave» è lunga 255: dopo la normalizzazione restano solo
        // lettere e cifre senza accenti, quindi tagliare i byte non spezza caratteri.
        return substr(implode(' ', $tokens), 0, 255);
    }

    /**
     * Somiglianza tra due chiavi (Jaccard sulle parole): 1 = stesse parole.
     * Serve a decidere se una domanda imparata è davvero nuova o solo una
     * variante di una domanda iniziale.
     */
    public static function similarity(string $a, string $b): float
    {
        $x = $a === '' ? [] : explode(' ', $a);
        $y = $b === '' ? [] : explode(' ', $b);
        $union = count(array_unique(array_merge($x, $y)));

        return $union === 0 ? 1.0 : count(array_intersect(array_unique($x), array_unique($y))) / $union;
    }
}
