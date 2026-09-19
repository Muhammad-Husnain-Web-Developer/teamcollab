<?php

namespace App\Services;

use App\Events\ChannelCreated;
use App\Events\MemberJoined;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChannelService
{
    /**
     * Create a new channel and add the creating user as admin.
     */
    public function create(array $data, User $user): Channel
    {
        $channel = DB::transaction(function () use ($data, $user): Channel {
            $slug = Str::slug($data['name']);

            // Ensure slug uniqueness within the tenant DB
            $baseSlug = $slug;
            $counter  = 1;
            while (Channel::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            /** @var Channel $channel */
            $channel = Channel::create([
                'name'          => $data['name'],
                'slug'          => $slug,
                'description'   => $data['description'] ?? null,
                'type'          => $data['type'] ?? 'public',
                'topic'         => $data['topic'] ?? null,
                'is_archived'   => false,
                'is_read_only'  => $data['is_read_only'] ?? false,
                'is_default'    => $data['is_default'] ?? false,
                'icon'          => $data['icon'] ?? null,
                'color'         => $data['color'] ?? null,
                'created_by'    => $user->id,
                'members_count' => 1,
                'messages_count'=> 0,
            ]);

            // Add creator as admin member
            ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id'    => $user->id,
                'role'       => 'admin',
                'is_muted'   => false,
                'joined_at'  => now(),
            ]);

            return $channel;
        });

        // Fire event OUTSIDE the transaction so broadcast failure never
        // rolls back the created channel.
        try {
            event(new ChannelCreated($channel, tenant('id')));
        } catch (\Throwable) {
            // Non-fatal — channel is already persisted.
        }

        return $channel;
    }

    /**
     * Update channel attributes.
     */
    public function update(Channel $channel, array $data): Channel
    {
        $fillable = [
            'name', 'description', 'topic', 'type',
            'is_read_only', 'icon', 'color',
        ];

        $updateData = array_intersect_key($data, array_flip($fillable));

        // Re-slug if name changed
        if (isset($updateData['name']) && $updateData['name'] !== $channel->name) {
            $slug     = Str::slug($updateData['name']);
            $baseSlug = $slug;
            $counter  = 1;
            while (Channel::where('slug', $slug)->where('id', '!=', $channel->id)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
            $updateData['slug'] = $slug;
        }

        $channel->update($updateData);

        return $channel->fresh();
    }

    /**
     * Soft-delete a channel and all its messages.
     */
    public function delete(Channel $channel): void
    {
        DB::transaction(function () use ($channel): void {
            $channel->messages()->delete();
            $channel->channelMembers()->delete();
            $channel->delete();
        });
    }

    /**
     * Archive a channel (marks it inactive, not deleted).
     */
    public function archive(Channel $channel): void
    {
        $channel->update([
            'is_archived'  => true,
            'is_read_only' => true,
        ]);
    }

    /**
     * Add a user to a channel with the given role.
     */
    public function addMember(Channel $channel, User $user, string $role = 'member'): void
    {
        $existing = ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            // Update role if they are already a member
            $existing->update(['role' => $role]);
            return;
        }

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $user->id,
            'role'       => $role,
            'is_muted'   => false,
            'joined_at'  => now(),
        ]);

        $channel->increment('members_count');

        try {
            event(new MemberJoined($user, tenant('id')));
        } catch (\Throwable) {
            // Non-fatal.
        }
    }

    /**
     * Remove a user from a channel.
     */
    public function removeMember(Channel $channel, User $user): void
    {
        $deleted = ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted) {
            $channel->decrement('members_count');
        }
    }

    /**
     * Get paginated list of active (non-archived) channels.
     */
    public function getPaginated(int $perPage = 50): LengthAwarePaginator
    {
        return Channel::active()
            ->withCount(['members', 'messages'])
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Get all channels visible to a user:
     * public channels + private channels they are a member of.
     */
    public function getVisibleChannels(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Channel::active()
            ->where(function ($q) use ($user) {
                $q->where('type', 'public')
                  ->orWhereHas('channelMembers', fn ($q) => $q->where('user_id', $user->id));
            })
            ->with('channelMembers')
            ->withCount('channelMembers as members_count')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }
}
