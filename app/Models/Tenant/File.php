<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uploaded_by', 'message_id', 'channel_id', 'conversation_id',
        'original_name', 'stored_name', 'disk', 'path', 'url',
        'mime_type', 'extension', 'size', 'type', 'thumbnail_path',
        'metadata', 'is_public',
    ];

    /** Included in every JSON payload (messages, uploads, file lists). */
    protected $appends = ['size_human', 'view_url', 'download_url'];

    protected function casts(): array
    {
        return [
            'size'      => 'integer',
            'is_public' => 'boolean',
            'metadata'  => 'array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }

    /**
     * Files are served through authenticated streaming routes — never via
     * direct storage URLs (the local disk is private).
     */
    public function getViewUrlAttribute(): string
    {
        return "/files/{$this->id}/view";
    }

    public function getDownloadUrlAttribute(): string
    {
        return "/files/{$this->id}/download";
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isDocument(): bool
    {
        return $this->type === 'document';
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public static function resolveType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/')       => 'image',
            str_starts_with($mimeType, 'video/')       => 'video',
            str_starts_with($mimeType, 'audio/')       => 'audio',
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
            ])                                          => 'document',
            in_array($mimeType, [
                'application/zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
            ])                                          => 'archive',
            default                                     => 'other',
        };
    }
}
