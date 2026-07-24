<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PageMoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $pageId,
        public int $fromPosition,
        public int $toPosition,
        public int $movedByUserId,
        public string $movedByUserName,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'PageMoved';
    }

    public function broadcastWith(): array
    {
        return [
            'pageId' => $this->pageId,
            'fromPosition' => $this->fromPosition,
            'toPosition' => $this->toPosition,
            'movedByUserId' => $this->movedByUserId,
            'movedByUserName' => $this->movedByUserName,
        ];
    }
}
