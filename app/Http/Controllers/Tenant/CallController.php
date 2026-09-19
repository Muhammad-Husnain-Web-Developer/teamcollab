<?php

namespace App\Http\Controllers\Tenant;

use App\Events\WebRtcSignal;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Call;
use App\Services\CallService;
use App\Services\IceServerService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Call signaling broadcasts straight to user IDs with no other access check
 * (see routes/channels.php — the private channel only verifies the listener
 * matches), so CallService confirms workspace/call membership before every
 * broadcast. A 1-on-1 call is simply the count(callee_ids) === 1 case of the
 * same mesh flow — there is no separate code path.
 */
class CallController extends Controller
{
    public function __construct(private readonly CallService $calls)
    {
    }

    /**
     * Fetched by the client right before it opens peer connections; TURN
     * credentials (when configured) are minted per user and expire, so this
     * is never cached beyond `ttl`.
     */
    public function iceServers(IceServerService $ice): JsonResponse
    {
        return response()->json([
            'iceServers' => $ice->forUser(auth()->user()),
            'ttl'        => $ice->ttl(),
        ]);
    }

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'callee_ids'   => ['required', 'array', 'min:1', 'max:' . (CallService::MAX_PARTICIPANTS - 1)],
            'callee_ids.*' => ['integer'],
            'call_type'    => ['required', 'in:audio,video'],
            'context_type' => ['nullable', 'in:dm,channel,adhoc'],
            'context_id'   => ['nullable', 'integer'],
        ]);

        $call = $this->calls->initiate(
            auth()->user(),
            $data['callee_ids'],
            $data['call_type'],
            $data['context_type'] ?? null,
            $data['context_id'] ?? null,
        );

        return response()->json([
            'callId'       => $call->id,
            'participants' => $this->calls->roster($call),
        ]);
    }

    public function accept(Call $call): JsonResponse
    {
        return response()->json([
            'ok'           => true,
            'participants' => $this->calls->accept($call, auth()->user()),
        ]);
    }

    public function reject(Call $call): JsonResponse
    {
        $this->calls->reject($call, auth()->user());

        return response()->json(['ok' => true]);
    }

    public function leave(Call $call, NotificationService $notifications): JsonResponse
    {
        $this->calls->leave($call, auth()->user(), $notifications);

        return response()->json(['ok' => true]);
    }

    public function recording(Request $request, Call $call): JsonResponse
    {
        $data = $request->validate(['recording' => ['required', 'boolean']]);

        $this->calls->setRecording($call, auth()->user(), $data['recording']);

        return response()->json(['ok' => true]);
    }

    public function signal(Request $request, Call $call): JsonResponse
    {
        $data = $request->validate([
            'target_user_id' => ['required', 'integer'],
            'signal'         => ['required', 'array'],
        ]);

        $this->calls->assertCanSignal($call, auth()->user(), (int) $data['target_user_id']);

        event(new WebRtcSignal(
            $call->id,
            (int) $data['target_user_id'],
            $data['signal'],
            auth()->id(),
        ));

        return response()->json(['ok' => true]);
    }
}
