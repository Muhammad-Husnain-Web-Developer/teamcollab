<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone was added to a channel by an admin.
 *
 * Distinct from MemberJoined, which is a workspace-level event. This one
 * carries the member payload so the open members panel can insert the row
 * without refetching.
 */
class ChannelMemberAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $channelId;
    public array $member;
    public int $actorId;
    public string $tenantId;

    public function __construct(int $channelId, array $member, int $actorId, string $tenantId)
    {
        $this->channelId = $channelId;
        $this->member    = $member;
        $this->actorId   = $actorId;
        $this->tenantId  = $tenantId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.channel.{$this->channelId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'member.added';
    }

    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->channelId,
            'member'     => $this->member,
            // Lets the actor's own client skip the update it already applied.
            'actor_id'   => $this->actorId,
        ];
    }
}
