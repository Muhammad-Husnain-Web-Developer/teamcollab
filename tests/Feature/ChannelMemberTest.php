<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

class ChannelMemberTest extends TenantTestCase
{
    private function url(string $path): string
    {
        return 'http://' . $this->tenant->id . '.localhost' . $path;
    }

    private function privateChannel(User $creator): Channel
    {
        $channel = Channel::create([
            'name'       => 'wedding-updates',
            'slug'       => 'wedding-updates',
            'type'       => 'private',
            'created_by' => $creator->id,
        ]);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $creator->id,
            'role'       => 'admin',
            'joined_at'  => now(),
        ]);

        return $channel;
    }

    public function test_creator_can_add_a_member_to_a_private_channel(): void
    {
        $creator = $this->tenantUser();
        $invitee = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        $this->actingAs($creator)
            ->postJson($this->url("/channels/{$channel->id}/members"), ['user_id' => $invitee->id])
            ->assertCreated()
            ->assertJsonPath('member.id', $invitee->id)
            ->assertJsonPath('members_count', 2);

        $this->assertDatabaseHas('channel_members', [
            'channel_id' => $channel->id,
            'user_id'    => $invitee->id,
        ]);
    }

    public function test_added_member_can_then_read_the_private_channel(): void
    {
        $creator = $this->tenantUser();
        $invitee = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        // Before: the whole point of a private channel.
        $this->actingAs($invitee)
            ->getJson($this->url("/channels/{$channel->id}/messages"))
            ->assertForbidden();

        $this->actingAs($creator)
            ->postJson($this->url("/channels/{$channel->id}/members"), ['user_id' => $invitee->id])
            ->assertCreated();

        $this->actingAs($invitee)
            ->getJson($this->url("/channels/{$channel->id}/messages"))
            ->assertOk();
    }

    public function test_a_plain_member_cannot_add_others(): void
    {
        $creator  = $this->tenantUser();
        $member   = $this->tenantUser();
        $outsider = $this->tenantUser();
        $channel  = $this->privateChannel($creator);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $member->id,
            'role'       => 'member',
            'joined_at'  => now(),
        ]);

        $this->actingAs($member)
            ->postJson($this->url("/channels/{$channel->id}/members"), ['user_id' => $outsider->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('channel_members', [
            'channel_id' => $channel->id,
            'user_id'    => $outsider->id,
        ]);
    }

    public function test_a_non_member_cannot_add_themselves(): void
    {
        $creator  = $this->tenantUser();
        $outsider = $this->tenantUser();
        $channel  = $this->privateChannel($creator);

        // This is the hole that would make "private" meaningless.
        $this->actingAs($outsider)
            ->postJson($this->url("/channels/{$channel->id}/members"), ['user_id' => $outsider->id])
            ->assertForbidden();
    }

    public function test_a_user_outside_the_workspace_cannot_be_added(): void
    {
        $creator = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        // Real user, but never attached to this tenant.
        $stranger = User::create([
            'name'              => 'Stranger',
            'email'             => Str::lower(Str::random(10)) . '@example.test',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($creator)
            ->postJson($this->url("/channels/{$channel->id}/members"), ['user_id' => $stranger->id])
            ->assertForbidden();
    }

    public function test_admin_can_remove_a_member(): void
    {
        $creator = $this->tenantUser();
        $member  = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $member->id,
            'role'       => 'member',
            'joined_at'  => now(),
        ]);

        $this->actingAs($creator)
            ->deleteJson($this->url("/channels/{$channel->id}/members/{$member->id}"))
            ->assertOk()
            ->assertJsonPath('members_count', 1);

        $this->assertDatabaseMissing('channel_members', [
            'channel_id' => $channel->id,
            'user_id'    => $member->id,
        ]);
    }

    public function test_the_creator_cannot_be_removed(): void
    {
        $creator = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        $this->actingAs($creator)
            ->deleteJson($this->url("/channels/{$channel->id}/members/{$creator->id}"))
            ->assertStatus(422);
    }

    public function test_member_list_hides_addable_users_from_non_managers(): void
    {
        $creator = $this->tenantUser();
        $member  = $this->tenantUser();
        $this->tenantUser(); // a third workspace user who could be added
        $channel = $this->privateChannel($creator);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $member->id,
            'role'       => 'member',
            'joined_at'  => now(),
        ]);

        $this->actingAs($member)
            ->getJson($this->url("/channels/{$channel->id}/members"))
            ->assertOk()
            ->assertJsonPath('can_manage', false)
            ->assertJsonCount(0, 'addable');

        $this->actingAs($creator)
            ->getJson($this->url("/channels/{$channel->id}/members"))
            ->assertOk()
            ->assertJsonPath('can_manage', true)
            ->assertJsonCount(1, 'addable');
    }

    public function test_non_members_cannot_list_a_private_channels_members(): void
    {
        $creator  = $this->tenantUser();
        $outsider = $this->tenantUser();
        $channel  = $this->privateChannel($creator);

        $this->actingAs($outsider)
            ->getJson($this->url("/channels/{$channel->id}/members"))
            ->assertForbidden();
    }

    public function test_a_plain_member_can_leave(): void
    {
        $creator = $this->tenantUser();
        $member  = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $member->id,
            'role'       => 'member',
            'joined_at'  => now(),
        ]);

        $this->actingAs($member)
            ->post($this->url("/channels/{$channel->id}/leave"))
            ->assertRedirect();

        $this->assertDatabaseMissing('channel_members', [
            'channel_id' => $channel->id,
            'user_id'    => $member->id,
        ]);
    }

    public function test_the_last_admin_cannot_leave_while_others_remain(): void
    {
        $creator = $this->tenantUser();
        $member  = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $member->id,
            'role'       => 'member',
            'joined_at'  => now(),
        ]);

        // Leaving would strand $member in a channel nobody can manage.
        $this->actingAs($creator)
            ->post($this->url("/channels/{$channel->id}/leave"))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('channel_members', [
            'channel_id' => $channel->id,
            'user_id'    => $creator->id,
        ]);
    }

    public function test_an_admin_can_leave_once_another_admin_remains(): void
    {
        $creator = $this->tenantUser();
        $second  = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $second->id,
            'role'       => 'admin',
            'joined_at'  => now(),
        ]);

        $this->actingAs($creator)
            ->post($this->url("/channels/{$channel->id}/leave"))
            ->assertRedirect();

        $this->assertDatabaseMissing('channel_members', [
            'channel_id' => $channel->id,
            'user_id'    => $creator->id,
        ]);
    }

    public function test_the_last_member_can_leave(): void
    {
        $creator = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        // Nobody left to strand, so this is allowed.
        $this->actingAs($creator)
            ->post($this->url("/channels/{$channel->id}/leave"))
            ->assertRedirect();

        $this->assertDatabaseMissing('channel_members', [
            'channel_id' => $channel->id,
            'user_id'    => $creator->id,
        ]);
    }

    public function test_a_creator_who_left_can_no_longer_manage_members(): void
    {
        $creator = $this->tenantUser();
        $second  = $this->tenantUser();
        $target  = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $second->id,
            'role'       => 'admin',
            'joined_at'  => now(),
        ]);

        $this->actingAs($creator)->post($this->url("/channels/{$channel->id}/leave"));

        // created_by still points at them, but they are no longer inside.
        $this->actingAs($creator)
            ->postJson($this->url("/channels/{$channel->id}/members"), ['user_id' => $target->id])
            ->assertForbidden();
    }

    public function test_adding_an_existing_member_does_not_duplicate_them(): void
    {
        $creator = $this->tenantUser();
        $invitee = $this->tenantUser();
        $channel = $this->privateChannel($creator);

        foreach ([1, 2] as $_) {
            $this->actingAs($creator)
                ->postJson($this->url("/channels/{$channel->id}/members"), ['user_id' => $invitee->id])
                ->assertCreated();
        }

        $this->assertSame(1, ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $invitee->id)
            ->count());
    }
}
