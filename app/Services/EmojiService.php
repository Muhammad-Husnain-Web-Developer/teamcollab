<?php

namespace App\Services;

use App\Models\Tenant\WorkspaceEmoji;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;

class EmojiService
{
    /**
     * Upload a custom emoji/sticker, resizing to a 128px master and a 32px
     * thumbnail (mirrors ProcessFileUploadJob's thumbnailing approach).
     * Re-encoding to PNG also means an uploaded GIF/JPEG/WEBP never reaches
     * storage in its original format — one less thing to sanity-check later.
     */
    public function upload(UploadedFile $file, string $shortcode, User $user, string $kind = 'emoji'): WorkspaceEmoji
    {
        $tenantId  = tenant('id');
        $disk      = config('filesystems.default', 'local');
        $directory = "tenants/{$tenantId}/emojis";

        $manager  = new ImageManager(new Driver());
        $contents = file_get_contents($file->getRealPath());

        $masterName = Str::uuid() . '.png';
        $thumbName  = 'thumb_' . $masterName;

        $master = $manager->decode($contents);
        $master->scaleDown(width: 128, height: 128);
        Storage::disk($disk)->put("{$directory}/{$masterName}", $master->encode(new PngEncoder())->toString());

        $thumb = $manager->decode($contents);
        $thumb->scaleDown(width: 32, height: 32);
        Storage::disk($disk)->put("{$directory}/{$thumbName}", $thumb->encode(new PngEncoder())->toString());

        return WorkspaceEmoji::create([
            'shortcode'   => strtolower($shortcode),
            'kind'        => $kind,
            'uploaded_by' => $user->id,
            'file_path'   => "{$directory}/{$masterName}",
            'thumb_path'  => "{$directory}/{$thumbName}",
            'mime_type'   => 'image/png',
            'is_active'   => true,
        ]);
    }

    /**
     * Soft-delete: flips is_active rather than removing the row, so historical
     * messages/reactions that already used this :shortcode: keep rendering.
     */
    public function delete(WorkspaceEmoji $emoji): void
    {
        $emoji->update(['is_active' => false]);
    }

    public function list(?string $kind = null): Collection
    {
        return WorkspaceEmoji::active()
            ->when($kind, fn ($q) => $q->ofKind($kind))
            ->orderBy('shortcode')
            ->get();
    }
}
