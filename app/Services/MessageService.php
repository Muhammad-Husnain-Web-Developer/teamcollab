<?php

namespace App\Services;

use App\Events\DmMessageSent;
use App\Events\MessageReacted;
use App\Events\MessageRead as MessageReadEvent;
use App\Events\MessageSent;
use App\Jobs\FetchLinkPreviewJob;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\File;
use App\Models\Tenant\Message;
use App\Models\Tenant\MessageReaction;
use App\Models\Tenant\MessageRead;
use App\Models\Tenant\MessageThread;
use App\Models\User;
use App\Policies\ChannelPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MessageService
{
    /**
     * Send a message in a channel, persist it, and fire the MessageSent event.
     */
    public function send(array $data, User $user): Message
    {
        if (! empty($data['parent_id'])) {
            $parent = Message::find($data['parent_id']);

            $sharesChannel = $parent && $parent->channel_id !== null
                && $parent->channel_id === ($data['channel_id'] ?? null);
            $sharesConversation = $parent && $parent->conversation_id !== null
                && $parent->conversation_id === ($data['conversation_id'] ?? null);

            abort_unless($sharesChannel || $sharesConversation, 422, 'Thread parent does not belong to this channel or conversation.');
        }

        $message = DB::transaction(function () use ($data, $user): Message {
            $metadata = $this->buildTypeMetadata($data);

            /** @var Message $message */
            $message = Message::create([
                'channel_id'      => $data['channel_id'],
                'conversation_id' => $data['conversation_id'] ?? null,
                'user_id'         => $user->id,
                'thread_id'       => $data['thread_id'] ?? null,
                'parent_id'       => $data['parent_id'] ?? null,
                'body'            => $data['body'] ?? '',
                'type'            => $data['type'] ?? 'text',
                'is_edited'       => false,
                'is_pinned'       => false,
                'mentions'        => $data['mentions'] ?? [],
                'attachments'     => $data['attachments'] ?? [],
                'metadata'        => $metadata,
                'replies_count'   => 0,
            ]);

            // Update parent reply count when this is a thread reply
            if ($message->parent_id) {
                Message::where('id', $message->parent_id)->increment('replies_count');
                $this->touchThreadSummary($message);
            }

            // Link pre-uploaded files to this message. Ownership + unlinked
            // checks prevent hijacking other users' uploads.
            if (! empty($data['files'])) {
                File::whereIn('id', $data['files'])
                    ->where('uploaded_by', $user->id)
                    ->whereNull('message_id')
                    ->update([
                        'message_id' => $message->id,
                        'channel_id' => $message->channel_id,
                    ]);
            }

            return $message;
        });

        // Load relations and fire event OUTSIDE the transaction so a broadcast
        // failure never rolls back the saved message.
        $message->load(['reactions', 'files', 'parent.user']);
        $message->setRelation('user', $user);

        try {
            event(new MessageSent($message, tenant('id')));
        } catch (\Throwable) {
            // Broadcast failure is non-fatal — message is already persisted.
        }

        $this->queueLinkPreviews($message);

        return $message;
    }

    /**
     * DM counterpart to send() — used by SendScheduledMessageJob for scheduled
     * DM sends. ConversationController::sendMessage() has its own inline
     * version of this same logic for the live HTTP path (a pre-existing
     * duplication between the channel/DM paths, not introduced here); this
     * method exists so new callers like the scheduled-send job don't have to
     * duplicate it a third time.
     */
    public function sendToConversation(array $data, User $user): Message
    {
        $conversation = Conversation::findOrFail($data['conversation_id']);

        $metadata = $this->buildTypeMetadata($data);

        $message = $conversation->messages()->create([
            'user_id'   => $user->id,
            'body'      => $data['body'] ?? '',
            'type'      => $data['type'] ?? 'text',
            'parent_id' => $data['parent_id'] ?? null,
            'metadata'  => $metadata,
        ]);

        if (! empty($data['files'])) {
            File::whereIn('id', $data['files'])
                ->where('uploaded_by', $user->id)
                ->whereNull('message_id')
                ->update([
                    'message_id'      => $message->id,
                    'conversation_id' => $conversation->id,
                ]);
        }

        $message->load(['reactions', 'files', 'parent.user']);
        $message->setRelation('user', $user);
        $conversation->touch();

        try {
            event(new DmMessageSent($message, tenant('id')));
        } catch (\Throwable) {
            // Broadcast failure is non-fatal — message is already persisted.
        }

        $this->queueLinkPreviews($message);

        return $message;
    }

    /**
     * Fold type-specific, narrowly-validated request fields (voice duration,
     * GIF details) into a metadata array. Shared by send() above and by
     * ConversationController::sendMessage(), which creates DM messages
     * directly rather than through this service.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function buildTypeMetadata(array $data): array
    {
        $metadata = $data['metadata'] ?? [];

        if (array_key_exists('voice_duration_seconds', $data) && $data['voice_duration_seconds'] !== null) {
            $metadata['voice_duration_seconds'] = $data['voice_duration_seconds'];
        }

        if (($data['type'] ?? null) === 'gif' && ! empty($data['gif_url'])) {
            $metadata['gif_url']         = $data['gif_url'];
            $metadata['gif_preview_url'] = $data['gif_preview_url'] ?? $data['gif_url'];
            $metadata['gif_provider']    = $data['gif_provider'] ?? 'giphy';
            $metadata['gif_width']       = $data['gif_width'] ?? null;
            $metadata['gif_height']      = $data['gif_height'] ?? null;
        }

        return $metadata;
    }

    /**
     * Extract URLs from a message body and queue an async OG-tag fetch for
     * each. Shared by the channel send() path above and by
     * ConversationController::sendMessage(), which creates DM messages
     * directly rather than through this service.
     */
    public function queueLinkPreviews(Message $message): void
    {
        $urls = app(LinkPreviewService::class)->extractUrls($message->body ?? '');

        if (empty($urls)) {
            return;
        }

        FetchLinkPreviewJob::dispatch($message->id, tenant('id'), $urls);
    }

    /**
     * Keep the denormalised thread summary in step with a newly saved reply.
     *
     * Runs inside send()'s transaction. participant_ids accumulates everyone who
     * has posted in the thread, so the UI can show facepiles without a join.
     */
    private function touchThreadSummary(Message $reply): void
    {
        $thread = MessageThread::firstOrNew(['parent_message_id' => $reply->parent_id]);

        $participants = $thread->participant_ids ?? [];
        if (! in_array($reply->user_id, $participants, true)) {
            $participants[] = $reply->user_id;
        }

        $thread->fill([
            // A reply always belongs to the same channel as its parent. For DM
            // replies channel_id is null, which the nullable column allows.
            'channel_id'      => $reply->channel_id,
            'replies_count'   => ($thread->replies_count ?? 0) + 1,
            'last_reply_id'   => $reply->id,
            'last_reply_at'   => $reply->created_at ?? now(),
            'participant_ids' => $participants,
        ])->save();
    }

    /**
     * Edit the body of an existing message.
     */
    public function update(Message $message, string $body): Message
    {
        $message->update([
            'body'      => $body,
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        return $message->fresh();
    }

    /**
     * Soft-delete a message.
     */
    public function delete(Message $message): void
    {
        DB::transaction(function () use ($message): void {
            if ($message->parent_id) {
                Message::where('id', $message->parent_id)
                    ->where('replies_count', '>', 0)
                    ->decrement('replies_count');
            }

            $message->delete();
        });
    }

    /**
     * Pin a message in its channel.
     */
    public function pin(Message $message, User $user): Message
    {
        $message->update([
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by' => $user->id,
        ]);

        return $message->fresh();
    }

    /**
     * Unpin a previously pinned message.
     */
    public function unpin(Message $message): Message
    {
        $message->update([
            'is_pinned' => false,
            'pinned_at' => null,
            'pinned_by' => null,
        ]);

        return $message->fresh();
    }

    /**
     * Toggle an emoji reaction on a message.
     * Returns ['action' => 'added'|'removed', 'reactions' => grouped array].
     */
    public function react(Message $message, User $user, string $emoji): array
    {
        $tenantId = tenant('id');

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id'    => $user->id,
                'emoji'      => $emoji,
            ]);
            $action = 'added';
        }

        try {
            event(new MessageReacted(
                $message->id,
                $emoji,
                $user->id,
                $user->display_name ?? $user->name,
                $action,
                $message->channel_id,
                $message->conversation_id,
                $tenantId
            ));
        } catch (\Throwable) {
            // Non-fatal.
        }

        // Return fresh grouped reactions
        $message->load('reactions');

        return [
            'action'    => $action,
            'reactions' => $message->grouped_reactions,
        ];
    }

    /**
     * Mark a message as read by a user.
     */
    public function markAsRead(Message $message, User $user): void
    {
        $alreadyRead = MessageRead::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyRead) {
            return;
        }

        MessageRead::create([
            'message_id' => $message->id,
            'user_id'    => $user->id,
            'read_at'    => now(),
        ]);

        // Update the channel member's last_read_at bookmark
        \App\Models\Tenant\ChannelMember::where('channel_id', $message->channel_id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        try {
            event(new MessageReadEvent(
                $message->id,
                $user->id,
                $message->channel_id,
                tenant('id')
            ));
        } catch (\Throwable) {
            // Non-fatal.
        }
    }

    /**
     * Get paginated top-level messages for a channel (newest first).
     */
    public function getPaginated(Channel $channel, int $perPage = 50): LengthAwarePaginator
    {
        return Message::inChannel($channel->id)
            ->with(['reactions', 'files'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Forward a message into one or more channels and/or DMs with a user.
     *
     * The forwarded message never re-links the original's `File` rows — that
     * would steal their attribution from the source message. Instead it
     * snapshots everything needed to render the "forwarded from" quote block
     * into `metadata.forwarded_from`.
     *
     * @param  int[]  $channelIds
     * @param  int[]  $userIds
     * @return array{channels: Message[], conversations: Message[]}
     */
    public function forward(Message $original, User $user, array $channelIds, array $userIds, ?string $comment): array
    {
        $original->loadMissing(['user', 'files']);

        $forwardedFrom = [
            'message_id'      => $original->id,
            'channel_id'      => $original->channel_id,
            'conversation_id' => $original->conversation_id,
            'user_id'         => $original->user_id,
            'user_name'       => $original->user?->display_name ?? $original->user?->name,
            'body'            => $original->body,
            'files'           => $original->files->map(fn (File $f) => [
                'id'            => $f->id,
                'original_name' => $f->original_name,
                'type'          => $f->type,
                'size_human'    => $f->size_human,
                'view_url'      => $f->view_url,
                'download_url'  => $f->download_url,
            ])->toArray(),
        ];

        // Resolve and authorize every channel target up front so a bad id
        // partway through the list aborts before anything is created, rather
        // than leaving some targets forwarded and others silently missing.
        $channels = array_map(function (int $channelId) use ($user) {
            $channel = Channel::find($channelId);

            abort_unless($channel && (new ChannelPolicy)->view($user, $channel), 403);

            return $channel;
        }, array_unique($channelIds));

        // Same up-front validation for DM targets — everyone forwarded to
        // must actually belong to this tenant workspace.
        $targetUserIds = array_values(array_filter(
            array_unique(array_map('intval', $userIds)),
            fn (int $id) => $id !== $user->id,
        ));

        foreach ($targetUserIds as $targetUserId) {
            $isTenantMember = DB::connection('mysql')->table('tenant_users')
                ->where('tenant_id', tenant('id'))
                ->where('user_id', $targetUserId)
                ->exists();

            abort_unless($isTenantMember, 403, 'That user is not a member of this workspace.');
        }

        $results = ['channels' => [], 'conversations' => []];

        foreach ($channels as $channel) {
            $message = Message::create([
                'channel_id' => $channel->id,
                'user_id'    => $user->id,
                'body'       => $comment ?? '',
                'type'       => 'text',
                'mentions'   => [],
                'attachments' => [],
                'metadata'   => ['forwarded_from' => $forwardedFrom],
                'replies_count' => 0,
            ]);

            $message->load(['reactions', 'files', 'parent.user']);
            $message->setRelation('user', $user);

            try {
                event(new MessageSent($message, tenant('id')));
            } catch (\Throwable) {
                // Broadcast failure is non-fatal — message is already persisted.
            }

            $results['channels'][] = $message;
        }

        foreach ($targetUserIds as $targetUserId) {
            $conversation = Conversation::whereHas('participantEntries', fn ($q) => $q->where('user_id', $user->id))
                ->whereHas('participantEntries', fn ($q) => $q->where('user_id', $targetUserId))
                ->where('type', 'direct')
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'type'       => 'direct',
                    'created_by' => $user->id,
                ]);
                $conversation->participantEntries()->createMany([
                    ['user_id' => $user->id],
                    ['user_id' => $targetUserId],
                ]);
            }

            $message = $conversation->messages()->create([
                'user_id'  => $user->id,
                'body'     => $comment ?? '',
                'type'     => 'text',
                'mentions' => [],
                'attachments' => [],
                'metadata' => ['forwarded_from' => $forwardedFrom],
                'replies_count' => 0,
            ]);

            $message->load(['reactions', 'files', 'parent.user']);
            $message->setRelation('user', $user);
            $conversation->touch();

            try {
                event(new DmMessageSent($message, tenant('id')));
            } catch (\Throwable) {
                // Broadcast failure is non-fatal — message is already persisted.
            }

            $results['conversations'][] = $message;
        }

        return $results;
    }
}
