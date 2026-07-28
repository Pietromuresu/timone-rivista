<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PageLocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $pageId,
        public int $lockedByUserId,
        public string $lockedByUserName,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'PageLocked';
    }

    public function broadcastWith(): array
    {
        return [
            'pageId' => $this->pageId,
            'lockedByUserId' => $this->lockedByUserId,
            'lockedByUserName' => $this->lockedByUserName,
        ];
    }
}
