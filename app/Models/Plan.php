<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'name', 'slug', 'description', 'price_monthly', 'price_yearly',
        'currency', 'max_members', 'max_channels', 'storage_limit',
        'has_file_sharing', 'has_video_calls', 'has_analytics',
        'has_custom_roles', 'is_active', 'features', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly'    => 'decimal:2',
            'price_yearly'     => 'decimal:2',
            'has_file_sharing' => 'boolean',
            'has_video_calls'  => 'boolean',
            'has_analytics'    => 'boolean',
            'has_custom_roles' => 'boolean',
            'is_active'        => 'boolean',
            'features'         => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function isFree(): bool
    {
        return $this->price_monthly == 0;
    }

    public function getStorageLimitHumanAttribute(): string
    {
        $bytes = $this->storage_limit;
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        return $bytes . ' B';
    }
}
