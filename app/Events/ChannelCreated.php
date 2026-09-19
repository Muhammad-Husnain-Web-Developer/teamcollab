<?php

namespace App\Events;

use App\Models\Tenant\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChannelCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Channel $channel;
    public string $tenantId;

    public function __construct(Channel $channel, string $tenantId)
    {
        $this->channel  = $channel;
        $this->tenantId = $tenantId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.workspace"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'channel.created';
    }

    public function broadcastWith(): array
    {
        return [
            'channel' => [
                'id'              => $this->channel->id,
                'name'            => $this->channel->name,
                'slug'            => $this->channel->slug,
                'description'     => $this->channel->description,
                'type'            => $this->channel->type,
                'topic'           => $this->channel->topic,
                'is_archived'     => $this->channel->is_archived,
                'is_read_only'    => $this->channel->is_read_only,
                'is_default'      => $this->channel->is_default,
                'icon'            => $this->channel->icon,
                'color'           => $this->channel->color,
                'members_count'   => $this->channel->members_count,
                'messages_count'  => $this->channel->messages_count,
                'created_by'      => $this->channel->created_by,
                'created_at'      => $this->channel->created_at?->toIso8601String(),
            ],
            'tenant_id' => $this->tenantId,
        ];
    }
}
