<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberLeft implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $channelId;
    public int $userId;
    public string $userName;
    public int $actorId;
    public string $tenantId;

    /**
     * $userId is who left the channel; $actorId is who caused it — the same
     * person when they leave themselves, an admin when they were removed.
     */
    public function __construct(
        int $channelId,
        int $userId,
        string $userName,
        string $tenantId,
        ?int $actorId = null,
    ) {
        $this->channelId = $channelId;
        $this->userId    = $userId;
        $this->userName  = $userName;
        $this->tenantId  = $tenantId;
        $this->actorId   = $actorId ?? $userId;
    }

    public function broadcastOn(): array
    {
        // Broadcast to the channel itself so remaining members can update their
        // member list without a refresh.
        return [
            new PrivateChannel("tenant.{$this->tenantId}.channel.{$this->channelId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'member.left';
    }

    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->channelId,
            'user_id'    => $this->userId,
            'user_name'  => $this->userName,
            // Lets the actor's own client skip the update it already applied.
            'actor_id'   => $this->actorId,
        ];
    }
}
