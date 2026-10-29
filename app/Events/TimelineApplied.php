<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Annulla/ripeti (2026-10-28): un utente ha annullato o ripetuto un passo.
 * Gli altri ridisegnano il timone e vedono il toast «Mario — Annullato: …»;
 * senza Reverb basta pages_updated_at (già toccato) al poll successivo.
 */
class TimelineApplied implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $issueId, public string $userName, public string $message) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'TimelineApplied';
    }

    public function broadcastWith(): array
    {
        return ['userName' => $this->userName, 'message' => $this->message];
    }
}
