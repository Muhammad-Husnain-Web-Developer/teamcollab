<?php

namespace App\Http\Controllers\Tenant;

use App\Events\UserPresenceUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PresenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['online', 'away', 'busy', 'offline'])],
        ]);

        $user = auth()->user();

        $user->update([
            'status'       => $validated['status'],
            'last_seen_at' => now(),
        ]);

        Cache::put("user.{$user->id}.presence", $validated['status'], now()->addMinutes(10));

        broadcast(new UserPresenceUpdated($user))->toOthers();

        return response()->json([
            'status'    => $user->status,
            'user_id'   => $user->id,
            'updated_at' => now()->toISOString(),
        ]);
    }

    public function online(): JsonResponse
    {
        return $this->setStatus('online');
    }

    public function away(): JsonResponse
    {
        return $this->setStatus('away');
    }

    public function offline(): JsonResponse
    {
        return $this->setStatus('offline');
    }

    private function setStatus(string $status): JsonResponse
    {
        $user = auth()->user();

        $user->update([
            'status'       => $status,
            'last_seen_at' => now(),
        ]);

        Cache::put("user.{$user->id}.presence", $status, now()->addMinutes(10));

        broadcast(new UserPresenceUpdated($user))->toOthers();

        return response()->json([
            'status'     => $user->status,
            'user_id'    => $user->id,
            'updated_at' => now()->toISOString(),
        ]);
    }
}
