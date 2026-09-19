<?php

namespace App\Services;

use App\Jobs\ProcessFileUploadJob;
use App\Models\Tenant\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileService
{
    /** Maximum file size in bytes (matches UploadFileRequest: 50 MB). */
    private int $maxSizeBytes = 50 * 1024 * 1024;

    /** Allowed MIME types (kept in sync with UploadFileRequest mimes rule). */
    private array $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv',
        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo',
        'audio/mpeg', 'audio/wav', 'audio/mp3',
        // Voice messages: real-world MediaRecorder output varies by browser
        // (Chrome/Firefox → webm/ogg with opus, Safari → mp4/m4a).
        'audio/webm', 'audio/ogg', 'audio/mp4',
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/octet-stream',
    ];

    /**
     * Upload and persist a file record, then queue async processing.
     *
     * @param  UploadedFile $file
     * @param  User         $user
     * @param  array        $context  ['channel_id' => int, 'message_id' => int|null, 'conversation_id' => int|null]
     * @return File
     *
     * @throws ValidationException
     */
    public function upload(UploadedFile $file, User $user, array $context): File
    {
        $this->validate($file);

        $tenantId  = tenant('id');
        $disk      = config('filesystems.default', 'local');
        $mimeType  = $file->getMimeType() ?? 'application/octet-stream';
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid() . '.' . $extension;
        $directory  = "tenants/{$tenantId}/files/" . date('Y/m');
        $path       = $directory . '/' . $storedName;

        // Store the raw file
        $file->storeAs($directory, $storedName, ['disk' => $disk]);

        /** @var File $fileModel */
        $fileModel = File::create([
            'uploaded_by'     => $user->id,
            'message_id'      => $context['message_id'] ?? null,
            'channel_id'      => $context['channel_id'] ?? null,
            'conversation_id' => $context['conversation_id'] ?? null,
            'original_name'   => $file->getClientOriginalName(),
            'stored_name'     => $storedName,
            'disk'            => $disk,
            'path'            => $path,
            'url'             => null, // served via authenticated /files/{id}/view route
            'mime_type'       => $mimeType,
            'extension'       => $extension,
            'size'            => $file->getSize(),
            'type'            => File::resolveType($mimeType),
            'thumbnail_path'  => null,
            'metadata'        => [
                'original_name' => $file->getClientOriginalName(),
                'uploaded_at'   => now()->toIso8601String(),
            ],
            'is_public'       => false,
        ]);

        // Queue thumbnail generation and metadata extraction
        ProcessFileUploadJob::dispatch($fileModel->id, $tenantId)
            ->onQueue('files');

        return $fileModel;
    }

    /**
     * Delete a file from storage and remove the database record.
     */
    public function delete(File $file): void
    {
        DB::transaction(function () use ($file): void {
            $disk = $file->disk ?: 'local';

            // Delete main file
            if (Storage::disk($disk)->exists($file->path)) {
                Storage::disk($disk)->delete($file->path);
            }

            // Delete thumbnail if it exists
            if ($file->thumbnail_path && Storage::disk($disk)->exists($file->thumbnail_path)) {
                Storage::disk($disk)->delete($file->thumbnail_path);
            }

            $file->delete();
        });
    }

    /**
     * Calculate total storage used by this tenant in bytes.
     */
    public function getStorageUsed(): int
    {
        return (int) File::whereNull('deleted_at')->sum('size');
    }

    /**
     * Validate the uploaded file against size and MIME type rules.
     *
     * @throws ValidationException
     */
    private function validate(UploadedFile $file): void
    {
        $errors = [];

        if ($file->getSize() > $this->maxSizeBytes) {
            $maxMb    = $this->maxSizeBytes / 1024 / 1024;
            $errors[] = "File exceeds the maximum allowed size of {$maxMb} MB.";
        }

        $mimeType = $file->getMimeType() ?? '';

        $isAllowed = collect($this->allowedMimes)->contains(
            fn (string $allowed) => $allowed === $mimeType
                || (str_ends_with($allowed, '/*') && str_starts_with($mimeType, rtrim($allowed, '*')))
        );

        if (! $isAllowed) {
            $errors[] = "File type '{$mimeType}' is not allowed.";
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages(['file' => $errors]);
        }
    }
}
