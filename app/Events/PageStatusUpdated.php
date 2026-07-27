<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $pageId,
        public string $status,
        public int $updatedByUserId,
        public string $updatedByUserName,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'PageStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'pageId' => $this->pageId,
            'status' => $this->status,
            'updatedByUserId' => $this->updatedByUserId,
            'updatedByUserName' => $this->updatedByUserName,
        ];
    }
}
