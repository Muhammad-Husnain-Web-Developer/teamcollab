<?php

namespace App\Services;

use App\Events\CallInitiated;
use App\Events\CallParticipantJoined;
use App\Events\CallParticipantLeft;
use App\Events\CallRecordingToggled;
use App\Events\CallRejected;
use App\Models\Tenant\Call;
use App\Models\Tenant\CallParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mesh (peer-to-peer) group calls. The server only keeps the roster and
 * relays signaling — media flows directly between every pair of participants.
 * That is O(N²) connections and O(N) upload bandwidth per person, so the
 * participant count is hard-capped; past ~6 an SFU is the right tool.
 */
class CallService
{
    public const MAX_PARTICIPANTS = 6;

    /**
     * @param  int[]  $calleeIds
     */
    public function initiate(User $initiator, array $calleeIds, string $type, ?string $contextType = null, ?int $contextId = null): Call
    {
        $calleeIds = array_values(array_unique(array_map('intval', $calleeIds)));
        $calleeIds = array_filter($calleeIds, fn (int $id) => $id !== $initiator->id);

        abort_if(count($calleeIds) === 0, 422, 'Pick at least one person to call.');
        abort_if(
            count($calleeIds) + 1 > self::MAX_PARTICIPANTS,
            422,
            'Calls are limited to ' . self::MAX_PARTICIPANTS . ' participants.',
        );

        // Reject the whole request before anything is created or broadcast if
        // even one invitee isn't in this workspace.
        $this->assertAllTenantMembers($calleeIds);

        $call = DB::transaction(function () use ($initiator, $calleeIds, $type, $contextType, $contextId) {
            $call = Call::create([
                'id'           => (string) Str::uuid(),
                'initiated_by' => $initiator->id,
                'call_type'    => $type,
                'context_type' => $contextType,
                'context_id'   => $contextId,
                'status'       => 'ringing',
            ]);

            $call->participants()->create([
                'user_id'   => $initiator->id,
                'status'    => 'joined',
                'joined_at' => now(),
            ]);

            foreach ($calleeIds as $calleeId) {
                $call->participants()->create(['user_id' => $calleeId, 'status' => 'invited']);
            }

            return $call;
        });

        try {
            event(new CallInitiated(
                $call->id,
                $this->presentUser($initiator),
                $type,
                $this->roster($call),
                $calleeIds,
            ));
        } catch (\Throwable) {
            // Broadcast failure is non-fatal — the call row exists and the
            // caller's UI will time out ringing if nobody hears about it.
        }

        return $call;
    }

    /**
     * Mark the user joined and return everyone ELSE already in the call — the
     * joiner opens a peer connection (and sends the offer) to each of them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function accept(Call $call, User $user): array
    {
        abort_if($call->isEnded(), 410, 'This call has already ended.');

        $participant = $this->participantOrFail($call, $user);
        abort_unless(in_array($participant->status, ['invited', 'joined'], true), 422, 'You are no longer part of this call.');

        $participant->update(['status' => 'joined', 'joined_at' => $participant->joined_at ?? now()]);

        if ($call->status === 'ringing') {
            $call->update(['status' => 'active', 'started_at' => now()]);
        }

        $others = $this->joinedOthers($call, $user->id);

        $recipients = $this->reachableOtherIds($call, $user->id);
        if ($recipients) {
            try {
                event(new CallParticipantJoined($call->id, $this->presentUser($user), $recipients));
            } catch (\Throwable) {
                // Non-fatal.
            }
        }

        return $others;
    }

    public function reject(Call $call, User $user): void
    {
        $participant = $this->participantOrFail($call, $user);
        $participant->update(['status' => 'declined']);

        // Nobody left to talk to and nobody still ringing = the call is over
        // for the caller (the classic 1-on-1 "declined" outcome).
        $callEnded = ! $call->isEnded()
            && $call->joinedParticipants()->count() <= 1
            && $call->participants()->where('status', 'invited')->doesntExist();

        if ($callEnded) {
            $call->update(['status' => 'ended', 'ended_at' => now()]);
        }

        $recipients = $this->reachableOtherIds($call, $user->id);
        if ($recipients) {
            try {
                event(new CallRejected($call->id, $user->id, $callEnded, $recipients));
            } catch (\Throwable) {
                // Non-fatal.
            }
        }
    }

    /**
     * Leave (or, for the caller while still ringing, cancel). Ends the call
     * when no joined participant remains.
     */
    public function leave(Call $call, User $user, NotificationService $notifications): void
    {
        $participant = $this->participantOrFail($call, $user);

        $wasRinging = $call->status === 'ringing';

        $participant->update(['status' => 'left', 'left_at' => now()]);

        $remainingJoined = $call->joinedParticipants()->count();
        $callEnded = ! $call->isEnded() && ($remainingJoined === 0 || ($wasRinging && $user->id === $call->initiated_by));

        // Cancelling before anyone answered: the invitees get a missed-call
        // notification, exactly as the old 1-on-1 flow did.
        if ($wasRinging && $user->id === $call->initiated_by) {
            $invitedIds = $call->participants()->where('status', 'invited')->pluck('user_id');

            foreach ($invitedIds as $invitedId) {
                $notifications->notify((int) $invitedId, 'missed_call', [
                    'call_id'       => $call->id,
                    'call_type'     => $call->call_type,
                    'sender_id'     => $user->id,
                    'sender_name'   => $user->display_name ?? $user->name,
                    'sender_avatar' => $user->avatar_url,
                    'tenant_id'     => tenant('id'),
                ]);
            }
        }

        // Compute recipients BEFORE ending, so still-ringing invitees hear
        // that the caller gave up.
        $recipients = $this->reachableOtherIds($call, $user->id);

        if ($callEnded) {
            $call->update(['status' => 'ended', 'ended_at' => now()]);
            $call->participants()->where('status', 'invited')->update(['status' => 'declined']);
        }

        if ($recipients) {
            try {
                event(new CallParticipantLeft($call->id, $user->id, $callEnded, $recipients));
            } catch (\Throwable) {
                // Non-fatal.
            }
        }
    }

