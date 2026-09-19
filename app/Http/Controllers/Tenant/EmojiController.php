<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Emoji\UploadEmojiRequest;
use App\Models\Tenant\WorkspaceEmoji;
use App\Services\EmojiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmojiController extends Controller
{
    public function __construct(private readonly EmojiService $emojis)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'emojis' => $this->emojis->list()->map->toPickerArray()->values(),
        ]);
    }

    /**
     * The admin-facing "manage custom emoji" settings page.
     */
    public function page(): Response
    {
        return Inertia::render('Workspace/EmojiSettings', [
            'emojis' => $this->emojis->list()->map->toPickerArray()->values(),
        ]);
    }

    public function store(UploadEmojiRequest $request): JsonResponse
    {
        $this->authorize('update', tenant());

        $emoji = $this->emojis->upload(
            $request->file('file'),
            $request->validated()['shortcode'],
            auth()->user(),
        );

        return response()->json(['emoji' => $emoji->toPickerArray()], 201);
    }

    public function destroy(WorkspaceEmoji $emoji): JsonResponse
    {
        $this->authorize('update', tenant());

        $this->emojis->delete($emoji);

        return response()->json(['deleted' => true]);
    }

    public function image(WorkspaceEmoji $emoji): StreamedResponse
    {
        abort_unless($emoji->file_path, 404);

        return Storage::disk(config('filesystems.default', 'local'))->response($emoji->file_path);
    }
}
