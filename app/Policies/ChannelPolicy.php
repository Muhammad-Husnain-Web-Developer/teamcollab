<?php

namespace App\Policies;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\User;

class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Channel $channel): bool
    {
        if ($channel->type === 'public') {
            return true;
        }

        return ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Channel $channel): bool
    {
        $member = ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();

        return $member?->isAdmin() || $channel->created_by === $user->id;
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $channel->created_by === $user->id || $this->isTenantOwner($user);
    }

    public function pin(User $user, Channel $channel): bool
    {
        $member = ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();

        return $member?->isAdmin() || $channel->created_by === $user->id;
    }

    /**
     * Add or remove other people. Restricted to channel admins and the
     * creator — otherwise anyone could pull themselves, or a stranger, into a
     * private channel.
     *
     * Membership is required even for the creator: once they have left, they
     * would otherwise still be able to add themselves back into a private
     * channel they no longer belong to.
     */
    public function manageMembers(User $user, Channel $channel): bool
    {
        if ($channel->is_archived) {
            return false;
        }

        $member = ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            return false;
        }

        return $member->isAdmin() || $channel->created_by === $user->id;
    }

    private function isTenantOwner(User $user): bool
    {
        return tenant()?->owner_id === $user->id;
    }
}
