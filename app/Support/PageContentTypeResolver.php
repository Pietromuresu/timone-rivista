<?php

namespace App\Support;

use App\Enums\ContentType;
use App\Enums\PageContentType;

/**
 * Deriva il "tipo pagina" (editoriale/pubblicità/mista/bianca — il colore
 * della card nel timone) dai tipi dei contenuti effettivamente assegnati.
 * Estratta come classe pura (stesso pattern di PageReorderer/AdLoadCalculator
 * ecc.) perché va richiamata da più punti di Grid.php ogni volta che
 * l'insieme dei contenuti di una pagina cambia — assegnazione, rimozione,
 * estensione multipagina — e deve restare in sincrono in tutti.
 */
class PageContentTypeResolver
{
    /**
     * @param  list<ContentType|string>  $contentTypes  i tipi dei contenuti assegnati alla pagina, in una qualunque delle due forme: stringa grezza ('articolo'/'pubblicita') o istanza dell'enum ContentType.
     */
    public static function resolve(array $contentTypes): PageContentType
    {
        // ContentType|string, non solo string: scoperto un bug reale usando
        // ->pluck('type') su una relazione Eloquent — a differenza del query
        // builder puro, Eloquent applica comunque il cast del modello anche
        // dentro pluck(), restituendo istanze di ContentType invece della
        // stringa grezza della colonna. Il confronto con === contro una
        // stringa falliva sempre senza errori, cadendo silenziosamente sul
        // ramo sbagliato — normalizzato qui una volta per tutte, invece di
        // dover ricordare di fare .value ad ogni chiamata.
        $values = array_map(
            fn (ContentType|string $type) => $type instanceof ContentType ? $type->value : $type,
            $contentTypes,
        );

        $unique = array_values(array_unique($values));

        return match (true) {
            $unique === [] => PageContentType::Bianca,
            count($unique) > 1 => PageContentType::Mista,
            $unique[0] === ContentType::Articolo->value => PageContentType::Editoriale,
            default => PageContentType::Pubblicita,
        };
    }
}
