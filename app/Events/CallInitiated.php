<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CallInitiated implements ShouldBroadcastNow
{
    /**
     * @param  array<string, mixed>  $caller  {id, name, display_name, avatar_url}
     * @param  array<int, array<string, mixed>>  $participants  full roster snapshot, each {id, name, display_name, avatar_url, status}
     * @param  int[]  $recipientIds  the invitees
     */
    public function __construct(
        public readonly string $callId,
        public readonly array  $caller,
        public readonly string $callType,
        public readonly array  $participants,
        public readonly array  $recipientIds,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(
            fn (int $id) => new PrivateChannel('App.Models.User.' . $id),
            $this->recipientIds,
        );
    }

    public function broadcastAs(): string { return 'call.initiated'; }

    public function broadcastWith(): array
    {
        return [
            'callId'       => $this->callId,
            'caller'       => $this->caller,
            'callType'     => $this->callType,
            'participants' => $this->participants,
        ];
    }
}
