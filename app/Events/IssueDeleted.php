<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Revisione 2, Fase 4.2 (2026-10-28): il numero è stato eliminato da un
 * admin. Chi ha il timone aperto con Reverb attivo lo riceve subito; il
 * listener di Grid fa una richiesta Livewire che trova il numero
 * scomparso (404) e il browser riporta all'elenco con un avviso
 * (resources/js/timone-alpine.js, hook su `request`). Senza Reverb lo
 * stesso succede al poll successivo. Nessun dato nel payload: il numero non
 * esiste più. Ad ogni destinatario (non toOthers): l'admin che elimina non
 * ha il timone aperto, è sulla pagina della rivista.
 */
class IssueDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $issueId) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'IssueDeleted';
    }
}
