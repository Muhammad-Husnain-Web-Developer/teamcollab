<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Consent notice: recording happens entirely on the recorder's own machine
 * (nothing is captured server-side), but everyone else in the call should
 * still be told when it starts and stops.
 *
 * @param  int[]  $recipientIds
 */
class CallRecordingToggled implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $callId,
        public readonly int    $userId,
        public readonly bool   $recording,
        public readonly array  $recipientIds,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(
            fn (int $id) => new PrivateChannel('App.Models.User.' . $id),
            $this->recipientIds,
        );
    }

    public function broadcastAs(): string { return 'call.recording'; }

    public function broadcastWith(): array
    {
        return [
            'callId'    => $this->callId,
            'userId'    => $this->userId,
            'recording' => $this->recording,
        ];
    }
}
