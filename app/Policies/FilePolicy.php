<?php

namespace App\Policies;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\File;
use App\Models\User;

class FilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A file is viewable exactly like the message it's attached to: public
     * channels to anyone, private channels/DMs to their members only. A
     * freshly-uploaded file not yet linked to a channel/conversation is
     * visible only to its uploader.
     */
    public function view(User $user, File $file): bool
    {
        if ($file->channel_id) {
            return $file->channel && (new ChannelPolicy)->view($user, $file->channel);
        }

        if ($file->conversation_id) {
            return Conversation::find($file->conversation_id)
                ?->participantEntries()
                ->where('user_id', $user->id)
                ->exists() ?? false;
        }

        return $file->uploaded_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, File $file): bool
    {
        return $file->uploaded_by === $user->id;
    }

    public function delete(User $user, File $file): bool
    {
        return $file->uploaded_by === $user->id || tenant()?->owner_id === $user->id;
    }

    public function restore(User $user, File $file): bool
    {
        return $file->uploaded_by === $user->id;
    }

    public function forceDelete(User $user, File $file): bool
    {
        return false;
    }
}
