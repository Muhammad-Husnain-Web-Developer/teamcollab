<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Someone joined the call. Sent to every other reachable participant so they
 * can add a tile and expect a WebRTC offer from the joiner (the joiner always
 * offers, existing participants only answer — that is what keeps a mesh
 * glare-free without any extra negotiation).
 */
class CallParticipantJoined implements ShouldBroadcastNow
{
    /**
     * @param  array<string, mixed>  $participant  {id, name, display_name, avatar_url}
     * @param  int[]  $recipientIds
     */
    public function __construct(
        public readonly string $callId,
        public readonly array  $participant,
        public readonly array  $recipientIds,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(
            fn (int $id) => new PrivateChannel('App.Models.User.' . $id),
            $this->recipientIds,
        );
    }

    public function broadcastAs(): string { return 'call.participant.joined'; }

    public function broadcastWith(): array
    {
        return ['callId' => $this->callId, 'participant' => $this->participant];
    }
}
