<?php

namespace App\Jobs;

use App\Models\Tenant\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;

class ProcessFileUploadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(
        public readonly int $fileId,
        public readonly string $tenantId,
    ) {
        $this->onQueue('files');
    }

    public function handle(): void
    {
        $file = File::find($this->fileId);

        if (! $file) {
            Log::warning("ProcessFileUploadJob: File #{$this->fileId} not found.");
            return;
        }

        $disk = $file->disk ?: 'local';

        if (! Storage::disk($disk)->exists($file->path)) {
            Log::warning("ProcessFileUploadJob: File path {$file->path} does not exist on disk {$disk}.");
            return;
        }

        // Generate thumbnail for images
        if ($file->type === 'image' && $this->isImageMime($file->mime_type)) {
            $this->generateThumbnail($file, $disk);
        }

        // Update metadata after processing
        $file->update([
            'metadata' => array_merge($file->metadata ?? [], [
                'processed'    => true,
                'processed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    private function generateThumbnail(File $file, string $disk): void
    {
        try {
            $manager  = new ImageManager(new Driver());
            $contents = Storage::disk($disk)->get($file->path);

            if (! $contents) {
                return;
            }

            $image = $manager->decode($contents);

            // Resize to thumbnail maintaining aspect ratio — max 320×240
            $image->scaleDown(width: 320, height: 240);

            $thumbDir  = 'thumbnails/' . dirname($file->path);
            $thumbName = 'thumb_' . basename($file->path);
            $thumbPath = ltrim($thumbDir . '/' . $thumbName, '/');

            $encoded = $image->encode(new JpegEncoder(quality: 80));

            Storage::disk($disk)->put($thumbPath, $encoded->toString());

            $file->update(['thumbnail_path' => $thumbPath]);
        } catch (\Throwable $e) {
            Log::error("ProcessFileUploadJob: thumbnail generation failed for file #{$file->id}: {$e->getMessage()}");
        }
    }

    private function isImageMime(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
        ], true);
    }
}
