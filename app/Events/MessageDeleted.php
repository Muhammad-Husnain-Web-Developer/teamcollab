<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Carries plain IDs rather than the model: the row is soft-deleted by the
 * time this is serialized for the queue, and the client only needs to know
 * which message vanished (and which thread parent lost a reply).
 */
class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int     $messageId,
        public ?int    $parentId,
        public ?int    $channelId,
        public ?int    $conversationId,
        public string  $tenantId,
    ) {}

    public function broadcastOn(): array
    {
        $channel = $this->channelId
            ? "tenant.{$this->tenantId}.channel.{$this->channelId}"
            : "tenant.{$this->tenantId}.dm.{$this->conversationId}";

        return [new PrivateChannel($channel)];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'messageId' => $this->messageId,
            'parent_id' => $this->parentId,
        ];
    }
}
