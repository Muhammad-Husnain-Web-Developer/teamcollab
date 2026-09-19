<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(): Response
    {
        $members = User::with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn(User $user) => [
                'id'           => $user->id,
                'name'         => $user->name,
                'display_name' => $user->display_name,
                'email'        => $user->email,
                'avatar_url'   => $user->avatar_url,
                'status'       => $user->status,
                'status_text'  => $user->status_text,
                'status_emoji' => $user->status_emoji,
                'roles'        => $user->roles->pluck('name'),
                'last_seen_at' => $user->last_seen_at?->toISOString(),
                'is_active'    => $user->is_active,
            ]);

        return Inertia::render('Members/Index', [
            'members' => $members,
        ]);
    }

    public function show(User $user): Response
    {
        $channels = [];

        try {
            $channels = \App\Models\Tenant\Channel::whereHas(
                'members',
                fn($q) => $q->where('users.id', $user->id)
            )->select(['id', 'name', 'type'])->get();
        } catch (\Exception $e) {
            // Channels not available in this context
        }

        return Inertia::render('Members/Show', [
            'member' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'display_name' => $user->display_name,
                'email'        => $user->email,
                'avatar_url'   => $user->avatar_url,
                'bio'          => $user->bio,
                'timezone'     => $user->timezone,
                'status'       => $user->status,
                'status_text'  => $user->status_text,
                'status_emoji' => $user->status_emoji,
                'roles'        => $user->roles->pluck('name'),
                'last_seen_at' => $user->last_seen_at?->toISOString(),
                'is_active'    => $user->is_active,
                'channels'     => $channels,
            ],
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $this->authorize('assignRoles', $user);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'member', 'guest'])],
        ]);

        $user->syncRoles([$validated['role']]);

        return response()->json([
            'user'  => $user->only(['id', 'name', 'email']),
            'roles' => $user->roles->pluck('name'),
        ]);
    }

    public function remove(User $user): RedirectResponse
    {
        $this->authorize('removeMember', $user);

        $currentUser = auth()->user();

        if ($currentUser->id === $user->id) {
            return back()->withErrors(['user' => 'You cannot remove yourself from the workspace.']);
        }

        $user->update(['is_active' => false]);

        return redirect()->route('members.index')
            ->with('success', "{$user->name} has been removed from the workspace.");
    }
}
