<?php

namespace App\Events;

use App\Models\Tenant\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageLinkPreviewReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;
    public string $tenantId;

    public function __construct(Message $message, string $tenantId)
    {
        $this->message  = $message;
        $this->tenantId = $tenantId;
    }

    public function broadcastOn(): array
    {
        $channel = $this->message->channel_id
            ? "tenant.{$this->tenantId}.channel.{$this->message->channel_id}"
            : "tenant.{$this->tenantId}.dm.{$this->message->conversation_id}";

        return [new PrivateChannel($channel)];
    }

    public function broadcastAs(): string
    {
        return 'message.preview.ready';
    }

    /**
     * Shaped to match `.message.updated`'s payload so the client can patch
     * the message with the same generic updateMessage() merge.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id'       => $this->message->id,
                'metadata' => $this->message->metadata,
            ],
        ];
    }
}
