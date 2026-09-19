<?php

namespace App\Events;

use App\Models\Tenant\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DmMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;
    public int $conversationId;
    public string $tenantId;

    public function __construct(Message $message, string $tenantId)
    {
        $this->message        = $message;
        $this->conversationId = $message->conversation_id;
        $this->tenantId       = $tenantId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.dm.{$this->conversationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $message = $this->message;

        return [
            'message' => [
                'id'              => $message->id,
                'body'            => $message->body,
                'conversation_id' => $message->conversation_id,
                'user_id'         => $message->user_id,
                'type'            => $message->type ?? 'text',
                'is_edited'       => $message->is_edited,
                'metadata'        => $message->metadata,
                'created_at'      => $message->created_at?->toIso8601String(),
                'user'            => $message->relationLoaded('user') ? [
                    'id'           => $message->user_id,
                    'name'         => optional($message->getRelation('user'))->name,
                    'display_name' => optional($message->getRelation('user'))->display_name,
                    'avatar_url'   => optional($message->getRelation('user'))->avatar_url,
                ] : ['id' => $message->user_id],
                'reactions'       => $message->grouped_reactions ?? [],
                'parent'          => $message->relationLoaded('parent') && $message->parent ? [
                    'id'   => $message->parent->id,
                    'body' => \Illuminate\Support\Str::limit($message->parent->body ?? '', 120),
                    'user' => [
                        'id'           => $message->parent->user?->id,
                        'name'         => $message->parent->user?->name,
                        'display_name' => $message->parent->user?->display_name,
                    ],
                ] : null,
                'files'           => $message->relationLoaded('files')
                    ? $message->files->map(fn ($f) => [
                        'id'            => $f->id,
                        'original_name' => $f->original_name,
                        'type'          => $f->type,
                        'extension'     => $f->extension,
                        'mime_type'     => $f->mime_type,
                        'size'          => $f->size,
                        'size_human'    => $f->size_human,
                        'view_url'      => $f->view_url,
                        'download_url'  => $f->download_url,
                    ])->toArray()
                    : [],
            ],
            'conversation_id' => $this->conversationId,
            'tenant_id'       => $this->tenantId,
        ];
    }
}