    /**
     * Tell everyone else that this participant started/stopped a local
     * recording. Purely a consent notice — the server never sees any media.
     */
    public function setRecording(Call $call, User $user, bool $recording): void
    {
        abort_if($call->isEnded(), 410, 'This call has already ended.');

        $participant = $this->participantOrFail($call, $user);
        abort_unless($participant->status === 'joined', 422, 'Join the call before recording it.');

        $recipients = $this->reachableOtherIds($call, $user->id);
        if ($recipients) {
            try {
                event(new CallRecordingToggled($call->id, $user->id, $recording, $recipients));
            } catch (\Throwable) {
                // Non-fatal.
            }
        }
    }

    /**
     * Only participants of THIS call may relay signals to each other — a
     * tighter check than "is a tenant member".
     */
    public function assertCanSignal(Call $call, User $from, int $targetUserId): void
    {
        abort_if($call->isEnded(), 410, 'This call has already ended.');

        $bothIn = CallParticipant::where('call_id', $call->id)
            ->whereIn('user_id', [$from->id, $targetUserId])
            ->whereIn('status', ['invited', 'joined'])
            ->count() === 2;

        abort_unless($bothIn, 403, 'That user is not in this call.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function roster(Call $call): array
    {
        $participants = $call->participants()->get()->keyBy('user_id');

        return User::whereIn('id', $participants->keys())
            ->get(['id', 'name', 'display_name', 'avatar'])
            ->map(fn (User $u) => $this->presentUser($u) + ['status' => $participants[$u->id]->status])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function joinedOthers(Call $call, int $exceptUserId): array
    {
        $ids = $call->joinedParticipants()->where('user_id', '!=', $exceptUserId)->pluck('user_id');

        return User::whereIn('id', $ids)
            ->get(['id', 'name', 'display_name', 'avatar'])
            ->map(fn (User $u) => $this->presentUser($u))
            ->values()
            ->all();
    }

    /**
     * @return int[]
     */
    private function reachableOtherIds(Call $call, int $exceptUserId): array
    {
        return $call->reachableParticipants()
            ->where('user_id', '!=', $exceptUserId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function participantOrFail(Call $call, User $user): CallParticipant
    {
        $participant = $call->participants()->where('user_id', $user->id)->first();
        abort_unless((bool) $participant, 403, 'You are not part of this call.');

        return $participant;
    }

    /**
     * @param  int[]  $userIds
     */
    private function assertAllTenantMembers(array $userIds): void
    {
        $memberCount = DB::connection('mysql')->table('tenant_users')
            ->where('tenant_id', tenant('id'))
            ->whereIn('user_id', $userIds)
            ->count();

        abort_unless($memberCount === count($userIds), 403, 'One or more of those users is not a member of this workspace.');
    }

    /**
     * @return array{id: int, name: string, display_name: ?string, avatar_url: string}
     */
    private function presentUser(User $user): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'display_name' => $user->display_name,
            'avatar_url'   => $user->avatar_url,
        ];
    }
}
