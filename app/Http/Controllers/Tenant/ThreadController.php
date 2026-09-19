<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\SendMessageRequest;
use App\Models\Tenant\Message;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;

/**
 * A thread is the set of replies hanging off one root message (`parent_id`).
 * Replies are ordinary messages, so they keep working inline in the channel;
 * this controller just gathers them into one view and lets you add to them.
 */
class ThreadController extends Controller
{
    public function __construct(private readonly MessageService $messages)
    {
    }

    /**
     * Root message plus every reply, oldest first.
     */
    public function show(Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        // Opening a reply should show the whole thread, not an empty one.
        $root = $message->parent_id
            ? Message::with(['user', 'reactions', 'files'])->findOrFail($message->parent_id)
            : $message->load(['user', 'reactions', 'files']);

        // The reply and its root normally share a channel/conversation, but
        // that isn't enforced at write time everywhere — re-check the root
        // explicitly so a crafted parent_id can never leak a foreign message.
        $this->authorize('view', $root);

        $replies = Message::where('parent_id', $root->id)
            ->with(['user', 'reactions', 'files'])
            ->oldest()
            ->get();

        return response()->json([
            'root'          => $root,
            'replies'       => $replies,
            'replies_count' => $replies->count(),
        ]);
    }

    /**
     * Post a reply into this thread.
     */
    public function store(SendMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        // Replying to a reply still lands in the same thread — threads are one
        // level deep, which keeps ordering and counts unambiguous.
        $rootId = $message->parent_id ?? $message->id;

        $reply = $this->messages->send([
            'channel_id'      => $message->channel_id,
            'conversation_id' => $message->conversation_id,
            'parent_id'       => $rootId,
            'body'            => $request->validated('body') ?? '',
            'files'           => $request->validated('files') ?? [],
        ], $request->user());

        return response()->json(['message' => $reply], 201);
    }
}
