<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceEmoji extends Model
{
    // Eloquent's pluralizer treats "emoji" as invariant (like "fish"), which
    // would guess `workspace_emoji` — override to match the actual migration.
    protected $table = 'workspace_emojis';

    protected $fillable = [
        'shortcode', 'kind', 'uploaded_by', 'file_path', 'thumb_path',
        'mime_type', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }

    /**
     * Served through an authenticated streaming route, same posture as
     * File::getViewUrlAttribute() — never a direct/public disk URL.
     */
    public function getImageUrlAttribute(): string
    {
        return "/emojis/{$this->id}/image";
    }

    public function toPickerArray(): array
    {
        return [
            'id'        => $this->id,
            'shortcode' => $this->shortcode,
            'kind'      => $this->kind,
            'image_url' => $this->image_url,
        ];
    }
}
