<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fase 1, bug 3 (2026-10-26): un contenuto NON assegnato è stato archiviato,
 * ripristinato o eliminato. Prima queste azioni non avvisavano nessuno —
 * né via Reverb né toccando `issues.pages_updated_at` — quindi gli altri
 * utenti continuavano a vedere (e a poter trascinare) un contenuto già
 * eliminato finché non ricaricavano la pagina. Un solo evento per le tre
 * azioni: per chi lo riceve conta solo "la lista dei contenuti è cambiata,
 * ripesca dal database", l'azione serve solo al testo del toast.
 * ShouldBroadcastNow per lo stesso motivo di tutti gli altri eventi del
 * timone (vedi HANDOFF.md, PageMoved).
 */
class ContentListChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  'archived'|'unarchived'|'deleted'  $action
     */
    public function __construct(
        public int $issueId,
        public int $contentId,
        public string $action,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'ContentListChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'contentId' => $this->contentId,
            'action' => $this->action,
        ];
    }
}
