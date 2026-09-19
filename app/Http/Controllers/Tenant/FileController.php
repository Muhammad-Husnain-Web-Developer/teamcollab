<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\File\UploadFileRequest;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\ConversationParticipant;
use App\Models\Tenant\File;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(private readonly FileService $fileService)
    {
    }

    public function index(Request $request): Response|JsonResponse
    {
        $user = auth()->user();

        // Only files in channels/conversations the caller can actually see —
        // public channels, private channels/DMs they belong to, or their own
        // uploads (matches FilePolicy::view).
        $visibleChannelIds = ChannelMember::where('user_id', $user->id)->pluck('channel_id')
            ->merge(Channel::where('type', 'public')->pluck('id'))
            ->unique();
        $visibleConversationIds = ConversationParticipant::where('user_id', $user->id)->pluck('conversation_id');

        $query = File::with('uploadedBy')
            ->where(function ($q) use ($visibleChannelIds, $visibleConversationIds, $user): void {
                $q->whereIn('channel_id', $visibleChannelIds)
                    ->orWhereIn('conversation_id', $visibleConversationIds)
                    ->orWhere('uploaded_by', $user->id);
            })
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('mime_type', 'like', $request->type . '/%');
        }

        if ($request->filled('search')) {
            $query->where('original_name', 'like', '%' . $request->search . '%');
        }

        // Scope to one channel or conversation — used by the channel/DM info
        // panels' "shared files" preview, layered on top of the visibility
        // check above (so it can never leak a channel/DM the user isn't in).
        if ($request->filled('channel_id')) {
            $query->where('channel_id', (int) $request->channel_id);
        }

        if ($request->filled('conversation_id')) {
            $query->where('conversation_id', (int) $request->conversation_id);
        }

        if ($request->wantsJson()) {
            $files = $query->limit(20)->get()->map(fn (File $file) => [
                'id'            => $file->id,
                'original_name' => $file->original_name,
                'type'          => $file->type,
                'size_human'    => $file->size_human,
                'view_url'      => $file->view_url,
                'download_url'  => $file->download_url,
                'created_at'    => $file->created_at->toIso8601String(),
            ]);

            return response()->json(['files' => $files]);
        }

        $files = $query->paginate(30)->through(fn(File $file) => [
            'id'            => $file->id,
            'original_name' => $file->original_name,
            'mime_type'     => $file->mime_type,
            'size'          => $file->size,
            'size_human'    => $file->size_human,
            'url'           => $file->url,
            'uploaded_by'   => $file->uploadedBy?->only(['id', 'name', 'avatar_url']),
            'created_at'    => $file->created_at->toISOString(),
        ]);

        return Inertia::render('Files/Index', [
            'files'   => $files,
            'filters' => $request->only(['type', 'search']),
        ]);
    }

    public function store(UploadFileRequest $request): JsonResponse
    {
        $file = $this->fileService->upload($request->file('file'), auth()->user(), [
            'channel_id'      => $request->input('channel_id'),
            'conversation_id' => $request->input('conversation_id'),
        ]);

        return response()->json([
            'file' => [
                'id'            => $file->id,
                'original_name' => $file->original_name,
                'mime_type'     => $file->mime_type,
                'type'          => $file->type,
                'extension'     => $file->extension,
                'size'          => $file->size,
                'size_human'    => $file->size_human,
                'view_url'      => $file->view_url,
                'download_url'  => $file->download_url,
            ],
        ], 201);
    }

    public function destroy(File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        $this->fileService->delete($file);

        return response()->json(['deleted' => true]);
    }

    /**
     * Stream the file inline (image previews, in-browser viewing).
     */
    public function view(File $file): StreamedResponse
    {
        $this->authorize('view', $file);

        return Storage::disk($file->disk ?: 'local')->response($file->path, $file->original_name);
    }

    public function download(File $file): StreamedResponse
    {
        $this->authorize('view', $file);

        return Storage::disk($file->disk ?: 'local')->download($file->path, $file->original_name);
    }
}
