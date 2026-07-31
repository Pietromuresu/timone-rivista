<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fase 5: spostamento di un intero blocco di pagine selezionate come unità
 * unica — un solo evento per l'intera operazione (non N eventi, uno per
 * pagina spostata), richiesta esplicita della fase per evitare che gli
 * altri utenti collegati vedano stati intermedi inconsistenti mentre il
 * blocco si sposta pezzo per pezzo.
 */
class PagesBlockMoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $pageIds
     */
    public function __construct(
        public int $issueId,
        public array $pageIds,
        public int $movedByUserId,
        public string $movedByUserName,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'PagesBlockMoved';
    }

    public function broadcastWith(): array
    {
        return [
            'pageIds' => $this->pageIds,
            'movedByUserId' => $this->movedByUserId,
            'movedByUserName' => $this->movedByUserName,
        ];
    }
}
