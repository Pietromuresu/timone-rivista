<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un gruppo di pagine collegate è stato creato o sciolto (Fase 2.3).
 * Corpo vuoto lato listener (Grid::onPageGroupsChanged()): il prossimo
 * render() ripesca già la relazione 'group' su ogni pagina, stesso pattern
 * di tutti gli altri eventi broadcast di questo progetto.
 */
class PageGroupsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'PageGroupsChanged';
    }
}
