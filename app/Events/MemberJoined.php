<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public string $tenantId;

    public function __construct(User $user, string $tenantId)
    {
        $this->user     = $user;
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
        return 'member.joined';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id'           => $this->user->id,
                'name'         => $this->user->name,
                'display_name' => $this->user->display_name,
                'email'        => $this->user->email,
                'avatar'       => $this->user->avatar_url,
                'status'       => $this->user->status,
                'status_emoji' => $this->user->status_emoji,
                'status_text'  => $this->user->status_text,
                'timezone'     => $this->user->timezone,
            ],
            'tenant_id' => $this->tenantId,
        ];
    }
}
