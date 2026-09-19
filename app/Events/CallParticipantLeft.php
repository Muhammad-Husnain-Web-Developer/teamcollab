<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Someone left (or the caller cancelled before anyone answered). Sent to every
 * other reachable participant — joined ones drop that peer, still-ringing
 * invitees dismiss their toast when `callEnded` is set.
 */
class CallParticipantLeft implements ShouldBroadcastNow
{
    /**
     * @param  int[]  $recipientIds
     */
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

    public function broadcastAs(): string { return 'call.participant.left'; }

    public function broadcastWith(): array
    {
        return [
            'callId'    => $this->callId,
            'userId'    => $this->userId,
            'callEnded' => $this->callEnded,
        ];
    }
}
