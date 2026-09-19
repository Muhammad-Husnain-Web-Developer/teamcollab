<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\ForwardMessageRequest;
use App\Http\Requests\Message\SendMessageRequest;
use App\Http\Requests\Message\UpdateMessageRequest;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Message;
use App\Services\MessageService;
use App\Services\SlashCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly SlashCommandService $slashCommands,
    ) {
    }

    public function index(Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $messages = $channel->messages()
            ->with(['user', 'reactions.user', 'files', 'thread', 'parent.user'])
            ->latest()
            ->paginate(50);

        return response()->json($messages);
    }

    /**
     * Pinned messages for the channel info panel.
     */
    public function pinned(Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $messages = $channel->messages()
            ->pinned()
            ->with(['user'])
            ->latest('pinned_at')
            ->get();

        return response()->json(['messages' => $messages]);
    }

    public function store(SendMessageRequest $request, Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $slash = $this->slashCommands->parse((string) $request->input('body'));
        if ($slash) {
            return response()->json(
                $this->slashCommands->execute($slash['command'], $slash['args'], $channel, auth()->user())
            );
        }

        $message = $this->messageService->send(
            array_merge($request->validated(), ['channel_id' => $channel->id]),
            auth()->user()
        );

        return response()->json([
            'message' => $message->load(['user', 'reactions', 'files']),
        ], 201);
    }

    /**
     * Mark this channel read up to now, clearing the caller's unread badge.
     */
    public function markRead(Channel $channel, \App\Services\UnreadService $unread): JsonResponse
    {
        $this->authorize('view', $channel);

        // Non-members (viewing a public channel they haven't joined) have no
        // channel_members row and therefore no unread state to clear.
        $unread->markChannelRead($channel->id, auth()->user());

        return response()->json(['unread_count' => 0]);
    }

    /**
     * Broadcast that the current user is typing in this channel.
     * Fire-and-forget: never fails the caller if the broadcaster is down.
     */
    public function typing(Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $user = auth()->user();

        try {
            event(new \App\Events\UserTyping(
                $user->id,
                $user->display_name ?? $user->name,
                $channel->id,
                null,
                tenant('id'),
            ));
        } catch (\Throwable) {
            // Typing indicators are cosmetic — swallow broadcast failures.
        }

        return response()->json(['status' => 'ok']);
    }

    public function update(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('update', $message);

        $updated = $this->messageService->update($message, $request->validated()['body']);

        return response()->json([
            'message' => $updated->load(['user', 'reactions', 'files']),
        ]);
    }

    public function destroy(Message $message): JsonResponse
    {
        $this->authorize('delete', $message);

        $this->messageService->delete($message);

        return response()->json(['deleted' => true]);
    }

    public function pin(Message $message): JsonResponse
    {
        $this->authorize('pin', $message);

        $pinned = $this->messageService->pin($message, auth()->user());

        return response()->json([
            'message' => $pinned->load(['user', 'reactions', 'files']),
        ]);
    }

    public function unpin(Message $message): JsonResponse
    {
        $this->authorize('pin', $message);

        $unpinned = $this->messageService->unpin($message);

        return response()->json([
            'message' => $unpinned->load(['user', 'reactions', 'files']),
        ]);
    }

    public function forward(ForwardMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        $result = $this->messageService->forward(
            $message,
            auth()->user(),
            $request->input('channel_ids', []),
            $request->input('user_ids', []),
            $request->input('comment'),
        );

        return response()->json([
            'forwarded_to_channels'      => count($result['channels']),
            'forwarded_to_conversations' => count($result['conversations']),
        ]);
    }
}
