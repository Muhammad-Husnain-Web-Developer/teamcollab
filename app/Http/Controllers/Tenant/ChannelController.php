<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\CreateChannelRequest;
use App\Http\Requests\Channel\UpdateChannelRequest;
use App\Models\Tenant\Channel;
use App\Services\ChannelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function __construct(private readonly ChannelService $channelService)
    {
    }

    public function index(): Response
    {
        $user = auth()->user();

        $channels = $this->channelService->getVisibleChannels($user);

        return Inertia::render('Channel/Index', [
            'channels' => $channels->map(fn(Channel $channel) => [
                'id'           => $channel->id,
                'name'         => $channel->name,
                'description'  => $channel->description,
                'type'         => $channel->type,
                'is_archived'  => $channel->is_archived,
                'member_count' => $channel->members_count ?? 0,
                'is_member'    => $channel->channelMembers->contains('user_id', $user->id),
            ]),
        ]);
    }

    public function store(CreateChannelRequest $request): RedirectResponse
    {
        $channel = $this->channelService->create($request->validated(), auth()->user());

        return redirect()->route('channels.show', $channel)
            ->with('success', 'Channel created successfully.');
    }

    public function show(Channel $channel): Response
    {
        $this->authorize('view', $channel);

        $messages = $channel->messages()
            ->with(['user', 'reactions', 'files', 'thread'])
            ->latest()
            ->paginate(50);

        $memberUserIds = $channel->channelMembers()->pluck('user_id');

        $members = \App\Models\User::whereIn('id', $memberUserIds)
            ->select(['id', 'name', 'display_name', 'avatar', 'status'])
            ->get()
            ->map(fn($user) => [
                'id'           => $user->id,
                'name'         => $user->name,
                'display_name' => $user->display_name,
                'avatar_url'   => $user->avatar_url,
                'status'       => $user->status,
            ]);

        return Inertia::render('Channel/Show', [
            'channel'  => [
                'id'          => $channel->id,
                'name'        => $channel->name,
                'description' => $channel->description,
                'type'        => $channel->type,
                'is_archived' => $channel->is_archived,
                'created_by'  => $channel->created_by,
            ],
            'messages' => $messages,
            'members'  => $members,
        ]);
    }

    public function update(UpdateChannelRequest $request, Channel $channel): RedirectResponse
    {
        $this->authorize('update', $channel);

        $this->channelService->update($channel, $request->validated());

        return back()->with('success', 'Channel updated successfully.');
    }

    public function destroy(Channel $channel): RedirectResponse
    {
        $this->authorize('delete', $channel);

        $this->channelService->delete($channel);

        return redirect()->route('channels.index')
            ->with('success', 'Channel deleted successfully.');
    }

    public function archive(Channel $channel): RedirectResponse
    {
        $this->authorize('update', $channel);

        $this->channelService->archive($channel);

        return back()->with('success', 'Channel archived successfully.');
    }

    public function join(Channel $channel): RedirectResponse
    {
        $user = auth()->user();

        if ($channel->type === 'private') {
            abort(403, 'You cannot join a private channel without an invitation.');
        }

        // Not $channel->members()->attach(): that pivot query runs on the
        // related User model's central connection, where channel_members does
        // not exist. The service writes to the tenant database.
        $this->channelService->addMember($channel, $user);

        return back()->with('success', "You've joined #{$channel->name}.");
    }

    public function leave(Channel $channel): RedirectResponse
    {
        $user = auth()->user();

        $membership = \App\Models\Tenant\ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return back()->with('error', "You're not a member of #{$channel->name}.");
        }

        // Someone must be left who can manage membership, otherwise a private
        // channel becomes impossible to add anyone to ever again.
        if ($this->isLastManager($channel, $user)) {
            return back()->with(
                'error',
                'Make someone else an admin before you leave — you are the last one who can manage this channel.'
            );
        }

        // Same reason as join(): go through the service so the delete lands on
        // the tenant connection and members_count stays in step.
        $this->channelService->removeMember($channel, $user);

        try {
            event(new \App\Events\MemberLeft(
                $channel->id,
                $user->id,
                $user->display_name ?? $user->name,
                tenant('id'),
            ));
        } catch (\Throwable) {
            // Broadcast failure is non-fatal — the user has already left.
        }

        return redirect()->route('channels.index')
            ->with('success', "You've left #{$channel->name}.");
    }

    /**
     * Would this user leaving strand the channel with nobody able to manage it?
     *
     * Only a concern while other members remain — the last person out is free
     * to go, since there is no one left to be locked out.
     */
    private function isLastManager(Channel $channel, \App\Models\User $user): bool
    {
        // A plain member leaving can never strand anyone.
        if (! $user->can('manageMembers', $channel)) {
            return false;
        }

        $others = \App\Models\Tenant\ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', '!=', $user->id)
            ->get();

        if ($others->isEmpty()) {
            return false;
        }

        $anotherManagerRemains = $others->contains(
            fn ($m) => $m->role === 'admin' || $channel->created_by === $m->user_id
        );

        return ! $anotherManagerRemains;
    }

    /**
     * Toggle mute for this channel. Muted channels still receive messages but
     * raise no notification.
     */
    public function toggleMute(Channel $channel): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $channel);

        $member = $channel->channelMembers()
            ->where('user_id', auth()->id())
            ->first();

        abort_unless((bool) $member, 403, 'Join the channel before muting it.');

        $member->update(['is_muted' => ! $member->is_muted]);

        return response()->json(['is_muted' => (bool) $member->is_muted]);
    }
}
