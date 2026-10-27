<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * L'ordine dei contenuti dentro una pagina è cambiato (Fase 7b,
 * 2026-10-27, frecce sul blocco). Corpo vuoto lato listener
 * (Grid::onPageContentsReordered()): il prossimo render() ridisegna la
 * disposizione dal database, come per tutti gli altri eventi del timone.
 */
class PageContentsReordered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $pageId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'PageContentsReordered';
    }

    public function broadcastWith(): array
    {
        return ['pageId' => $this->pageId];
    }
}
