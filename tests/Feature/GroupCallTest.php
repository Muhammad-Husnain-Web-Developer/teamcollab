<?php

namespace Tests\Feature;

use App\Events\CallInitiated;
use App\Events\CallParticipantJoined;
use App\Events\CallParticipantLeft;
use App\Events\CallRecordingToggled;
use App\Events\CallRejected;
use App\Events\WebRtcSignal;
use App\Models\Tenant\Call;
use App\Models\Tenant\CallParticipant;
use App\Models\Tenant\TenantNotification;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TenantTestCase;

class GroupCallTest extends TenantTestCase
{
    private function tenantUrl(string $path): string
    {
        return 'http://' . $this->tenant->id . '.localhost' . $path;
    }

    /**
     * Only fake the call events — a bare Event::fake() would also swallow
     * Stancl Tenancy's TenantCreated event and the tenant DB would never be
     * created for the test.
     */
    private function fakeCallEvents(): void
    {
        Event::fake([
            CallInitiated::class,
            CallParticipantJoined::class,
            CallParticipantLeft::class,
            CallRejected::class,
            CallRecordingToggled::class,
            WebRtcSignal::class,
        ]);
    }

    private function initiate(User $caller, array $callees, string $type = 'video'): string
    {
        return $this->actingAs($caller)
            ->postJson($this->tenantUrl('/calls'), [
                'callee_ids' => array_map(fn (User $u) => $u->id, $callees),
                'call_type'  => $type,
            ])
            ->assertOk()
            ->json('callId');
    }

    public function test_initiating_creates_the_roster_and_rings_every_invitee(): void
    {
        $this->fakeCallEvents();

        $caller = $this->tenantUser();
        [$a, $b, $c] = [$this->tenantUser(), $this->tenantUser(), $this->tenantUser()];

        $callId = $this->initiate($caller, [$a, $b, $c]);

        $call = Call::find($callId);
        $this->assertNotNull($call);
        $this->assertSame('ringing', $call->status);
        $this->assertSame($caller->id, $call->initiated_by);

        $this->assertSame(4, CallParticipant::where('call_id', $callId)->count());
        $this->assertSame('joined', CallParticipant::where('call_id', $callId)->where('user_id', $caller->id)->value('status'));
        $this->assertSame(3, CallParticipant::where('call_id', $callId)->where('status', 'invited')->count());

        Event::assertDispatched(CallInitiated::class, function (CallInitiated $e) use ($callId, $a, $b, $c) {
            return $e->callId === $callId
                && count($e->recipientIds) === 3
                && empty(array_diff([$a->id, $b->id, $c->id], $e->recipientIds))
                && count($e->participants) === 4;
        });
    }

    public function test_the_participant_cap_is_enforced(): void
    {
        $this->fakeCallEvents();

        $caller  = $this->tenantUser();
        $callees = array_map(fn () => $this->tenantUser(), range(1, 6)); // 7 with the caller

        $this->actingAs($caller)
            ->postJson($this->tenantUrl('/calls'), [
                'callee_ids' => array_map(fn (User $u) => $u->id, $callees),
                'call_type'  => 'audio',
            ])
            ->assertUnprocessable();

        $this->assertSame(0, Call::count());
        Event::assertNotDispatched(CallInitiated::class);
    }

    public function test_a_non_member_invitee_rejects_the_whole_call_before_anything_is_created(): void
    {
        $this->fakeCallEvents();

        $caller   = $this->tenantUser();
        $member   = $this->tenantUser();
        $outsider = User::create([
            'name' => 'Outsider', 'email' => 'outsider@example.test',
            'password' => bcrypt('x'), 'email_verified_at' => now(),
        ]);

        $this->actingAs($caller)
            ->postJson($this->tenantUrl('/calls'), [
                'callee_ids' => [$member->id, $outsider->id],
                'call_type'  => 'audio',
            ])
            ->assertForbidden();

        $this->assertSame(0, Call::count());
        Event::assertNotDispatched(CallInitiated::class);
    }

    public function test_accepting_joins_and_returns_everyone_already_in_the_call(): void
    {
        $this->fakeCallEvents();

        $caller = $this->tenantUser();
        $a      = $this->tenantUser();
        $b      = $this->tenantUser();

        $callId = $this->initiate($caller, [$a, $b]);

        // First accepter sees only the caller.
        $first = $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/accept"))->assertOk();
        $this->assertEqualsCanonicalizing([$caller->id], array_column($first->json('participants'), 'id'));
        $this->assertSame('active', Call::find($callId)->status);

        Event::assertDispatched(CallParticipantJoined::class, function (CallParticipantJoined $e) use ($a, $caller, $b) {
            // The caller (joined) and b (still invited) both hear that a joined.
            return $e->participant['id'] === $a->id
                && empty(array_diff([$caller->id, $b->id], $e->recipientIds));
        });

        // Second accepter sees caller + a — the peers it must offer to.
        $second = $this->actingAs($b)->postJson($this->tenantUrl("/calls/{$callId}/accept"))->assertOk();
        $this->assertEqualsCanonicalizing([$caller->id, $a->id], array_column($second->json('participants'), 'id'));
    }

