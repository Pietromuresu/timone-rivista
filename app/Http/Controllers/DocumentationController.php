<?php

namespace App\Http\Controllers;

use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Fase 6.1: la fonte di verità resta MANUALE_UTENTE.md (come richiesto dal
 * prompt — "riusa MANUALE_UTENTE.md come fonte di contenuto se già
 * organizzato per task, invece di riscriverlo da zero"), qui solo convertito
 * in HTML per essere consultabile senza uscire dall'app. `league/commonmark`
 * era già una dipendenza transitiva presente in vendor/ (non aggiunta da
 * questa fase) — nessun nuovo pacchetto Composer necessario. Nessun
 * componente Livewire: la pagina è puramente di sola lettura, senza alcuna
 * interazione wire: — stesso trattamento già riservato a
 * PageFileController/ContentFileController per contenuto servito e basta.
 */
class DocumentationController extends Controller
{
    public function show()
    {
        $markdown = file_get_contents(base_path('MANUALE_UTENTE.md'));

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => '',
                'insert' => 'before',
                'symbol' => '#',
                'title' => 'Link a questa sezione',
            ],
        ]);
        $converter->getEnvironment()->addExtension(new HeadingPermalinkExtension);

        return view('documentation.show', [
            'contentHtml' => (string) $converter->convert($markdown),
        ]);
    }
}
