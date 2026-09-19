<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\Tenant\Channel;
use App\Models\Tenant\WorkspaceSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CreateTenantDefaultChannelsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $ownerId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            Log::warning("CreateTenantDefaultChannelsJob: Tenant {$this->tenantId} not found.");
            return;
        }

        // Run inside the tenant context so queries hit the tenant DB
        tenancy()->initialize($tenant);

        $this->createDefaultChannels();
        $this->createWorkspaceSettings();

        tenancy()->end();
    }

    private function createDefaultChannels(): void
    {
        $defaults = [
            [
                'name'        => 'general',
                'slug'        => 'general',
                'description' => 'General conversation for the whole workspace.',
                'type'        => 'public',
                'is_default'  => true,
                'is_archived' => false,
                'is_read_only' => false,
                'icon'        => '#',
                'color'       => '#5c7cfa',
                'created_by'  => $this->ownerId,
            ],
            [
                'name'        => 'announcements',
                'slug'        => 'announcements',
                'description' => 'Important announcements for everyone.',
                'type'        => 'public',
                'is_default'  => true,
                'is_archived' => false,
                'is_read_only' => true,
                'icon'        => '📢',
                'color'       => '#f76707',
                'created_by'  => $this->ownerId,
            ],
            [
                'name'        => 'random',
                'slug'        => 'random',
                'description' => 'A place for non-work-related chat.',
                'type'        => 'public',
                'is_default'  => false,
                'is_archived' => false,
                'is_read_only' => false,
                'icon'        => '🎲',
                'color'       => '#37b24d',
                'created_by'  => $this->ownerId,
            ],
        ];

        foreach ($defaults as $data) {
            if (! Channel::where('slug', $data['slug'])->exists()) {
                $channel = Channel::create($data);

                // Add owner as admin member of each default channel
                $channel->channelMembers()->create([
                    'user_id'   => $this->ownerId,
                    'role'      => 'admin',
                    'is_muted'  => false,
                    'joined_at' => now(),
                ]);
            }
        }
    }

    private function createWorkspaceSettings(): void
    {
        if (WorkspaceSetting::exists()) {
            return;
        }

        WorkspaceSetting::create([
            'allow_guest_access'        => false,
            'default_channel_id'        => null,
            'message_retention_days'    => 365,
            'file_storage_limit_mb'     => 5120,
            'allowed_file_types'        => json_encode(['image/*', 'application/pdf', 'text/*', 'video/*', 'audio/*']),
            'max_file_size_mb'          => 25,
            'enable_emoji_reactions'    => true,
            'enable_thread_replies'     => true,
            'enable_direct_messages'    => true,
            'require_email_verification'=> false,
        ]);
    }
}
