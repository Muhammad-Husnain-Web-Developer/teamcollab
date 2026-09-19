<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\TenantNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        $notifications = TenantNotification::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->through(fn(TenantNotification $n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'data'       => $n->data,
                'read_at'    => $n->read_at?->toISOString(),
                'created_at' => $n->created_at->toISOString(),
            ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread_count'  => TenantNotification::where('user_id', auth()->id())
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    /**
     * JSON feed for the notification bell dropdown.
     */
    public function feed(): JsonResponse
    {
        $notifications = TenantNotification::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->map(fn(TenantNotification $n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'data'       => $n->data,
                'read_at'    => $n->read_at?->toISOString(),
                'created_at' => $n->created_at->toISOString(),
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => TenantNotification::where('user_id', auth()->id())
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markRead(TenantNotification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->update(['read_at' => now(), 'is_read' => true]);

        return response()->json([
            'id'      => $notification->id,
            'read_at' => $notification->read_at->toISOString(),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $count = TenantNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'is_read' => true]);

        return response()->json([
            'marked_count' => $count,
        ]);
    }

    public function destroy(TenantNotification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->delete();

        return response()->json(['deleted' => true]);
    }
}
