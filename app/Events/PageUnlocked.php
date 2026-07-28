<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PageUnlocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $pageId,
        public int $unlockedByUserId,
        public string $unlockedByUserName,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'PageUnlocked';
    }

    public function broadcastWith(): array
    {
        return [
            'pageId' => $this->pageId,
            'unlockedByUserId' => $this->unlockedByUserId,
            'unlockedByUserName' => $this->unlockedByUserName,
        ];
    }
}