    public function test_leaving_mid_call_keeps_it_going_for_the_others(): void
    {
        $this->fakeCallEvents();

        $caller = $this->tenantUser();
        $a      = $this->tenantUser();
        $b      = $this->tenantUser();

        $callId = $this->initiate($caller, [$a, $b]);
        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/accept"));
        $this->actingAs($b)->postJson($this->tenantUrl("/calls/{$callId}/accept"));

        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/leave"))->assertOk();

        $this->assertSame('left', CallParticipant::where('call_id', $callId)->where('user_id', $a->id)->value('status'));
        $this->assertSame('active', Call::find($callId)->status);

        Event::assertDispatched(CallParticipantLeft::class, fn (CallParticipantLeft $e) =>
            $e->userId === $a->id && $e->callEnded === false
            && empty(array_diff([$caller->id, $b->id], $e->recipientIds)));
    }

    public function test_the_last_person_leaving_ends_the_call(): void
    {
        $this->fakeCallEvents();

        $caller = $this->tenantUser();
        $a      = $this->tenantUser();

        $callId = $this->initiate($caller, [$a]);
        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/accept"));

        $this->actingAs($caller)->postJson($this->tenantUrl("/calls/{$callId}/leave"))->assertOk();
        Event::assertDispatched(CallParticipantLeft::class, fn (CallParticipantLeft $e) => $e->callEnded === false);

        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/leave"))->assertOk();

        $this->assertSame('ended', Call::find($callId)->status);
        $this->assertNotNull(Call::find($callId)->ended_at);
    }

    public function test_cancelling_while_ringing_ends_the_call_and_leaves_missed_call_notifications(): void
    {
        $this->fakeCallEvents();

        $caller = $this->tenantUser();
        $a      = $this->tenantUser();
        $b      = $this->tenantUser();

        $callId = $this->initiate($caller, [$a, $b], 'audio');

        $this->actingAs($caller)->postJson($this->tenantUrl("/calls/{$callId}/leave"))->assertOk();

        $call = Call::find($callId);
        $this->assertSame('ended', $call->status);

        Event::assertDispatched(CallParticipantLeft::class, fn (CallParticipantLeft $e) =>
            $e->callEnded === true && empty(array_diff([$a->id, $b->id], $e->recipientIds)));

        $this->assertSame(2, TenantNotification::where('type', 'missed_call')->count());
        $this->assertSame(1, TenantNotification::where('type', 'missed_call')->where('user_id', $a->id)->count());
    }

    public function test_declining_a_one_on_one_call_ends_it_for_the_caller(): void
    {
        $this->fakeCallEvents();

        $caller = $this->tenantUser();
        $a      = $this->tenantUser();

        $callId = $this->initiate($caller, [$a]);

        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/reject"))->assertOk();

        $this->assertSame('declined', CallParticipant::where('call_id', $callId)->where('user_id', $a->id)->value('status'));
        $this->assertSame('ended', Call::find($callId)->status);

        Event::assertDispatched(CallRejected::class, fn (CallRejected $e) =>
            $e->userId === $a->id && $e->callEnded === true && $e->recipientIds === [$caller->id]);
    }

    public function test_declining_a_group_call_does_not_end_it_while_others_remain(): void
    {
        $this->fakeCallEvents();

        $caller = $this->tenantUser();
        $a      = $this->tenantUser();
        $b      = $this->tenantUser();

        $callId = $this->initiate($caller, [$a, $b]);

        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/reject"))->assertOk();

        // b is still ringing, so the caller keeps waiting.
        $this->assertSame('ringing', Call::find($callId)->status);
        Event::assertDispatched(CallRejected::class, fn (CallRejected $e) => $e->callEnded === false);
    }

    public function test_signals_only_relay_between_participants_of_the_same_call(): void
    {
        $this->fakeCallEvents();

        $caller   = $this->tenantUser();
        $a        = $this->tenantUser();
        $stranger = $this->tenantUser(); // a workspace member, but not on this call

        $callId = $this->initiate($caller, [$a]);

        $this->actingAs($caller)->postJson($this->tenantUrl("/calls/{$callId}/signal"), [
            'target_user_id' => $a->id,
            'signal'         => ['type' => 'offer', 'data' => ['sdp' => 'v=0']],
        ])->assertOk();

        $this->actingAs($caller)->postJson($this->tenantUrl("/calls/{$callId}/signal"), [
            'target_user_id' => $stranger->id,
            'signal'         => ['type' => 'offer', 'data' => ['sdp' => 'v=0']],
        ])->assertForbidden();

        Event::assertDispatchedTimes(WebRtcSignal::class, 1);
    }
    public function test_recording_notice_reaches_everyone_else_in_the_call(): void
    {
        $this->fakeCallEvents();

        $caller = $this->tenantUser();
        $a      = $this->tenantUser();
        $b      = $this->tenantUser();

        $callId = $this->initiate($caller, [$a, $b]);
        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/accept"));

        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/recording"), ['recording' => true])->assertOk();

        // The caller (joined) and b (still ringing) are both told.
        Event::assertDispatched(CallRecordingToggled::class, fn (CallRecordingToggled $e) =>
            $e->userId === $a->id && $e->recording === true
            && empty(array_diff([$caller->id, $b->id], $e->recipientIds)));

        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/recording"), ['recording' => false])->assertOk();
        Event::assertDispatched(CallRecordingToggled::class, fn (CallRecordingToggled $e) => $e->recording === false);
    }

    public function test_only_a_joined_participant_can_announce_a_recording(): void
    {
        $this->fakeCallEvents();

        $caller = $this->tenantUser();
        $a      = $this->tenantUser();

        $callId = $this->initiate($caller, [$a]);

        // a is still 'invited', not joined.
        $this->actingAs($a)->postJson($this->tenantUrl("/calls/{$callId}/recording"), ['recording' => true])
            ->assertUnprocessable();

        Event::assertNotDispatched(CallRecordingToggled::class);
    }
}
