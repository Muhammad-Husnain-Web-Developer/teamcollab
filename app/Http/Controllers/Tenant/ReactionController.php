<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Message;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    public function __construct(private readonly MessageService $messageService)
    {
    }

    public function toggle(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        $validated = $request->validate([
            // Unicode emoji are a handful of bytes; :shortcode: custom emoji
            // can run up to 34 chars (`:` + 32-char shortcode + `:`).
            'emoji' => ['required', 'string', 'max:34'],
        ]);

        $result = $this->messageService->react($message, auth()->user(), $validated['emoji']);

        // Shape: { action: 'added'|'removed', reactions: grouped[] }
        return response()->json($result);
    }
}
