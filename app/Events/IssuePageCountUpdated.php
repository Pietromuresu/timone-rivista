<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssuePageCountUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $oldTotalPages,
        public int $newTotalPages,
        public int $changedByUserId,
        public string $changedByUserName,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'IssuePageCountUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'oldTotalPages' => $this->oldTotalPages,
            'newTotalPages' => $this->newTotalPages,
            'changedByUserId' => $this->changedByUserId,
            'changedByUserName' => $this->changedByUserName,
        ];
    }
}
