<?php

namespace App\Services;

use App\Jobs\SendScheduledMessageJob;
use App\Models\Tenant\ScheduledMessage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SchedulingService
{
    /**
     * Persist a scheduled message and queue a delayed job to send it.
     *
     * Uses a plain delayed job rather than Laravel's cron-based Schedule —
     * this app has no scheduler wired up at all (no `withSchedule()`, no
     * server cron entry), and a delayed job needs nothing beyond the queue
     * worker every other async feature here already requires.
     */
    public function schedule(array $data, User $user): ScheduledMessage
    {
        $scheduledFor = Carbon::parse($data['scheduled_for']);

        $scheduledMessage = ScheduledMessage::create([
            'user_id'         => $user->id,
            'channel_id'      => $data['channel_id'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'body'            => $data['body'] ?? '',
            'type'            => $data['type'] ?? 'text',
            'files'           => $data['files'] ?? [],
            'mentions'        => $data['mentions'] ?? [],
            'metadata'        => $data['metadata'] ?? [],
            'scheduled_for'   => $scheduledFor,
            'status'          => 'pending',
        ]);

        SendScheduledMessageJob::dispatch($scheduledMessage->id, tenant('id'))
            ->delay($scheduledFor);

        return $scheduledMessage;
    }

    /**
     * Cancel a pending scheduled message. The job's own status guard (see
     * SendScheduledMessageJob::handle()) is what actually stops it firing —
     * there is no need to revoke the already-queued delayed job.
     */
    public function cancel(ScheduledMessage $scheduledMessage, User $user): void
    {
        abort_unless($scheduledMessage->user_id === $user->id, 403);
        abort_unless($scheduledMessage->status === 'pending', 422, 'This message has already been sent or cancelled.');

        $scheduledMessage->update(['status' => 'cancelled']);
    }

    public function listPending(User $user): Collection
    {
        return ScheduledMessage::where('user_id', $user->id)
            ->pending()
            ->with('channel')
            ->orderBy('scheduled_for')
            ->get();
    }
}
