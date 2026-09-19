<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class WebRtcSignal implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $callId,
        public readonly int    $targetUserId,
        public readonly array  $signal,
        public readonly int    $fromUserId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->targetUserId)];
    }

    public function broadcastAs(): string { return 'webrtc.signal'; }

    public function broadcastWith(): array
    {
        return [
            'callId'     => $this->callId,
            'signal'     => $this->signal,
            'fromUserId' => $this->fromUserId,
        ];
    }
}
