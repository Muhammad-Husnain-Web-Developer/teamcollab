<?php

namespace App\Http\Middleware;

use App\Models\Tenant\Channel;
use App\Models\Tenant\TenantNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $sharedData = [
            ...parent::share($request),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],
        ];

        if (Auth::check()) {
            $user = $request->user();

            // VAPID public key is safe to expose client-side (unlike a
            // third-party API key) — it's how the browser verifies push
            // messages actually came from this server, not a secret.
            $sharedData['vapid_public_key'] = config('webpush.vapid.public_key');

            $sharedData['auth'] = [
                'user' => [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'display_name' => $user->display_name,
                    'email'        => $user->email,
                    'avatar_url'   => $user->avatar_url,
                    'status'       => $user->status,
                    'status_emoji' => $user->status_emoji,
                    'status_text'  => $user->status_text,
                    'timezone'     => $user->timezone,
                    'preferences'  => $user->preferences,
                    'roles'        => $user->getRoleNames(),
                    'permissions'  => $user->getAllPermissions()->pluck('name'),
                ],
            ];

            // Share tenant-scoped data only when tenancy is initialized
            if (app(\Stancl\Tenancy\Tenancy::class)->initialized) {
                $sharedData['tenant'] = fn () => tenant() ? [
                    'id'       => tenant('id'),
                    'name'     => tenant('name'),
                    'slug'     => tenant('slug'),
                    'logo_url' => tenant()->logo_url,
                ] : null;

                $sharedData['channels'] = function () use ($user) {
                    $unread = app(\App\Services\UnreadService::class)->channelCounts($user);

                    // Mute state comes from the caller's own channel_members row.
                    $muted = DB::table('channel_members')
                        ->where('user_id', $user->id)
                        ->pluck('is_muted', 'channel_id');

                    return Channel::active()
                        ->where(function ($q) use ($user) {
                            $q->whereHas('channelMembers', fn ($q) => $q->where('user_id', $user->id))
                              ->orWhere('type', 'public');
                        })
                        ->withCount('channelMembers as members_count')
                        ->orderBy('is_default', 'desc')
                        ->orderBy('name')
                        ->get()
                        ->map(fn ($ch) => [
                            'id'            => $ch->id,
                            'name'          => $ch->name,
                            'slug'          => $ch->slug,
                            'type'          => $ch->type,
                            'is_archived'   => $ch->is_archived,
                            'is_default'    => $ch->is_default,
                            'members_count' => $ch->members_count,
                            'unread_count'  => $unread[$ch->id] ?? 0,
                            'is_muted'      => (bool) ($muted[$ch->id] ?? false),
                        ]);
                };

                $sharedData['unread_notifications_count'] = fn () =>
                    TenantNotification::where('user_id', $user->id)
                        ->where('is_read', false)
                        ->count();

                $sharedData['workspaceMembers'] = fn () => $this->getWorkspaceMembers($user);

                // Mirrors WorkspacePolicy::isAdmin() — the frontend uses this
                // purely to show/hide admin-only UI (e.g. custom emoji upload);
                // the actual action is still gated server-side via the policy.
                $sharedData['is_workspace_admin'] = fn () => in_array(
                    DB::connection('mysql')->table('tenant_users')
                        ->where('tenant_id', tenant('id'))
                        ->where('user_id', $user->id)
                        ->value('role'),
                    ['owner', 'admin'],
                    true,
                );
            }
        }

        return $sharedData;
    }

    private function getWorkspaceMembers(User $currentUser): array
    {
        // All tenant member user IDs (central DB)
        $memberIds = DB::connection('mysql')
            ->table('tenant_users')
            ->where('tenant_id', tenant('id'))
            ->where('user_id', '!=', $currentUser->id)
            ->pluck('user_id');

        // Recent direct conversations on tenant DB (default connection = tenant)
        $convIds = DB::table('conversation_participants')
            ->where('user_id', $currentUser->id)
            ->pluck('conversation_id');

        $conversationByPartner = collect();
        if ($convIds->isNotEmpty()) {
            $conversationByPartner = DB::table('conversation_participants as cp')
                ->join('conversations as c', 'cp.conversation_id', '=', 'c.id')
                ->whereIn('cp.conversation_id', $convIds)
                ->where('cp.user_id', '!=', $currentUser->id)
                ->where('c.type', 'direct')
                ->whereNull('c.deleted_at')
                ->select(['cp.conversation_id', 'cp.user_id', 'c.updated_at'])
                ->orderByDesc('c.updated_at')
                ->get()
                ->keyBy('user_id');
        }

        $unreadByConversation = app(\App\Services\UnreadService::class)
            ->conversationCounts($currentUser);

        return User::whereIn('id', $memberIds)
            ->get(['id', 'name', 'display_name', 'email', 'avatar', 'status', 'last_seen_at'])
            ->map(function (User $m) use ($conversationByPartner, $unreadByConversation) {
                $conversationId = $conversationByPartner[$m->id]?->conversation_id ?? null;

                return [
                    'id'              => $m->id,
                    'name'            => $m->name,
                    'display_name'    => $m->display_name,
                    'email'           => $m->email,
                    'avatar_url'      => $m->avatar_url,
                    'status'          => $m->status,
                    'last_seen_at'    => $m->last_seen_at?->toISOString(),
                    'conversation_id' => $conversationId,
                    'last_message_at' => $conversationByPartner[$m->id]?->updated_at ?? null,
                    'unread_count'    => $conversationId
                        ? ($unreadByConversation[$conversationId] ?? 0)
                        : 0,
                ];
            })
            ->values()
            ->all();
    }
}
