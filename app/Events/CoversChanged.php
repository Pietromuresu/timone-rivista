<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fase 2 (2026-10-26): le copertine di un numero sono cambiate — due slot
 * scambiati, bandella aggiunta o tolta. Ascoltato da Grid (ridisegna la
 * fascia copertine) e da WeightPanel (la bandella cambia le pagine di
 * copertina del peso). Le modifiche al CONTENUTO di una copertina
 * (assegnazioni, stato, blocco) usano gli eventi di sempre: una copertina
 * è una riga di `pages`. ShouldBroadcastNow come gli altri eventi del
 * timone (vedi HANDOFF.md, PageMoved).
 */
class CoversChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  'swapped'|'bandella_added'|'bandella_removed'  $action
     */
    public function __construct(
        public int $issueId,
        public string $action,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'CoversChanged';
    }

    public function broadcastWith(): array
    {
        return ['action' => $this->action];
    }
}
