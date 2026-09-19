<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\ScheduleMessageRequest;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\ScheduledMessage;
use App\Services\SchedulingService;
use Illuminate\Http\JsonResponse;

class ScheduledMessageController extends Controller
{
    public function __construct(private readonly SchedulingService $scheduling)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'scheduled_messages' => $this->scheduling->listPending(auth()->user())->map->toPendingArray()->values(),
        ]);
    }

    public function storeForChannel(ScheduleMessageRequest $request, Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $scheduled = $this->scheduling->schedule(
            array_merge($request->validated(), ['channel_id' => $channel->id]),
            auth()->user(),
        );

        return response()->json(['scheduled_message' => $scheduled->toPendingArray()], 201);
    }

    public function storeForConversation(ScheduleMessageRequest $request, Conversation $conversation): JsonResponse
    {
        abort_unless(
            $conversation->participantEntries()->where('user_id', auth()->id())->exists(),
            403
        );

        $scheduled = $this->scheduling->schedule(
            array_merge($request->validated(), ['conversation_id' => $conversation->id]),
            auth()->user(),
        );

        return response()->json(['scheduled_message' => $scheduled->toPendingArray()], 201);
    }

    public function destroy(ScheduledMessage $scheduledMessage): JsonResponse
    {
        $this->scheduling->cancel($scheduledMessage, auth()->user());

        return response()->json(['cancelled' => true]);
    }
}
