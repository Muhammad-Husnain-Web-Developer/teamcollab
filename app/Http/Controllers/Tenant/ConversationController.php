<?php

namespace App\Http\Controllers\Tenant;

use App\Events\DmMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\SendMessageRequest;
use App\Models\Tenant\Conversation;
use App\Models\User;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\SlashCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        // Use participantEntries (HasMany on tenant DB) — no cross-DB join
        $conversations = Conversation::whereHas('participantEntries', fn($q) => $q->where('user_id', $user->id))
            ->with(['participantEntries' => fn($q) => $q->where('user_id', '!=', $user->id)])
            ->latest('updated_at')
            ->get();

        // Fetch other-participant User records in one query (central DB)
        $otherUserIds = $conversations
            ->flatMap(fn($c) => $c->participantEntries->pluck('user_id'))
            ->unique()
            ->values();

        $users = User::whereIn('id', $otherUserIds)
            ->get(['id', 'name', 'display_name', 'avatar', 'status'])
            ->keyBy('id');

        $unread = app(\App\Services\UnreadService::class)->conversationCounts($user);

        $result = $conversations->map(function (Conversation $conversation) use ($users, $unread) {
            $otherUserId = $conversation->participantEntries->first()?->user_id;
            $other       = $otherUserId ? ($users[$otherUserId] ?? null) : null;

            return [
                'id'          => $conversation->id,
                'other_user'  => $other ? [
                    'id'           => $other->id,
                    'name'         => $other->name,
                    'display_name' => $other->display_name,
                    'avatar_url'   => $other->avatar_url,
                    'status'       => $other->status,
                ] : null,
                'last_message' => $conversation->messages()->latest()->first()?->only(['id', 'body', 'created_at']),
                'unread_count' => $unread[$conversation->id] ?? 0,
            ];
        });

        return Inertia::render('DirectMessage/Index', [
            'conversations' => $result,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:mysql.users,id'],
        ]);

        $currentUser = auth()->user();
        $targetUser  = User::findOrFail($request->user_id);

        if ($currentUser->id === $targetUser->id) {
            return back()->withErrors(['user_id' => 'You cannot start a conversation with yourself.']);
        }

        // Use participantEntries HasMany (tenant DB only) — no cross-DB join
        $conversation = Conversation::whereHas('participantEntries', fn($q) => $q->where('user_id', $currentUser->id))
            ->whereHas('participantEntries', fn($q) => $q->where('user_id', $targetUser->id))
            ->where('type', 'direct')
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'type'       => 'direct',
                'created_by' => $currentUser->id,
            ]);
            $conversation->participantEntries()->createMany([
                ['user_id' => $currentUser->id],
                ['user_id' => $targetUser->id],
            ]);
        }

        return redirect()->route('dm.show', $conversation);
    }

    public function show(Conversation $conversation): Response
    {
        $user = auth()->user();

        abort_unless(
            $conversation->participantEntries()->where('user_id', $user->id)->exists(),
            403
        );

        $otherEntry  = $conversation->participantEntries()->where('user_id', '!=', $user->id)->first();
        $participant = $otherEntry ? User::find($otherEntry->user_id) : null;

        $ownEntry = $conversation->participantEntries()->where('user_id', $user->id)->first();

        return Inertia::render('DirectMessage/Show', [
            'conversation' => [
                'id'          => $conversation->id,
                'type'        => $conversation->type,
                'is_muted'    => (bool) $ownEntry?->is_muted,
                'participant' => $participant ? [
                    'id'           => $participant->id,
                    'name'         => $participant->name,
                    'display_name' => $participant->display_name,
                    'avatar_url'   => $participant->avatar_url,
                    'status'       => $participant->status,
                ] : null,
            ],
        ]);
    }

    public function getMessages(Conversation $conversation): JsonResponse
    {
        abort_unless(
            $conversation->participantEntries()->where('user_id', auth()->id())->exists(),
            403
        );

        $messages = $conversation->messages()
            ->with(['user', 'reactions', 'files', 'parent.user'])
            ->latest()
            ->paginate(request()->integer('per_page', 50));

        return response()->json($messages);
    }

    /**
     * Mark this conversation read up to now, clearing the caller's unread badge.
     */
    public function markRead(Conversation $conversation, \App\Services\UnreadService $unread): JsonResponse
    {
        abort_unless(
            $conversation->participantEntries()->where('user_id', auth()->id())->exists(),
            403
        );

        $unread->markConversationRead($conversation->id, auth()->user());

        return response()->json(['unread_count' => 0]);
    }

    /**
     * Toggle mute for this conversation. Muted DMs still receive messages but
     * raise no notification.
     */
    public function toggleMute(Conversation $conversation): JsonResponse
    {
        $entry = $conversation->participantEntries()
            ->where('user_id', auth()->id())
            ->first();

        abort_unless((bool) $entry, 403);

        $entry->update(['is_muted' => ! $entry->is_muted]);

        return response()->json(['is_muted' => (bool) $entry->is_muted]);
    }

    /**
     * Broadcast that the current user is typing in this conversation.
     * Fire-and-forget: never fails the caller if the broadcaster is down.
     */
    public function typing(Conversation $conversation): JsonResponse
    {
        abort_unless(
            $conversation->participantEntries()->where('user_id', auth()->id())->exists(),
            403
        );

        $user = auth()->user();

        try {
            event(new \App\Events\UserTyping(
                $user->id,
                $user->display_name ?? $user->name,
                null,
                $conversation->id,
                tenant('id'),
            ));
        } catch (\Throwable) {
            // Typing indicators are cosmetic — swallow broadcast failures.
        }

        return response()->json(['status' => 'ok']);
    }

    public function sendMessage(
        SendMessageRequest $request,
        Conversation $conversation,
        NotificationService $notifications,
        MessageService $messages,
        SlashCommandService $slashCommands,
    ): JsonResponse {
        abort_unless(
            $conversation->participantEntries()->where('user_id', auth()->id())->exists(),
            403
        );

        $slash = $slashCommands->parse((string) $request->input('body'));
        if ($slash) {
            return response()->json(
                $slashCommands->execute($slash['command'], $slash['args'], $conversation, auth()->user())
            );
        }

        $user = auth()->user();

        $metadata = $messages->buildTypeMetadata($request->validated());

        $message = $conversation->messages()->create([
            'user_id'   => $user->id,
            'body'      => $request->body ?? '',
            'type'      => $request->input('type', 'text'),
            'parent_id' => $request->input('parent_id'),
            'metadata'  => $metadata,
        ]);

        // Link pre-uploaded files to this DM message
        if ($request->filled('files')) {
            \App\Models\Tenant\File::whereIn('id', $request->input('files'))
                ->where('uploaded_by', $user->id)
                ->whereNull('message_id')
                ->update([
                    'message_id'      => $message->id,
                    'conversation_id' => $conversation->id,
                ]);
        }

        $message->load(['reactions', 'files', 'parent.user']);
        $message->setRelation('user', $user);

        $conversation->touch();

        try {
            event(new DmMessageSent($message, tenant('id')));
        } catch (\Throwable) {
            // Broadcast failure is non-fatal — message already persisted.
        }

        $messages->queueLinkPreviews($message);

        // Notify the other participant(s), skipping anyone who muted this DM
        $recipientIds = $conversation->participantEntries()
            ->where('user_id', '!=', $user->id)
            ->where('is_muted', false)
            ->pluck('user_id');

        $preview = $message->body === ''
            ? '📎 Sent an attachment'
            : (mb_strlen($message->body) > 200
                ? mb_substr($message->body, 0, 197) . '...'
                : $message->body);

        $notifications->notifyMany($recipientIds, 'dm_message', [
            'message_id'      => $message->id,
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'sender_name'     => $user->display_name ?? $user->name,
            'sender_avatar'   => $user->avatar_url,
            'preview'         => $preview,
            'tenant_id'       => tenant('id'),
        ]);

        return response()->json([
            'message' => $message,
        ], 201);
    }
}
