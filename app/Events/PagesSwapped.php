<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PagesSwapped implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $pageIdA,
        public int $pageIdB,
        public int $swappedByUserId,
        public string $swappedByUserName,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'PagesSwapped';
    }

    public function broadcastWith(): array
    {
        return [
            'pageIdA' => $this->pageIdA,
            'pageIdB' => $this->pageIdB,
            'swappedByUserId' => $this->swappedByUserId,
            'swappedByUserName' => $this->swappedByUserName,
        ];
    }
}
