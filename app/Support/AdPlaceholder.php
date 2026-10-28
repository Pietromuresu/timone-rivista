<?php

namespace App\Support;

use App\Enums\ContentType;
use App\Models\Content;

/**
 * Unico punto della regola "pubblicità segnaposto" (revisione 2, Fase 3.1,
 * 2026-10-28) e del suo testo automatico.
 *
 * Regola: una pubblicità è segnaposto finché non ha né cliente né file;
 * appena ne ha uno dei due è una pubblicità vera (e torna segnaposto se
 * li perde entrambi). Vale allo stesso modo per i segnaposto nati dalle
 * "pubblicità previste" del numero (PlaceholderAdCreator) e per quelli
 * creati dal modulo con il cliente vuoto. Prima (Fase 5) la regola era
 * sparsa in tre punti (ContentUpdater, Grid::renameContent, la creazione)
 * e anche un titolo diverso da quello automatico rendeva "vera" la
 * pubblicità: ora il titolo non conta.
 *
 * La colonna contents.is_placeholder resta come valore MEMORIZZATO della
 * regola (filtri SQL, cruscotto senza caricare file e inserzionista per
 * ogni contenuto): la scrive solo sync(), chiamata da ogni percorso che
 * cambia tipo, cliente o file di un contenuto.
 */
final class AdPlaceholder
{
    /** Separatore tra misura e stato nel titolo automatico (letto anche dall'export). */
    public const TITLE_SUFFIX = ' — da assegnare';

    /** La regola, sui dati grezzi (usata anche prima che il contenuto esista). */
    public static function applies(ContentType|string $type, ?string $client, bool $hasFile): bool
    {
        $type = $type instanceof ContentType ? $type : ContentType::from($type);

        return $type === ContentType::Pubblicita
            && trim((string) $client) === ''
            && ! $hasFile;
    }

    /** La regola applicata a un contenuto salvato (cliente e file dal database). */
    public static function appliesTo(Content $content): bool
    {
        return self::applies(
            $content->type,
            $content->advertisement()->value('client'),
            $content->files()->exists(),
        );
    }

    /** Riallinea contents.is_placeholder alla regola; true se è cambiato. */
    public static function sync(Content $content): bool
    {
        $placeholder = self::appliesTo($content);

        if ($content->is_placeholder === $placeholder) {
            return false;
        }

        $content->update(['is_placeholder' => $placeholder]);

        return true;
    }

    /**
     * Titolo automatico «Pubblicità 1/2 — da assegnare», dall'occupazione
     * in dodicesimi: lo stesso per la creazione del numero
     * (PlannedAdKind::placeholderTitle()) e per il modulo quando il titolo
     * di una pubblicità resta vuoto. Oltre la pagina usa le pagine
     * («Pubblicità 2 pagine — da assegnare»).
     */
    public static function title(int $twelfths): string
    {
        $size = match (true) {
            $twelfths === Twelfths::PER_PAGE => 'pagina intera',
            $twelfths < Twelfths::PER_PAGE => Twelfths::fractionLabel($twelfths),
            default => Twelfths::pagesLabel($twelfths),
        };

        return "Pubblicità {$size}".self::TITLE_SUFFIX;
    }

    /**
     * Titolo generato da title() (non scritto da qualcuno): quando cambia
     * l'occupazione di una pubblicità con il titolo automatico, il titolo
     * si rigenera invece di restare con la misura vecchia.
     */
    public static function isAutomaticTitle(?string $title): bool
    {
        return $title !== null && str_starts_with($title, 'Pubblicità ') && str_ends_with($title, self::TITLE_SUFFIX);
    }

    /**
     * Titolo da salvare per una pubblicità: quello scritto, oppure
     * l'automatico se è vuoto (o se era automatico e l'occupazione è
     * cambiata). Per un articolo il titolo è obbligatorio e resta com'è.
     */
    public static function resolveTitle(ContentType|string $type, ?string $title, int $twelfths): string
    {
        $type = $type instanceof ContentType ? $type : ContentType::from($type);
        $title = trim((string) $title);

        if ($type === ContentType::Pubblicita && ($title === '' || self::isAutomaticTitle($title))) {
            return self::title($twelfths);
        }

        return $title;
    }
}
