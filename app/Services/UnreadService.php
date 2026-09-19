<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Unread message counts.
 *
 * Read position is tracked by message id, not timestamp: ids are monotonic,
 * whereas Laravel writes timestamps at whole-second precision, so a message
 * posted in the same second as the read marker would tie and be missed.
 */
class UnreadService
{
    /**
     * Unread count per channel for this user, keyed by channel_id.
     *
     * Own messages never count. Channels the user has never opened count all
     * messages (last_read_message_id is null → treated as 0).
     *
     * @return array<int,int>
     */
    public function channelCounts(User $user): array
    {
        return DB::table('channel_members as cm')
            ->join('messages as m', function ($join) use ($user) {
                $join->on('m.channel_id', '=', 'cm.channel_id')
                    ->whereNull('m.deleted_at')
                    ->where('m.user_id', '!=', $user->id)
                    ->whereRaw('m.id > coalesce(cm.last_read_message_id, 0)');
            })
            ->where('cm.user_id', $user->id)
            ->groupBy('cm.channel_id')
            ->selectRaw('cm.channel_id as channel_id, count(m.id) as unread')
            ->pluck('unread', 'channel_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Unread count per conversation for this user, keyed by conversation_id.
     *
     * @return array<int,int>
     */
    public function conversationCounts(User $user): array
    {
        return DB::table('conversation_participants as cp')
            ->join('messages as m', function ($join) use ($user) {
                $join->on('m.conversation_id', '=', 'cp.conversation_id')
                    ->whereNull('m.deleted_at')
                    ->where('m.user_id', '!=', $user->id)
                    ->whereRaw('m.id > coalesce(cp.last_read_message_id, 0)');
            })
            ->where('cp.user_id', $user->id)
            ->groupBy('cp.conversation_id')
            ->selectRaw('cp.conversation_id as conversation_id, count(m.id) as unread')
            ->pluck('unread', 'conversation_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Mark a channel read up to its newest message.
     * Returns false when the user has no membership row to update.
     */
    public function markChannelRead(int $channelId, User $user): bool
    {
        $latestId = DB::table('messages')
            ->where('channel_id', $channelId)
            ->whereNull('deleted_at')
            ->max('id');

        return DB::table('channel_members')
            ->where('channel_id', $channelId)
            ->where('user_id', $user->id)
            ->update([
                'last_read_at'         => now(),
                'last_read_message_id' => $latestId,
                'updated_at'           => now(),
            ]) > 0;
    }

    /**
     * Mark a conversation read up to its newest message.
     * Returns false when the user has no participant row to update.
     */
    public function markConversationRead(int $conversationId, User $user): bool
    {
        $latestId = DB::table('messages')
            ->where('conversation_id', $conversationId)
            ->whereNull('deleted_at')
            ->max('id');

        return DB::table('conversation_participants')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->update([
                'last_read_at'         => now(),
                'last_read_message_id' => $latestId,
                'updated_at'           => now(),
            ]) > 0;
    }
}
