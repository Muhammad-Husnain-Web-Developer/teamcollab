<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class WorkspaceSetting extends Model
{
    protected $fillable = [
        'allow_guest_access',
        'default_channel_id',
        'message_retention_days',
        'file_storage_limit_mb',
        'allowed_file_types',
        'max_file_size_mb',
        'enable_emoji_reactions',
        'enable_thread_replies',
        'enable_direct_messages',
        'require_email_verification',
    ];

    protected function casts(): array
    {
        return [
            'allow_guest_access'         => 'boolean',
            'enable_emoji_reactions'     => 'boolean',
            'enable_thread_replies'      => 'boolean',
            'enable_direct_messages'     => 'boolean',
            'require_email_verification' => 'boolean',
            'message_retention_days'     => 'integer',
            'file_storage_limit_mb'      => 'integer',
            'max_file_size_mb'           => 'integer',
        ];
    }

    /**
     * Retrieve the single workspace settings row, or return defaults.
     */
    public static function getSettings(): self
    {
        return static::firstOrCreate([], [
            'allow_guest_access'         => false,
            'default_channel_id'         => null,
            'message_retention_days'     => 365,
            'file_storage_limit_mb'      => 5120,
            'allowed_file_types'         => json_encode(['image/*', 'application/pdf', 'text/*', 'video/*', 'audio/*']),
            'max_file_size_mb'           => 25,
            'enable_emoji_reactions'     => true,
            'enable_thread_replies'      => true,
            'enable_direct_messages'     => true,
            'require_email_verification' => false,
        ]);
    }

    public function getAllowedFileTypesArrayAttribute(): array
    {
        if (is_string($this->allowed_file_types)) {
            return json_decode($this->allowed_file_types, true) ?? [];
        }
        return (array) $this->allowed_file_types;
    }

    public function getMaxFileSizeBytesAttribute(): int
    {
        return $this->max_file_size_mb * 1024 * 1024;
    }
}
