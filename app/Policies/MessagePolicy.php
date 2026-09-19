<?php

namespace App\Policies;

use App\Models\Tenant\Message;
use App\Models\Tenant\ChannelMember;
use App\Models\User;
use App\Policies\ChannelPolicy;

class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A message is readable exactly when its channel or conversation is:
     * public channels to anyone, private channels/DMs to their members only.
     */
    public function view(User $user, Message $message): bool
    {
        if ($message->channel_id) {
            return $message->channel && (new ChannelPolicy)->view($user, $message->channel);
        }

        return $message->conversation
            ?->participantEntries()
            ->where('user_id', $user->id)
            ->exists() ?? false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Message $message): bool
    {
        return $message->user_id === $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        if ($message->user_id === $user->id) {
            return true;
        }

        $member = ChannelMember::where('channel_id', $message->channel_id)
            ->where('user_id', $user->id)
            ->first();

        return $member?->isAdmin() ?? false;
    }

    public function pin(User $user, Message $message): bool
    {
        $member = ChannelMember::where('channel_id', $message->channel_id)
            ->where('user_id', $user->id)
            ->first();

        return $member?->isAdmin() ?? false;
    }

    public function restore(User $user, Message $message): bool
    {
        return $message->user_id === $user->id;
    }

    public function forceDelete(User $user, Message $message): bool
    {
        return false;
    }
}
