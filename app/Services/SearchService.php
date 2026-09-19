<?php

namespace App\Services;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\ConversationParticipant;
use App\Models\Tenant\File;
use App\Models\Tenant\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SearchService
{
    /**
     * Search across messages, channels, members, and files — every result is
     * scoped to what $user can actually see: public channels, private
     * channels/DMs they belong to, and members of their own tenant.
     *
     * Supported filters:
     *   - type        : 'messages'|'channels'|'members'|'files'  (default: all)
     *   - channel_id  : int   — scope messages/files to a specific channel
     *   - from_user   : int   — filter messages by sender user_id
     *   - file_type   : string — filter files by type (image/document/…)
     *   - date_from   : string — ISO date, lower bound for created_at
     *   - date_to     : string — ISO date, upper bound for created_at
     *   - limit       : int   — max results per group (default 20)
     *
     * @return array{messages: array, channels: array, members: array, files: array, total: int}
     */
    public function search(string $query, User $user, array $filters = []): array
    {
        $query = trim($query);
        $limit = (int) ($filters['limit'] ?? 20);
        $type  = $filters['type'] ?? 'all';
        $term  = '%' . addcslashes($query, '%_\\') . '%';

        $results = [
            'messages' => [],
            'channels' => [],
            'members'  => [],
            'files'    => [],
            'total'    => 0,
        ];

        if (empty($query)) {
            return $results;
        }

        $visibleChannelIds = ChannelMember::where('user_id', $user->id)->pluck('channel_id')
            ->merge(Channel::where('type', 'public')->pluck('id'))
            ->unique();
        $visibleConversationIds = ConversationParticipant::where('user_id', $user->id)->pluck('conversation_id');

        if ($type === 'all' || $type === 'messages') {
            $results['messages'] = $this->searchMessages($term, $filters, $limit, $visibleChannelIds, $visibleConversationIds);
        }

        if ($type === 'all' || $type === 'channels') {
            $results['channels'] = $this->searchChannels($term, $limit, $visibleChannelIds);
        }

        if ($type === 'all' || $type === 'members') {
            $results['members'] = $this->searchMembers($term, $limit);
        }

        if ($type === 'all' || $type === 'files') {
            $results['files'] = $this->searchFiles($term, $filters, $limit, $visibleChannelIds, $visibleConversationIds);
        }

        $results['total'] = count($results['messages'])
            + count($results['channels'])
            + count($results['members'])
            + count($results['files']);

        return $results;
    }

    private function searchMessages(string $term, array $filters, int $limit, $visibleChannelIds, $visibleConversationIds): array
    {
        $query = Message::with(['channel', 'user'])
            ->where('body', 'LIKE', $term)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($visibleChannelIds, $visibleConversationIds): void {
                $q->whereIn('channel_id', $visibleChannelIds)
                    ->orWhereIn('conversation_id', $visibleConversationIds);
            });

        if (! empty($filters['channel_id'])) {
            $query->where('channel_id', (int) $filters['channel_id']);
        }

        if (! empty($filters['from_user'])) {
            $query->where('user_id', (int) $filters['from_user']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Message $m) => [
                'id'           => $m->id,
                'body'         => $m->body,
                'user_id'      => $m->user_id,
                'user_name'    => $m->user?->display_name ?? $m->user?->name,
                'channel_id'   => $m->channel_id,
                'channel_name' => $m->channel?->name,
                'channel'      => $m->channel ? ['id' => $m->channel->id, 'name' => $m->channel->name] : null,
                'created_at'   => $m->created_at?->toIso8601String(),
                'is_edited'    => $m->is_edited,
                'is_pinned'    => $m->is_pinned,
            ])
            ->toArray();
    }

    private function searchChannels(string $term, int $limit, $visibleChannelIds): array
    {
        return Channel::active()
            ->whereIn('id', $visibleChannelIds)
            ->where(function ($q) use ($term): void {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('description', 'LIKE', $term)
                  ->orWhere('topic', 'LIKE', $term);
            })
            ->limit($limit)
            ->get()
            ->map(fn (Channel $c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'slug'          => $c->slug,
                'description'   => $c->description,
                'type'          => $c->type,
                'members_count' => $c->members_count,
                'is_archived'   => $c->is_archived,
            ])
            ->toArray();
    }

    /**
     * Members are scoped to the current tenant — `users` is a central,
     * cross-tenant table, so without this a search would leak every active
     * user in the whole application, not just this workspace's members.
     */
    private function searchMembers(string $term, int $limit): array
    {
        $tenantUserIds = DB::connection('mysql')->table('tenant_users')
            ->where('tenant_id', tenant('id'))
            ->pluck('user_id');

        return User::where('is_active', true)
            ->whereIn('id', $tenantUserIds)
            ->where(function ($q) use ($term): void {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('display_name', 'LIKE', $term)
                  ->orWhere('email', 'LIKE', $term);
            })
            ->limit($limit)
            ->get()
            ->map(fn (User $u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'display_name' => $u->display_name,
                'email'        => $u->email,
                'avatar'       => $u->avatar_url,
                'status'       => $u->status,
            ])
            ->toArray();
    }

    private function searchFiles(string $term, array $filters, int $limit, $visibleChannelIds, $visibleConversationIds): array
    {
        $query = File::whereNull('deleted_at')
            ->where('original_name', 'LIKE', $term)
            ->where(function ($q) use ($visibleChannelIds, $visibleConversationIds): void {
                $q->whereIn('channel_id', $visibleChannelIds)
                    ->orWhereIn('conversation_id', $visibleConversationIds);
            });

        if (! empty($filters['channel_id'])) {
            $query->where('channel_id', (int) $filters['channel_id']);
        }

        if (! empty($filters['file_type'])) {
            $query->where('type', $filters['file_type']);
        }

        return $query->latest()
            ->limit($limit)
            ->get()
            ->map(fn (File $f) => [
                'id'            => $f->id,
                'original_name' => $f->original_name,
                'type'          => $f->type,
                'mime_type'     => $f->mime_type,
                'size'          => $f->size,
                'size_human'    => $f->size_human,
                'url'           => $f->download_url,
                'thumbnail_url' => $f->thumbnail_url,
                'channel_id'    => $f->channel_id,
                'uploaded_by'   => $f->uploaded_by,
                'created_at'    => $f->created_at?->toIso8601String(),
            ])
            ->toArray();
    }
}
