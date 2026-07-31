<?php

namespace App\Support;

use App\Enums\AdMaterialStatus;
use App\Models\Page;
use Illuminate\Support\Collection;

/**
 * Deriva lo stato del materiale di una pubblicità (Fase 3, §3) dalle pagine
 * a cui è assegnata — puro/testabile come le altre classi App\Support
 * (PageReorderer, AdLoadCalculator, ...): nessuna query propria, opera solo
 * sulla collection di pagine già caricata dal chiamante (con la relazione
 * "files" completa, non limitata all'ultimo file come in Grid::render()).
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
            $latestFile = $page->files->sortByDesc('created_at')->first();

            return $latestFile !== null && ! $latestFile->hasUnresolvedFormatMismatch();
        });

        return $everyPageComplete ? AdMaterialStatus::Completo : AdMaterialStatus::Assegnato;
    }
}
