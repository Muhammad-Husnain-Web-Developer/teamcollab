<?php

declare(strict_types=1);

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\ChannelController;
use App\Http\Controllers\Tenant\ChannelMemberController;
use App\Http\Controllers\Tenant\ConversationController;
use App\Http\Controllers\Tenant\EmojiController;
use App\Http\Controllers\Tenant\FileController;
use App\Http\Controllers\Tenant\GifController;
use App\Http\Controllers\Tenant\PushSubscriptionController;
use App\Http\Controllers\Tenant\ScheduledMessageController;
use App\Http\Controllers\Tenant\LinkPreviewController;
use App\Http\Controllers\Tenant\MemberController;
use App\Http\Controllers\Tenant\MessageController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\PresenceController;
use App\Http\Controllers\Tenant\ReactionController;
use App\Http\Controllers\Tenant\SearchController;
use App\Http\Controllers\Tenant\TenantAuthController;
use App\Http\Controllers\Tenant\CallController;
use App\Http\Controllers\Tenant\ThreadController;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureTenantMiddleware;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| All routes here run within tenant context — tenancy is initialized by
| domain. The EnsureTenantMiddleware confirms the authenticated user
| belongs to this tenant. CheckSubscription gates all tenant routes.
|
*/

// ── Tenant Auth Routes (guests only) ─────────────────────────────────────
// Domain constraint prevents these routes from overwriting the central login/register
// routes in Laravel's route collection (different domain+URI key).
Route::domain('{__tenant}.'.env('TENANT_DOMAIN', 'localhost'))
    ->middleware([
        'web',
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
        'guest',
    ])->group(function () {
        Route::get('/login',    [TenantAuthController::class, 'showLogin'])->name('tenant.login');
        // Throttled: unauthenticated POSTs are the credential-stuffing surface.
        Route::post('/login',   [TenantAuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('/register', [TenantAuthController::class, 'showRegister'])->name('tenant.register');
        Route::post('/register',[TenantAuthController::class, 'register'])->middleware('throttle:5,1');
    });

// Tenant logout
Route::domain('{__tenant}.'.env('TENANT_DOMAIN', 'localhost'))
    ->middleware([
        'web',
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
        'auth',
    ])->post('/logout', [TenantAuthController::class, 'logout'])->name('tenant.logout');

// ── Tenant App Routes (authenticated) ────────────────────────────────────
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'auth',
    EnsureTenantMiddleware::class,
    CheckSubscription::class,
])->group(function () {

    // Dashboard
    Route::get('/', DashboardController::class)->name('dashboard');

    // Analytics
    Route::get('/analytics', AnalyticsController::class)->name('analytics');

    // -------------------------------------------------------------------------
    // Channels
    // -------------------------------------------------------------------------
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
    Route::get('/channels/{channel}', [ChannelController::class, 'show'])->name('channels.show');
    Route::put('/channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
    Route::delete('/channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');
    Route::post('/channels/{channel}/archive', [ChannelController::class, 'archive'])->name('channels.archive');
    Route::post('/channels/{channel}/join', [ChannelController::class, 'join'])->name('channels.join');

    // Channel membership (private channels have no self-serve join)
    Route::get('/channels/{channel}/members', [ChannelMemberController::class, 'index'])
        ->name('channels.members.index');
    Route::post('/channels/{channel}/members', [ChannelMemberController::class, 'store'])
        ->name('channels.members.store');
    Route::delete('/channels/{channel}/members/{user}', [ChannelMemberController::class, 'destroy'])
        ->name('channels.members.destroy');
    Route::post('/channels/{channel}/leave', [ChannelController::class, 'leave'])->name('channels.leave');
    Route::post('/channels/{channel}/mute', [ChannelController::class, 'toggleMute'])->name('channels.mute');

    // -------------------------------------------------------------------------
    // Messages
    // -------------------------------------------------------------------------
    Route::get('/channels/{channel}/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/channels/{channel}/pinned', [MessageController::class, 'pinned'])->name('messages.pinned');
    // Generous enough for fast typists and paste-heavy use, low enough that a
    // script cannot flood a channel or the broadcast queue.
    Route::post('/channels/{channel}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('messages.store');
    Route::post('/channels/{channel}/typing', [MessageController::class, 'typing'])->name('channels.typing');
    Route::post('/channels/{channel}/read', [MessageController::class, 'markRead'])->name('channels.read');
    Route::put('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    Route::post('/messages/{message}/pin', [MessageController::class, 'pin'])->name('messages.pin');
    Route::post('/messages/{message}/unpin', [MessageController::class, 'unpin'])->name('messages.unpin');
    Route::post('/messages/{message}/forward', [MessageController::class, 'forward'])
        ->middleware('throttle:30,1')
        ->name('messages.forward');
    Route::post('/channels/{channel}/messages/schedule', [ScheduledMessageController::class, 'storeForChannel'])
        ->middleware('throttle:30,1')
        ->name('channels.messages.schedule');

    // Reactions
    Route::post('/messages/{message}/react', [ReactionController::class, 'toggle'])->name('messages.react');

    // -------------------------------------------------------------------------
    // Threads
    // -------------------------------------------------------------------------
    Route::get('/messages/{message}/thread', [ThreadController::class, 'show'])->name('threads.show');
    Route::post('/messages/{message}/thread', [ThreadController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('threads.store');

    // -------------------------------------------------------------------------
    // Direct Messages
    // -------------------------------------------------------------------------
    Route::get('/dm', [ConversationController::class, 'index'])->name('dm.index');
    Route::post('/dm', [ConversationController::class, 'store'])->name('dm.store');
    Route::get('/dm/{conversation}', [ConversationController::class, 'show'])->name('dm.show');
    Route::get('/dm/{conversation}/messages', [ConversationController::class, 'getMessages'])->name('dm.messages.index');
    Route::post('/dm/{conversation}/messages', [ConversationController::class, 'sendMessage'])
        ->middleware('throttle:60,1')
        ->name('dm.messages.store');
    Route::post('/dm/{conversation}/typing', [ConversationController::class, 'typing'])->name('dm.typing');
    Route::post('/dm/{conversation}/read', [ConversationController::class, 'markRead'])->name('dm.read');
    Route::post('/dm/{conversation}/mute', [ConversationController::class, 'toggleMute'])->name('dm.mute');
    Route::post('/dm/{conversation}/messages/schedule', [ScheduledMessageController::class, 'storeForConversation'])
        ->middleware('throttle:30,1')
        ->name('dm.messages.schedule');

    // -------------------------------------------------------------------------
    // Scheduled messages
    // -------------------------------------------------------------------------
    Route::get('/scheduled-messages', [ScheduledMessageController::class, 'index'])->name('scheduled-messages.index');
    Route::delete('/scheduled-messages/{scheduledMessage}', [ScheduledMessageController::class, 'destroy'])
        ->name('scheduled-messages.destroy');

    // -------------------------------------------------------------------------
    // Files
    // -------------------------------------------------------------------------
    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    // Uploads are the most expensive endpoint (disk + scanning), so tighter.
    Route::post('/files', [FileController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('files.store');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
    Route::get('/files/{file}/view', [FileController::class, 'view'])->name('files.view');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');

    // -------------------------------------------------------------------------
    // Link previews
    // -------------------------------------------------------------------------
    Route::get('/link-previews/{linkPreview}/image', [LinkPreviewController::class, 'image'])
        ->name('link-previews.image');

    // -------------------------------------------------------------------------
    // Custom emoji
    // -------------------------------------------------------------------------
    Route::get('/emoji-settings', [EmojiController::class, 'page'])->name('emojis.page');
    Route::get('/emojis', [EmojiController::class, 'index'])->name('emojis.index');
    Route::post('/emojis', [EmojiController::class, 'store'])->name('emojis.store');
    Route::delete('/emojis/{emoji}', [EmojiController::class, 'destroy'])->name('emojis.destroy');
    Route::get('/emojis/{emoji}/image', [EmojiController::class, 'image'])->name('emojis.image');

    // -------------------------------------------------------------------------
    // GIFs (Giphy proxy — never exposes the API key to the client)
    // -------------------------------------------------------------------------
    Route::get('/gifs/search', [GifController::class, 'search'])
        ->middleware('throttle:30,1')
        ->name('gifs.search');
    Route::get('/gifs/trending', [GifController::class, 'trending'])
        ->middleware('throttle:30,1')
        ->name('gifs.trending');

    // -------------------------------------------------------------------------
    // Members
    // -------------------------------------------------------------------------
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/{user}', [MemberController::class, 'show'])->name('members.show');
    Route::put('/members/{user}/role', [MemberController::class, 'updateRole'])->name('members.role');
    Route::delete('/members/{user}', [MemberController::class, 'remove'])->name('members.remove');

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    Route::get('/search', SearchController::class)->name('search');

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // -------------------------------------------------------------------------
    // Push subscriptions (Web Push — background/tab-closed delivery)
    // -------------------------------------------------------------------------
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    // -------------------------------------------------------------------------
    // Presence
    // -------------------------------------------------------------------------
    Route::post('/presence/update', [PresenceController::class, 'update'])->name('presence.update');
    Route::post('/presence/online', [PresenceController::class, 'online'])->name('presence.online');
    Route::post('/presence/away', [PresenceController::class, 'away'])->name('presence.away');
    Route::post('/presence/offline', [PresenceController::class, 'offline'])->name('presence.offline');

    // -------------------------------------------------------------------------
    // WebRTC Calls (mesh — 1-on-1 is the two-participant case)
    // -------------------------------------------------------------------------
    Route::post('/calls', [CallController::class, 'initiate'])->name('calls.initiate');
    Route::post('/calls/{call}/accept', [CallController::class, 'accept'])->name('calls.accept');
    Route::post('/calls/{call}/reject', [CallController::class, 'reject'])->name('calls.reject');
    Route::post('/calls/{call}/leave', [CallController::class, 'leave'])->name('calls.leave');
    Route::post('/calls/{call}/recording', [CallController::class, 'recording'])->name('calls.recording');
    // Pairwise even in a group call — a mesh is just N pairwise connections.
    Route::post('/calls/{call}/signal', [CallController::class, 'signal'])->name('calls.signal');

    // -------------------------------------------------------------------------
    // Profile
    // -------------------------------------------------------------------------
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::put('/profile/status', [ProfileController::class, 'updateStatus'])->name('profile.status');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');
});
