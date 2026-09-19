<?php

namespace App\Http\Controllers\Tenant;

use App\Events\ChannelMemberAdded;
use App\Events\MemberLeft;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\User;
use App\Services\ChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Managing who is in a channel.
 *
 * Private channels have no self-serve join, so this is the only way anyone
 * other than the creator gets in.
 */
class ChannelMemberController extends Controller
{
    public function __construct(private readonly ChannelService $channels)
    {
    }

    /**
     * Members of the channel, plus who could still be added.
     */
    public function index(Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $memberRows = ChannelMember::where('channel_id', $channel->id)
            ->get()
            ->keyBy('user_id');

        $members = User::whereIn('id', $memberRows->keys())
            ->get(['id', 'name', 'display_name', 'avatar', 'status'])
            ->map(fn (User $user) => $this->present($user, $memberRows[$user->id]->role ?? 'member'))
            ->sortBy('name')
            ->values();

        // Everyone in the workspace who is not already in this channel.
        $addable = collect();
        if (auth()->user()->can('manageMembers', $channel)) {
            $workspaceUserIds = DB::connection('mysql')->table('tenant_users')
                ->where('tenant_id', tenant('id'))
                ->pluck('user_id');

            $addable = User::whereIn('id', $workspaceUserIds)
                ->whereNotIn('id', $memberRows->keys())
                ->get(['id', 'name', 'display_name', 'avatar', 'status'])
                ->map(fn (User $user) => $this->present($user, null))
                ->sortBy('name')
                ->values();
        }

        return response()->json([
            'members'        => $members,
            'addable'        => $addable,
            'can_manage'     => auth()->user()->can('manageMembers', $channel),
            'members_count'  => $members->count(),
        ]);
    }

    public function store(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('manageMembers', $channel);

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'role'    => ['nullable', 'in:member,admin'],
        ]);

        $user = User::find($data['user_id']);
        abort_unless((bool) $user, 404, 'User not found.');

        // Only people already in this workspace may be added to its channels.
        $inWorkspace = DB::connection('mysql')->table('tenant_users')
            ->where('tenant_id', tenant('id'))
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($inWorkspace, 403, 'That user is not a member of this workspace.');

        $this->channels->addMember($channel, $user, $data['role'] ?? 'member');

        $member = $this->present($user, $data['role'] ?? 'member');

        try {
            event(new ChannelMemberAdded($channel->id, $member, auth()->id(), tenant('id')));
        } catch (\Throwable) {
            // Broadcast failure is non-fatal — membership is already saved.
        }

        return response()->json([
            'member'        => $member,
            'members_count' => ChannelMember::where('channel_id', $channel->id)->count(),
        ], 201);
    }

    public function destroy(Channel $channel, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $channel);

        // The creator anchors the channel; removing them would leave it
        // unmanageable once the last admin is gone.
        abort_if(
            $channel->created_by === $user->id,
            422,
            'The channel creator cannot be removed.'
        );

        $this->channels->removeMember($channel, $user);

        try {
            event(new MemberLeft(
                $channel->id,
                $user->id,
                $user->display_name ?? $user->name,
                tenant('id'),
                auth()->id(), // removed by an admin, not a self-initiated leave
            ));
        } catch (\Throwable) {
            // Non-fatal.
        }

        return response()->json([
            'removed_user_id' => $user->id,
            'members_count'   => ChannelMember::where('channel_id', $channel->id)->count(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function present(User $user, ?string $role): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'display_name' => $user->display_name,
            'avatar_url'   => $user->avatar_url,
            'status'       => $user->status,
            'role'         => $role,
        ];
    }
}
