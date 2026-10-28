<?php

namespace App\Policies;

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Issue;
use App\Models\User;
use App\Support\Permissions\TimoneAbilities;

/**
 * Permessi su un singolo contenuto (revisione 2, Fase 4.1, 2026-10-28).
 * Prima ogni azione controllava solo `update` sul numero (Admin/Redattore);
 * ora il Commerciale può lavorare sulle pubblicità ma non sugli articoli,
 * quindi la decisione dipende dal contenuto. Regole in TimoneAbilities.
 */
class ContentPolicy
{
    /** Crea un contenuto di questo tipo nel numero (modulo, caricamento multiplo, cambio di tipo). */
    public function create(User $user, Issue $issue, ContentType|string $type): bool
    {
        return TimoneAbilities::for($user, $issue)->canChangeContentOfType($type);
    }

    /** Modifica campi, file, spazio occupato; archivia, elimina se non assegnato. */
    public function update(User $user, Content $content): bool
    {
        return TimoneAbilities::for($user, $content->issue)->canChangeContent($content);
    }

    /**
     * Sposta: assegna a una pagina, frecce e rotazione sulla griglia,
     * estende, toglie dalla pagina, cambia pagina dalla modale. Oggi
     * coincide con update(); separato perché è il permesso chiesto dalla
     * specifica e può divergere senza toccare i chiamanti.
     */
    public function move(User $user, Content $content): bool
    {
        return $this->update($user, $content);
    }
}
