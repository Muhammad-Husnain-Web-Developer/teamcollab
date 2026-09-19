<?php

namespace App\Services;

use App\Models\Tenant\Channel;
use App\Models\Tenant\File;
use App\Models\Tenant\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Return high-level dashboard statistics for the current tenant.
     *
     * @return array{
     *   total_members: int,
     *   active_users: int,
     *   total_messages: int,
     *   messages_today: int,
     *   total_channels: int,
     *   storage_used: int,
     *   storage_used_human: string
     * }
     */
    public function getDashboardStats(): array
    {
        $totalMembers   = User::where('is_active', true)->count();
        $activeUsers    = User::where('status', 'online')->count();
        $totalMessages  = Message::whereNull('deleted_at')->count();
        $messagesToday  = Message::whereNull('deleted_at')
            ->whereDate('created_at', today())
            ->count();
        $totalChannels  = Channel::where('is_archived', false)->count();
        $storageUsed    = (int) File::whereNull('deleted_at')->sum('size');

        return [
            'total_members'      => $totalMembers,
            'active_users'       => $activeUsers,
            'total_messages'     => $totalMessages,
            'messages_today'     => $messagesToday,
            'total_channels'     => $totalChannels,
            'storage_used'       => $storageUsed,
            'storage_used_human' => $this->formatBytes($storageUsed),
        ];
    }

    /**
     * Return daily message counts for the last N days.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function getDailyMessages(int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();

        $rows = Message::whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Build a full date series so days with 0 messages are included
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date         = now()->subDays($i)->toDateString();
            $series[]     = [
                'date'  => $date,
                'count' => (int) ($rows[$date]->count ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Return per-channel activity statistics: message count, member count, last activity.
     *
     * @return array<int, array{
     *   channel_id: int,
     *   channel_name: string,
     *   channel_slug: string,
     *   messages_count: int,
     *   members_count: int,
     *   last_activity_at: string|null
     * }>
     */
    public function getChannelActivity(): array
    {
        return Channel::where('is_archived', false)
            ->select([
                'id',
                'name',
                'slug',
                'messages_count',
                'members_count',
                'last_activity_at',
            ])
            ->orderBy('messages_count', 'desc')
            ->limit(50)
            ->get()
            ->map(fn (Channel $channel) => [
                'channel_id'       => $channel->id,
                'channel_name'     => $channel->name,
                'channel_slug'     => $channel->slug,
                'messages_count'   => (int) $channel->messages_count,
                'members_count'    => (int) $channel->members_count,
                'last_activity_at' => $channel->last_activity_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Format bytes to a human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 2) . ' GB';
        }

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2) . ' MB';
        }

        if ($bytes >= 1_024) {
            return round($bytes / 1_024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
