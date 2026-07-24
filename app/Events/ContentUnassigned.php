<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentUnassigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $pageId,
        public int $contentId,
        public int $unassignedByUserId,
        public string $unassignedByUserName,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'ContentUnassigned';
    }

    public function broadcastWith(): array
    {
        return [
            'pageId' => $this->pageId,
            'contentId' => $this->contentId,
            'unassignedByUserId' => $this->unassignedByUserId,
            'unassignedByUserName' => $this->unassignedByUserName,
        ];
    }
}
