<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Stesso stampo di PageFileUploaded (Punto 1bis): trasmesso senza
 * ->toOthers() apposta, perché anche l'utente che ha caricato il file deve
 * vedere la propria card passare da "in elaborazione" a "pronta" senza
 * refresh (Fase 1.1) — non solo gli altri utenti collegati.
 */
class ContentFileUploaded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $issueId,
        public int $contentId,
        public int $contentFileId,
        public string $thumbnailStatus,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('issue.'.$this->issueId);
    }

    public function broadcastAs(): string
    {
        return 'ContentFileUploaded';
    }

    public function broadcastWith(): array
    {
        return [
            'contentId' => $this->contentId,
            'contentFileId' => $this->contentFileId,
            'thumbnailStatus' => $this->thumbnailStatus,
        ];
    }
}
