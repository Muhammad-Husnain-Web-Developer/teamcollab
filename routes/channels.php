<?php

use App\Models\Tenant\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Per-user private notification channel
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

// -------------------------------------------------------------------------
// Tenant workspace broadcast channel (private)
// Used for workspace-level events: member joins, settings changes, etc.
// -------------------------------------------------------------------------
Broadcast::channel('tenant.{tenantId}.workspace', function (User $user, string $tenantId) {
    return \DB::table('tenant_users')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $user->id)
        ->exists();
});

// -------------------------------------------------------------------------
// Tenant channel-specific broadcast channel (private)
// Used for: new messages, typing indicators, reactions, pins, etc.
// -------------------------------------------------------------------------
Broadcast::channel('tenant.{tenantId}.channel.{channelId}', function (User $user, string $tenantId, string $channelId) {
    $belongsToTenant = \DB::table('tenant_users')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $user->id)
        ->exists();

    if (! $belongsToTenant) {
        return false;
    }

    // For public channels: any tenant member can subscribe.
    // For private channels: only explicit channel members can subscribe.
    try {
        $channel = Channel::find($channelId);

        if (! $channel) {
            return false;
        }

        if ($channel->type === 'public') {
            return true;
        }

        return $channel->members()->where('users.id', $user->id)->exists();
    } catch (\Exception $e) {
        // Tenancy context may not be initialized in this callback; fall back
        // to tenant membership check only.
        return $belongsToTenant;
    }
});

// -------------------------------------------------------------------------
// Tenant presence channel
// Used for: online/offline status, typing indicators, live user list.
// Returns user data object so Pusher/Reverb includes it in presence set.
// -------------------------------------------------------------------------
Broadcast::channel('presence-tenant.{tenantId}', function (User $user, string $tenantId) {
    $belongsToTenant = \DB::table('tenant_users')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $user->id)
        ->exists();

    if (! $belongsToTenant) {
        return false;
    }

    return [
        'id'           => $user->id,
        'name'         => $user->name,
        'display_name' => $user->display_name,
        'avatar_url'   => $user->avatar_url,
        'status'       => $user->status ?? 'online',
    ];
});

// -------------------------------------------------------------------------
// Direct message conversation channel (private)
// -------------------------------------------------------------------------
Broadcast::channel('tenant.{tenantId}.dm.{conversationId}', function (User $user, string $tenantId, string $conversationId) {
    $belongsToTenant = \DB::connection('mysql')->table('tenant_users')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $user->id)
        ->exists();

    if (! $belongsToTenant) {
        return false;
    }

    try {
        // Initialize tenancy so ConversationParticipant queries hit the tenant DB
        $tenant = \App\Models\Tenant::find($tenantId);
        if ($tenant) {
            tenancy()->initialize($tenant);
        }

        return \App\Models\Tenant\ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->exists();
    } catch (\Exception $e) {
        // Fall back to tenant-membership check if tenant DB is unavailable
        return $belongsToTenant;
    }
});
