<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * An invitee declined. `callEnded` is set when that decline leaves nobody
 * for the caller to talk to (the 1-on-1 "they hung up on me" case).
 *
 * @param  int[]  $recipientIds
 */
class CallRejected implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $callId,
        public readonly int    $userId,
        public readonly bool   $callEnded,
        public readonly array  $recipientIds,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(
            fn (int $id) => new PrivateChannel('App.Models.User.' . $id),
            $this->recipientIds,
        );
    }

    public function broadcastAs(): string { return 'call.rejected'; }

    public function broadcastWith(): array
    {
        return [
            'callId'    => $this->callId,
            'userId'    => $this->userId,
            'callEnded' => $this->callEnded,
        ];
    }
}
