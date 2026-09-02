<?php

namespace App\Support;

use App\Enums\AdMaterialStatus;
use App\Models\Page;
use Illuminate\Support\Collection;

/**
 * Deriva lo stato del materiale di una pubblicità (Fase 3, §3) dalle pagine
 * a cui è assegnata — puro/testabile come le altre classi App\Support
 * (PageReorderer, AdLoadCalculator, ...): nessuna query propria, opera solo
 * sulla collection di pagine già caricata dal chiamante (con 'files' E
 * 'contents.files' eager-loaded per intero, non limitati all'ultimo come
 * in Grid::render() — vedi AdReservations::issueAdvertisements()).
 *
 * Bug reale trovato durante il Punto 4 (2026-09-02): guardava solo
 * $page->files (PageFile diretto) — con il PDF che si carica ora solo su
 * un contenuto, una pubblicità con materiale caricato col nuovo flusso non
 * sarebbe mai risultata "Completo", restando bloccata su "Assegnato" a
 * vita nel cruscotto. Page::previewFile() copre entrambi i casi (contenuto
 * assegnato con file, o PageFile legacy non ancora migrato).
 */
class AdMaterialStatusResolver
{
    /**
     * @param  Collection<int, Page>  $assignedPages  Le pagine a cui il Content pubblicitario è assegnato (Content::pages())
     */
    public static function resolve(Collection $assignedPages): AdMaterialStatus
    {
        if ($assignedPages->isEmpty()) {
            return AdMaterialStatus::Prenotato;
        }

        $everyPageComplete = $assignedPages->every(function (Page $page) {
            $file = $page->previewFile();

            return $file !== null && ! $file->hasUnresolvedFormatMismatch();
        });

        return $everyPageComplete ? AdMaterialStatus::Completo : AdMaterialStatus::Assegnato;
    }
}
