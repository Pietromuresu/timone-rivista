<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $contentId,
        public string $title,
        public int $createdByUserId,
        public string $createdByUserName,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'ContentCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'contentId' => $this->contentId,
            'title' => $this->title,
            'createdByUserId' => $this->createdByUserId,
            'createdByUserName' => $this->createdByUserName,
        ];
    }
}
